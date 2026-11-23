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
    /**
     * Notificaciones visibles solo para administración (protegido por permiso).
     */
    public function admin(Request $request)
    {
        $hoy = Carbon::today();
        $user = $request->user();
        $uid = $user?->id ?? 'anon';

        // Datos pesados: caché corta (misma UX; reduce presión en cada navegación).
        $data = Cache::remember("notifs_admin_data_{$uid}", 45, function () {
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
                ->with(['factura', 'cliente'])
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

            $cuentasVencidasCount = $cuentasVencidasConReal->count();
            $montoVencido = round($cuentasVencidasConReal->sum('saldo_real'), 2);

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
                    'cuentas_vencidas' => $cuentasVencidasCount,
                    'monto_vencido' => $montoVencido,
                    'primeras_fechas' => $primeras3,
                ],
                'logistica' => [
                    'envios_factura_en_ruta' => $enviosFacturaEnRuta,
                ],
            ];
        });

        $creditoExcedente = $data['credito_excedente'];
        $cuentasVencidasCount = (int) ($data['vencidas']['cuentas_vencidas'] ?? 0);
        $enviosFacturaEnRuta = (int) ($data['logistica']['envios_factura_en_ruta'] ?? 0);
        $tieneAlguna = (bool) ($creditoExcedente || $cuentasVencidasCount > 0 || $enviosFacturaEnRuta > 0);

        // Toast / leído: siempre por sesión (fuera del caché de datos).
        $toastKey = "notifs_admin_toast_shown_{$uid}";
        $readKey = "notifs_admin_read_{$uid}";

        $toastShown = (bool) $request->session()->get($toastKey, false);
        $alreadyRead = (bool) $request->session()->get($readKey, false);

        $showToast = $tieneAlguna && ! $toastShown && ! $alreadyRead;
        $unreadCount = $tieneAlguna && ! $alreadyRead ? 1 : 0;

        if ($showToast) {
            $request->session()->put($toastKey, true);
        }

        return response()->json([
            'has_notifications' => $tieneAlguna,
            'show_toast' => $showToast,
            'unread_count' => $unreadCount,
            'credito_excedente' => $creditoExcedente,
            'vencidas' => [
                'cuentas_vencidas' => $cuentasVencidasCount,
                'monto_vencido' => $data['vencidas']['monto_vencido'] ?? 0,
                'primeras_fechas' => $data['vencidas']['primeras_fechas'] ?? [],
                'hoy' => $hoy->format('d/m/Y'),
                'url' => $user?->can('cuentas_cobrar.ver')
                    ? route('cuentas-cobrar.index', ['estado' => 'vencidas'])
                    : null,
            ],
            'logistica' => [
                'envios_factura_en_ruta' => $enviosFacturaEnRuta,
                'url' => $user?->can('logistica.ver') ? route('logistica.index', ['estado' => 'en_ruta']) : null,
            ],
        ]);
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
}
