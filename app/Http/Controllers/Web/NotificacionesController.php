<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\CuentaPorCobrar;
use App\Models\NotaCredito;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificacionesController extends Controller
{
    /**
     * Notificaciones visibles solo para administración (protegido por permiso).
     */
    public function admin(Request $request)
    {
        $hoy = Carbon::today();

        // ───────── 1) Crédito excedente ─────────
        // Tomamos el cliente con mayor (saldo_actual - limite_credito) usando saldo_actual en DB
        // para escoger candidatos, y para el valor final usamos saldo_actual_coherente (igual que la tarjeta del cliente).
        $creditoExcedente = null;

        $candidatos = Cliente::query()
            ->where('activo', true)
            ->where('dias_credito', '>', 0)
            ->whereNotNull('limite_credito')
            ->orderByRaw('(saldo_actual - limite_credito) DESC')
            ->limit(5)
            ->get();

        $topExcedente = 0.0;
        $topCliente = null;

        foreach ($candidatos as $cliente) {
            $saldoCoherente = (float) $cliente->saldo_actual_coherente;
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

        // ───────── 2) Cuentas vencidas ─────────
        $basesVencidas = CuentaPorCobrar::query()
            ->with(['factura', 'cliente'])
            ->excluirFacturaBorrador()
            ->vencidas()
            ->orderBy('fecha_vencimiento', 'asc')
            ->get(['id', 'factura_id', 'cliente_id', 'fecha_vencimiento', 'monto_pendiente']);

        $facturaIds = $basesVencidas->pluck('factura_id')->unique()->values();

        // Nota de crédito timbrada acumulada por factura para calcular saldo_pendiente_real SIN N+1.
        $ncPorFactura = NotaCredito::query()
            ->whereIn('factura_id', $facturaIds)
            ->where('estado', 'timbrada')
            ->groupBy('factura_id')
            ->selectRaw('factura_id, SUM(total) as total_nc')
            ->pluck('total_nc', 'factura_id');

        $cuentasVencidasConReal = $basesVencidas
            ->map(function ($cuenta) use ($ncPorFactura) {
                $totalNc = (float) ($ncPorFactura[$cuenta->factura_id] ?? 0);
                $saldoReal = max(0.0, (float) $cuenta->monto_pendiente - $totalNc);

                return [
                    'cuenta' => $cuenta,
                    'saldo_real' => $saldoReal,
                ];
            })
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

        // El admin debe ver la notificación cada vez que entra al sistema.
        $tieneAlguna = true;

        return response()->json([
            'has_notifications' => $tieneAlguna,
            'credito_excedente' => $creditoExcedente,
            'vencidas' => [
                'cuentas_vencidas' => $cuentasVencidasCount,
                'monto_vencido' => $montoVencido,
                'primeras_fechas' => $primeras3,
                'hoy' => $hoy->format('d/m/Y'),
            ],
        ]);
    }
}

