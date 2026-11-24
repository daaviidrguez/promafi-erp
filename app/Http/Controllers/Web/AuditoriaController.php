<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Middleware\LogSlowRequests;
use App\Models\AuditoriaExcepcion;
use App\Models\User;
use App\Services\AuditoriaExcepcionService;
use App\Support\Rendimiento\RendimientoLogLector;
use App\Support\Rendimiento\RendimientoSemaforo;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use Throwable;

class AuditoriaController extends Controller
{
    private const MAX_DETALLE = 200;

    private const DIAS_ERRORES_POR_DEFECTO = 7;

    public function rendimiento(Request $request, RendimientoLogLector $lector): View
    {
        $hoy = CarbonImmutable::today();
        $desde = $this->fecha($request->query('desde'), $hoy);
        $hasta = $this->fecha($request->query('hasta'), $hoy);
        if ($hasta->lessThan($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        if ($desde->diffInDays($hasta) >= RendimientoLogLector::MAX_DIAS) {
            $desde = $hasta->subDays(RendimientoLogLector::MAX_DIAS - 1);
        }

        $ruta = trim((string) $request->query('ruta', ''));

        $entradas = $lector->leer($desde, $hasta);
        $peticiones = array_map(
            fn (array $e) => $e + ['semaforo' => RendimientoSemaforo::peticion($e['datos'])],
            RendimientoLogLector::soloPeticiones($entradas)
        );
        $paginas = array_map(
            fn (array $e) => $e + ['semaforo' => RendimientoSemaforo::pagina($e['datos'])],
            RendimientoLogLector::soloPaginas($entradas)
        );
        $erroresMysql = RendimientoLogLector::soloErroresMysql($entradas);
        $picos = RendimientoLogLector::picos($peticiones);
        $dias = (int) round($desde->diffInDays($hasta)) + 1;

        $semaforosPorRuta = [];
        foreach ($peticiones as $e) {
            $semaforosPorRuta[RendimientoLogLector::claveRuta($e['datos'])][] = $e['semaforo'];
        }
        $enSaturacion = RendimientoLogLector::rutasEnSaturacion($peticiones);
        $resumen = array_map(
            fn (array $r) => $r + ['semaforo' => RendimientoSemaforo::ruta(
                $r,
                $semaforosPorRuta[$r['ruta']] ?? [],
                isset($enSaturacion[$r['ruta']]),
                $dias,
            )],
            RendimientoLogLector::resumenPorRuta($peticiones)
        );

        $resumenPaginas = array_map(
            function (array $r) {
                $nivel = $r['rojos'] > 0
                    ? RendimientoSemaforo::ROJO
                    : ($r['amarillos'] > 0 ? RendimientoSemaforo::AMARILLO : RendimientoSemaforo::VERDE);
                $seg = (float) $r['promedio'];

                return $r + [
                    'semaforo' => RendimientoSemaforo::pagina([
                        'nivel' => $nivel,
                        'segundos' => $seg,
                        'origen' => 'servidor',
                    ]),
                ];
            },
            RendimientoLogLector::resumenPaginasPorRuta($paginas)
        );

        $detalle = $ruta === ''
            ? $peticiones
            : array_values(array_filter(
                $peticiones,
                fn (array $e) => ($e['datos']['route'] ?? null) === $ruta || ($e['datos']['path'] ?? null) === $ruta
            ));

        $detallePaginas = $ruta === ''
            ? $paginas
            : array_values(array_filter(
                $paginas,
                fn (array $e) => ($e['datos']['route'] ?? null) === $ruta || ($e['datos']['path'] ?? null) === $ruta
            ));

        $userIds = collect($entradas)
            ->map(fn (array $e) => $e['datos']['user_id'] ?? null)
            ->filter()
            ->unique()
            ->values();
        $usuarios = $userIds->isEmpty()
            ? collect()
            : User::query()->whereIn('id', $userIds)->pluck('name', 'id');

        $segundos = array_map(fn (array $e) => (float) ($e['datos']['segundos'] ?? 0), $peticiones);
        $conteoPaginas = [
            'total' => count($paginas),
            'verdes' => 0,
            'amarillos' => 0,
            'rojos' => 0,
        ];
        foreach ($paginas as $p) {
            $n = $p['semaforo']['nivel'];
            if ($n === RendimientoSemaforo::ROJO) {
                $conteoPaginas['rojos']++;
            } elseif ($n === RendimientoSemaforo::AMARILLO) {
                $conteoPaginas['amarillos']++;
            } else {
                $conteoPaginas['verdes']++;
            }
        }

        return view('auditoria.rendimiento', [
            'desde' => $desde,
            'hasta' => $hasta,
            'ruta' => $ruta,
            'umbral' => LogSlowRequests::umbralSegundos(),
            'muestraPct' => LogSlowRequests::muestraPaginaPct(),
            'totales' => [
                'peticiones' => count($peticiones),
                'abortadas' => count(array_filter($peticiones, fn (array $e) => RendimientoSemaforo::esAbortada($e['datos']))),
                'maximo' => $segundos === [] ? 0.0 : round(max($segundos), 1),
                'errores_mysql' => count($erroresMysql),
                'con_http_externo' => count(array_filter(
                    $peticiones,
                    fn (array $e) => ! empty($e['datos']['http_externas'])
                )),
            ],
            'paginasTotales' => $conteoPaginas,
            'semaforo' => RendimientoSemaforo::global($peticiones, $erroresMysql, count($picos), $resumen, $dias),
            'resumen' => $resumen,
            'resumenPaginas' => $resumenPaginas,
            'picos' => $picos,
            'erroresMysql' => array_slice($erroresMysql, 0, self::MAX_DETALLE),
            'detalle' => array_slice($detalle, 0, self::MAX_DETALLE),
            'detallePaginas' => array_slice($detallePaginas, 0, self::MAX_DETALLE),
            'detallePaginasTotal' => count($detallePaginas),
            'detalleTotal' => count($detalle),
            'maxDetalle' => self::MAX_DETALLE,
            'truncado' => count($entradas) >= RendimientoLogLector::MAX_ENTRADAS,
            'usuarios' => $usuarios,
        ]);
    }

    /**
     * Beacon ligero del navegador (DOMContentLoaded / load). Cualquier usuario autenticado.
     */
    public function registrarPagina(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'path' => 'required|string|max:500',
            'route' => 'nullable|string|max:120',
            'dcl_s' => 'required|numeric|min:0|max:600',
            'load_s' => 'nullable|numeric|min:0|max:600',
            'ttfb_s' => 'nullable|numeric|min:0|max:600',
        ]);

        $path = '/'.ltrim(parse_url($validated['path'], PHP_URL_PATH) ?: $validated['path'], '/');
        if (str_starts_with($path, '/auditoria') || $path === '/rendimiento/pagina') {
            return response()->json(['ok' => true, 'skipped' => true]);
        }

        $dcl = round((float) $validated['dcl_s'], 2);
        $nivel = RendimientoSemaforo::nivelPorSegundosPagina($dcl);

        if ($nivel === RendimientoSemaforo::VERDE) {
            $pct = LogSlowRequests::muestraPaginaPct();
            if ($pct <= 0 || random_int(1, 100) > $pct) {
                return response()->json(['ok' => true, 'skipped' => true]);
            }
        }

        try {
            Log::channel('rendimiento')->info(RendimientoLogLector::TIPO_PAGINA_CLIENTE, [
                'origen' => 'cliente',
                'nivel' => $nivel,
                'segundos' => $dcl,
                'dcl_s' => $dcl,
                'load_s' => isset($validated['load_s']) ? round((float) $validated['load_s'], 2) : null,
                'ttfb_s' => isset($validated['ttfb_s']) ? round((float) $validated['ttfb_s'], 2) : null,
                'method' => 'GET',
                'path' => $path,
                'route' => $validated['route'] ?? null,
                'user_id' => $request->user()?->id,
                'status' => 200,
            ]);
        } catch (Throwable) {
            // Telemetría best-effort.
        }

        return response()->json(['ok' => true]);
    }

