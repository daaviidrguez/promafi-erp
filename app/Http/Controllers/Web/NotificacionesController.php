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

class NotificacionesController extends Controller
{
    private const CACHE_KEY = 'notifs_admin_data_shared';

    private const CACHE_TTL_SECONDS = 120;

    /**
     * Resumen ligero para cada navegación: badge + toast.
     * El detalle pesado vive en caché compartida; el dropdown lo pide en admin().
     */
    public function resumen(Request $request)
    {
        $user = $request->user();
        $uid = $user?->id ?? 'anon';
        $data = $this->datosCompartidos();
        $flags = $this->flagsSesion($request, $uid, $data);

        $payload = [
            'has_notifications' => $flags['tiene_alguna'],
            'show_toast' => $flags['show_toast'],
            'unread_count' => $flags['unread_count'],
        ];

        // Solo en el toast de entrada enviamos el detalle (evita 2º payload pesado en cada página).
        if ($flags['show_toast']) {
            $payload = array_merge($payload, $this->detalleParaUsuario($user, $data));
        }

        return response()->json($payload);
    }

    /**
     * Detalle completo (dropdown al abrir la campana).
     */
    public function admin(Request $request)
    {
        $user = $request->user();
        $uid = $user?->id ?? 'anon';
        $data = $this->datosCompartidos();
        $flags = $this->flagsSesion($request, $uid, $data, marcarToastSiAplica: false);

        return response()->json(array_merge([
            'has_notifications' => $flags['tiene_alguna'],
            'show_toast' => false,
            'unread_count' => $flags['unread_count'],
        ], $this->detalleParaUsuario($user, $data)));
    }

    /**
     * Marcar notificaciones de admin como leídas para esta sesión.
     */
    public function leer(Request $request)
    {
        $user = $request->user();
        $uid = $user?->id ?? 'anon';
        $request->session()->put("notifs_admin_read_{$uid}", true);

        return response()->json(['ok' => true]);
    }

    /**
     * @return array{tiene_alguna: bool, show_toast: bool, unread_count: int}
     */
    private function flagsSesion(Request $request, string $uid, array $data, bool $marcarToastSiAplica = true): array
    {
        $tieneAlguna = $this->tieneAlguna($data);

        $toastKey = "notifs_admin_toast_shown_{$uid}";
        $readKey = "notifs_admin_read_{$uid}";

        $toastShown = (bool) $request->session()->get($toastKey, false);
        $alreadyRead = (bool) $request->session()->get($readKey, false);

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
     * Datos compartidos entre admins (no dependen del usuario).
     *
     * @return array{credito_excedente: ?array, vencidas: array, logistica: array}
     */
    private function datosCompartidos(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL_SECONDS, function () {
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
