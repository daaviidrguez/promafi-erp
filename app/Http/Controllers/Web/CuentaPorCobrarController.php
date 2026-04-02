<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CuentaPorCobrarController.php

use App\Http\Controllers\Controller;
use App\Models\CuentaPorCobrar;
use App\Models\Cliente;
use App\Models\FormaPago;
use Illuminate\Http\Request;

class CuentaPorCobrarController extends Controller
{
    /**
     * Listado de cuentas por cobrar
     */
    public function index(Request $request)
    {
        $estado = $request->get('estado');
        $cliente_id = $request->get('cliente_id');
        
        $baseQuery = CuentaPorCobrar::with(['cliente', 'factura'])
            ->excluirFacturaBorrador()
            ->when($estado, function($query) use ($estado) {
                if ($estado === 'vencidas') {
                    $query->vencidas();
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
        $cuentasParaTotales = CuentaPorCobrar::with(['cliente', 'factura'])
            ->excluirFacturaBorrador()
            ->whereIn('estado', ['pendiente', 'parcial', 'vencida'])
            ->get();
        $totales = [
            'pendiente' => $cuentasParaTotales->sum(fn ($c) => $c->saldo_pendiente_real),
            // "Total Vencido" debe reflejar el mismo criterio que el badge/estado_display:
            // si el saldo real (saldo_pendiente_real) ya es 0 por NC, se considera "pagada"
            // aunque la fecha haya sido vencida.
            'vencido' => $cuentasParaTotales
                ->filter(fn ($c) => $c->estado_display === 'vencida')
                ->sum(fn ($c) => $c->saldo_pendiente_real),
            'pagado' => CuentaPorCobrar::excluirFacturaBorrador()->where('estado', 'pagada')->sum('monto_pagado'),
        ];

        $clientes = Cliente::activos()->orderBy('nombre')->get();

        return view('cuentas-cobrar.index', compact('cuentas', 'totales', 'clientes', 'estado', 'cliente_id'));
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