    public function errores(Request $request): View
    {
        $hoy = CarbonImmutable::today();
        $desde = $this->fecha($request->query('desde'), $hoy->subDays(self::DIAS_ERRORES_POR_DEFECTO - 1));
        $hasta = $this->fecha($request->query('hasta'), $hoy);
        if ($hasta->lessThan($desde)) {
            [$desde, $hasta] = [$hasta, $desde];
        }
        $q = trim((string) $request->query('q', ''));

        $filtrada = AuditoriaExcepcion::query()
            ->where('created_at', '>=', $desde->startOfDay())
            ->where('created_at', '<=', $hasta->endOfDay())
            ->when($q !== '', function ($query) use ($q) {
                $term = '%'.$q.'%';
                $query->where(function ($qq) use ($term) {
                    $qq->where('excepcion', 'like', $term)
                        ->orWhere('mensaje', 'like', $term)
                        ->orWhere('archivo', 'like', $term)
                        ->orWhere('ruta', 'like', $term)
                        ->orWhere('ruta_nombre', 'like', $term);
                });
            });

        $frecuentes = (clone $filtrada)
            ->select('excepcion', 'archivo', DB::raw('COUNT(*) as veces'), DB::raw('MAX(created_at) as ultima'), DB::raw('MAX(mensaje) as mensaje'), DB::raw('COUNT(DISTINCT ruta) as rutas'))
            ->groupBy('excepcion', 'archivo')
            ->orderByDesc('veces')
            ->limit(10)
            ->get();

        $registros = (clone $filtrada)
            ->with('usuario:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        return view('auditoria.errores', [
            'desde' => $desde,
            'hasta' => $hasta,
            'q' => $q,
            'registros' => $registros,
            'frecuentes' => $frecuentes,
            'hoy' => AuditoriaExcepcion::query()->where('created_at', '>=', $hoy)->count(),
            'diasRetencion' => AuditoriaExcepcionService::DIAS_RETENCION,
        ]);
    }

    private function fecha(mixed $valor, CarbonImmutable $porDefecto): CarbonImmutable
    {
        if (! is_string($valor) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor) !== 1) {
            return $porDefecto;
        }

        try {
            return CarbonImmutable::createFromFormat('Y-m-d', $valor)->startOfDay();
        } catch (Throwable) {
            return $porDefecto;
        }
    }
}
