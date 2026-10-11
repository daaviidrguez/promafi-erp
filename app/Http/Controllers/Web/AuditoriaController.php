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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        $detalle = $ruta === ''
            ? $peticiones
            : array_values(array_filter(
                $peticiones,
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

        return view('auditoria.rendimiento', [
            'desde' => $desde,
            'hasta' => $hasta,
            'ruta' => $ruta,
            'umbral' => LogSlowRequests::umbralSegundos(),
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
            'semaforo' => RendimientoSemaforo::global($peticiones, $erroresMysql, count($picos), $resumen, $dias),
            'resumen' => $resumen,
            'picos' => $picos,
            'erroresMysql' => array_slice($erroresMysql, 0, self::MAX_DETALLE),
            'detalle' => array_slice($detalle, 0, self::MAX_DETALLE),
            'detalleTotal' => count($detalle),
            'maxDetalle' => self::MAX_DETALLE,
            'truncado' => count($entradas) >= RendimientoLogLector::MAX_ENTRADAS,
            'usuarios' => $usuarios,
        ]);
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
