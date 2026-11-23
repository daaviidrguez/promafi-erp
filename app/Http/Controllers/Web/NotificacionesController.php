<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\CuentaPorCobrar;
use App\Models\LogisticaEnvio;
use App\Support\CobranzaSaldoBatch;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Aviso diario (no en vivo): como máximo 1 cálculo pesado por día;
 * si el usuario ya leyó hoy, no se vuelve a consultar CxC/NC/logística.
 */
class NotificacionesController extends Controller
{
    /**
     * Resumen del día: badge + toast (solo si aún no se leyó hoy).
     */
    public function resumen(Request $request)
    {
        $user = $request->user();
        $uid = (string) ($user?->id ?? 'anon');
        $dia = $this->claveDia();

        if ($this->yaLeidoHoy($request, $uid, $dia)) {
            return response()->json([
                'day' => $dia,
                'has_notifications' => false,
                'show_toast' => false,
                'unread_count' => 0,
                'already_read_today' => true,
            ]);
        }

        $data = $this->datosCompartidos($dia);
        $flags = $this->flagsDia($request, $uid, $dia, $data);

        $payload = [
            'day' => $dia,
            'has_notifications' => $flags['tiene_alguna'],
            'show_toast' => $flags['show_toast'],
            'unread_count' => $flags['unread_count'],
            'already_read_today' => false,
        ];

        // Toast de entrada del día: incluir detalle una sola vez.
        if ($flags['show_toast'] || $flags['tiene_alguna']) {
            $payload = array_merge($payload, $this->detalleParaUsuario($user, $data));
        }

        return response()->json($payload);
    }

    /**
     * Detalle (dropdown). Reutiliza snapshot del día; no recalcula si ya está en caché.
     */
    public function admin(Request $request)
    {
        $user = $request->user();
        $uid = (string) ($user?->id ?? 'anon');
        $dia = $this->claveDia();

        if ($this->yaLeidoHoy($request, $uid, $dia)) {
            // Aun leído: puede abrir la campana y ver el snapshot del día sin badge.
            $data = $this->datosCompartidos($dia);

            return response()->json(array_merge([
                'day' => $dia,
                'has_notifications' => $this->tieneAlguna($data),
                'show_toast' => false,
                'unread_count' => 0,
                'already_read_today' => true,
            ], $this->detalleParaUsuario($user, $data)));
        }

        $data = $this->datosCompartidos($dia);
        $flags = $this->flagsDia($request, $uid, $dia, $data, marcarToastSiAplica: false);

        return response()->json(array_merge([
            'day' => $dia,
            'has_notifications' => $flags['tiene_alguna'],
            'show_toast' => false,
            'unread_count' => $flags['unread_count'],
            'already_read_today' => false,
        ], $this->detalleParaUsuario($user, $data)));
    }

    /**
     * Marcar como leídas hasta mañana (sesión + caché del día).
     */
    public function leer(Request $request)
    {
        $user = $request->user();
        $uid = (string) ($user?->id ?? 'anon');
        $dia = $this->claveDia();

        $request->session()->put($this->sessionReadKey($uid, $dia), true);
        Cache::put($this->cacheReadKey($uid, $dia), true, $this->segundosHastaFinDeDia());

        return response()->json(['ok' => true, 'day' => $dia]);
    }

    private function claveDia(): string
    {
        return Carbon::today()->toDateString();
    }

    private function segundosHastaFinDeDia(): int
    {
        $secs = (int) now()->diffInSeconds(now()->endOfDay(), false);

        return max(60, $secs > 0 ? $secs : 60);
    }

    private function sessionReadKey(string $uid, string $dia): string
    {
        return "notifs_admin_read_{$uid}_{$dia}";
    }

    private function sessionToastKey(string $uid, string $dia): string
    {
        return "notifs_admin_toast_{$uid}_{$dia}";
    }

    private function cacheReadKey(string $uid, string $dia): string
    {
        return "notifs_admin_read_{$uid}_{$dia}";
    }

    private function yaLeidoHoy(Request $request, string $uid, string $dia): bool
    {
        if ($request->session()->get($this->sessionReadKey($uid, $dia), false)) {
            return true;
        }

        return (bool) Cache::get($this->cacheReadKey($uid, $dia), false);
    }

    /**
     * @return array{tiene_alguna: bool, show_toast: bool, unread_count: int}
     */
    private function flagsDia(Request $request, string $uid, string $dia, array $data, bool $marcarToastSiAplica = true): array
    {
        $tieneAlguna = $this->tieneAlguna($data);
        $toastKey = $this->sessionToastKey($uid, $dia);
        $toastShown = (bool) $request->session()->get($toastKey, false);
        $alreadyRead = $this->yaLeidoHoy($request, $uid, $dia);

        $showToast = $tieneAlguna && ! $toastShown && ! $alreadyRead;
        $unreadCount = $tieneAlguna && ! $alreadyRead ? 1 : 0;

        if ($marcarToastSiAplica && $showToast) {
            $request->session()->put($toastKey, true);
        }

        return [
            'tiene_alguna' => $tieneAlguna,
            'show_toast' => $showToast,
            'unread_count' => $unreadCount,
        ];
    }

