<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CuentaPorCobrarController.php

use App\Http\Controllers\Controller;
use App\Models\CuentaPorCobrar;
use App\Models\Cliente;
use App\Models\FormaPago;
use App\Services\CobranzaResumenService;
use Illuminate\Http\Request;

class CuentaPorCobrarController extends Controller
{
    /**
     * Listado de cuentas por cobrar
     */
    public function index(Request $request, CobranzaResumenService $resumen)
    {
        $estado = $request->filled('estado') ? $request->get('estado') : 'vencidas';
        $cliente_id = $request->get('cliente_id');
        
        $facturaCols = \App\Support\CfdiListadoColumnas::facturaRelacion();
        $baseQuery = CuentaPorCobrar::with([
            'cliente:id,nombre,rfc',
            'factura' => fn ($q) => $q->select($facturaCols),
        ])
            ->excluirFacturaBorrador()
            ->when($estado !== 'todos', function($query) use ($estado) {
                if ($estado === 'vencidas') {
                    // Igual que la tarjeta "Cartera vencida": fuera las cubiertas por NC timbradas
                    $query->vencidas()->whereRaw(
                        "cuentas_por_cobrar.monto_pendiente > COALESCE((SELECT SUM(nc.total) FROM notas_credito nc WHERE nc.factura_id = cuentas_por_cobrar.factura_id AND nc.estado = 'timbrada'), 0)"
                    );
                } elseif ($estado === 'pendiente') {
                    // Misma noción que el badge "Pendiente": sin vencer por fecha (no mezclar con vencidas)
                    $query->pendienteAlCorriente();
                } else {
                    $query->where('estado', $estado);
                }
            })
            ->when($cliente_id, function($query) use ($cliente_id) {
                $query->where('cliente_id', $cliente_id);
            });

        $cuentas = (clone $baseQuery)->orderBy('fecha_vencimiento', 'asc')->paginate(20);

        // Totales coherentes con saldo_pendiente_real (factura show, complemento, dashboard)
        $cuentasParaTotales = CuentaPorCobrar::query()
            ->excluirFacturaBorrador()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->get(['id', 'factura_id', 'monto_pendiente', 'estado', 'fecha_vencimiento', 'monto_pagado']);
        \App\Support\CobranzaSaldoBatch::hidratarCuentas($cuentasParaTotales);
        $basesVencidas = CuentaPorCobrar::query()
            ->excluirFacturaBorrador()
            ->vencidas()
            ->get(['id', 'factura_id', 'monto_pendiente']);
        \App\Support\CobranzaSaldoBatch::hidratarCuentas($basesVencidas);
        $totales = [
            'pendiente' => $cuentasParaTotales->sum(fn ($c) => $c->saldo_pendiente_real),
            // Mismo criterio que Notificaciones: scopeVencidas + saldo real (monto - NC)
            'vencido' => (float) $basesVencidas->sum(fn ($c) => $c->saldo_pendiente_real),
        ];

        $mes = (int) $request->get('mes', now()->month);
        $mes = ($mes >= 1 && $mes <= 12) ? $mes : now()->month;
        $anio = (int) $request->get('anio', now()->year);
        $anio = ($anio >= 2020 && $anio <= 2035) ? $anio : now()->year;

        $cobradoMes = $resumen->cobradoMes($mes, $anio);
        $vencidoPorMes = $resumen->vencidoPorMes($anio);
        $totales['pagado'] = $cobradoMes['total_cobrado'];

        $clientes = Cliente::activos()->orderBy('nombre')->get();

        return view('cuentas-cobrar.index', compact(
            'cuentas', 'totales', 'clientes', 'estado', 'cliente_id',
            'mes', 'anio', 'cobradoMes', 'vencidoPorMes'
        ));
    }

    /**
     * Ver detalle de cuenta
     */
    public function show(CuentaPorCobrar $cuentaPorCobrar)
    {
        $cuentaPorCobrar->load([
            'cliente',
            'factura.detalles',
            'factura.documentosRelacionadosPago.pagoRecibido.complementoPago',
        ]);
        $formasPago = FormaPago::activos()->get();
        $complementoBorrador = \App\Models\ComplementoPago::where('cliente_id', $cuentaPorCobrar->cliente_id)->where('estado', 'borrador')->first();
        return view('cuentas-cobrar.show', compact('cuentaPorCobrar', 'formasPago', 'complementoBorrador'));
    }
}