    private function tieneAlguna(array $data): bool
    {
        return (bool) (
            ($data['credito_excedente'] ?? null)
            || ((int) ($data['vencidas']['cuentas_vencidas'] ?? 0) > 0)
            || ((int) ($data['logistica']['envios_factura_en_ruta'] ?? 0) > 0)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function detalleParaUsuario($user, array $data): array
    {
        $hoy = Carbon::today();

        return [
            'credito_excedente' => $data['credito_excedente'],
            'vencidas' => [
                'cuentas_vencidas' => (int) ($data['vencidas']['cuentas_vencidas'] ?? 0),
                'monto_vencido' => $data['vencidas']['monto_vencido'] ?? 0,
                'primeras_fechas' => $data['vencidas']['primeras_fechas'] ?? [],
                'hoy' => $hoy->format('d/m/Y'),
                'url' => $user?->can('cuentas_cobrar.ver')
                    ? route('cuentas-cobrar.index', ['estado' => 'vencidas'])
                    : null,
            ],
            'logistica' => [
                'envios_factura_en_ruta' => (int) ($data['logistica']['envios_factura_en_ruta'] ?? 0),
                'url' => $user?->can('logistica.ver') ? route('logistica.index', ['estado' => 'en_ruta']) : null,
            ],
        ];
    }

    /**
     * Snapshot compartido del día (todos los admins). TTL hasta medianoche.
     *
     * @return array{credito_excedente: ?array, vencidas: array, logistica: array}
     */
    private function datosCompartidos(string $dia): array
    {
        $ttl = $this->segundosHastaFinDeDia();

        return Cache::remember("notifs_admin_data_shared_{$dia}", $ttl, function () {
            $creditoExcedente = null;

            $candidatos = Cliente::query()
                ->where('activo', true)
                ->where('dias_credito', '>', 0)
                ->whereNotNull('limite_credito')
                ->orderByRaw('(saldo_actual - limite_credito) DESC')
                ->limit(5)
                ->get(['id', 'nombre', 'nombre_comercial', 'limite_credito', 'saldo_actual']);

            $cuentasCandidatos = CuentaPorCobrar::query()
                ->excluirFacturaBorrador()
                ->whereIn('cliente_id', $candidatos->pluck('id'))
                ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
                ->get(['id', 'cliente_id', 'factura_id', 'monto_pendiente', 'estado', 'fecha_vencimiento', 'monto_pagado']);
            CobranzaSaldoBatch::hidratarCuentas($cuentasCandidatos);
            $saldoPorCliente = $cuentasCandidatos
                ->groupBy('cliente_id')
                ->map(fn ($rows) => (float) $rows->sum(fn ($c) => $c->saldo_pendiente_real));

            $topExcedente = 0.0;
            $topCliente = null;

            foreach ($candidatos as $cliente) {
                $saldoCoherente = (float) ($saldoPorCliente[$cliente->id] ?? 0);
                $limite = (float) $cliente->limite_credito;
                $excedente = $saldoCoherente - $limite;

                if ($excedente > $topExcedente) {
                    $topExcedente = $excedente;
                    $topCliente = $cliente;
                }
            }

            if ($topCliente && $topExcedente > 0) {
                $creditoExcedente = [
                    'cliente_nombre' => $topCliente->nombre_comercial ?: $topCliente->nombre,
                    'saldo_excedente' => round($topExcedente, 2),
                ];
            }

            $basesVencidas = CuentaPorCobrar::query()
                ->excluirFacturaBorrador()
                ->vencidas()
                ->orderBy('fecha_vencimiento', 'asc')
                ->get(['id', 'factura_id', 'cliente_id', 'fecha_vencimiento', 'monto_pendiente', 'estado', 'monto_pagado']);

            CobranzaSaldoBatch::hidratarCuentas($basesVencidas);

            $cuentasVencidasConReal = $basesVencidas
                ->map(fn ($cuenta) => [
                    'cuenta' => $cuenta,
                    'saldo_real' => $cuenta->saldo_pendiente_real,
                ])
                ->filter(fn ($x) => $x['saldo_real'] > 0);

            $primeras3 = $cuentasVencidasConReal
                ->sortBy(fn ($x) => $x['cuenta']->fecha_vencimiento?->toDateString() ?? '9999-12-31')
                ->take(3)
                ->map(fn ($x) => [
                    'fecha_vencimiento' => $x['cuenta']->fecha_vencimiento
                        ? $x['cuenta']->fecha_vencimiento->format('d/m/Y')
                        : null,
                ])
                ->values()
                ->all();

            $enviosFacturaEnRuta = LogisticaEnvio::query()
                ->whereNotNull('factura_id')
                ->where('estado', 'en_ruta')
                ->count();

            return [
                'credito_excedente' => $creditoExcedente,
                'vencidas' => [
                    'cuentas_vencidas' => $cuentasVencidasConReal->count(),
                    'monto_vencido' => round($cuentasVencidasConReal->sum('saldo_real'), 2),
                    'primeras_fechas' => $primeras3,
                ],
                'logistica' => [
                    'envios_factura_en_ruta' => $enviosFacturaEnRuta,
                ],
            ];
        });
    }
}
