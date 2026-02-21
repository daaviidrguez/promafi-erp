<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CuentaPorCobrarController.php

use App\Http\Controllers\Controller;
use App\Models\CuentaPorCobrar;
use App\Models\Cliente;
use App\Models\FormaPago;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaPorCobrarController extends Controller
{
    /**
     * Listado de cuentas por cobrar
     */
    public function index(Request $request)
    {
        $estado = $request->get('estado');
        $cliente_id = $request->get('cliente_id');
        
        $cuentas = CuentaPorCobrar::with(['cliente', 'factura'])
            ->when($estado, function($query) use ($estado) {
                if ($estado === 'vencidas') {
                    $query->vencidas();
                } else {
                    $query->where('estado', $estado);
                }
            })
            ->when($cliente_id, function($query) use ($cliente_id) {
                $query->where('cliente_id', $cliente_id);
            })
            ->orderBy('fecha_vencimiento', 'asc')
            ->paginate(20);

        // Calcular totales
        $totales = [
            'pendiente' => CuentaPorCobrar::pendientes()->sum('monto_pendiente'),
            'vencido' => CuentaPorCobrar::vencidas()->sum('monto_pendiente'),
            'pagado' => CuentaPorCobrar::where('estado', 'pagada')->sum('monto_pagado'),
        ];

        $clientes = Cliente::activos()->orderBy('nombre')->get();

        return view('cuentas-cobrar.index', compact('cuentas', 'totales', 'clientes', 'estado', 'cliente_id'));
    }

    /**
     * Ver detalle de cuenta
     */
    public function show(CuentaPorCobrar $cuentaPorCobrar)
    {
        $cuentaPorCobrar->load(['cliente', 'factura.detalles']);
        $formasPago = FormaPago::activos()->get();
        return view('cuentas-cobrar.show', compact('cuentaPorCobrar', 'formasPago'));
    }

    /**
     * Registrar pago
     */
    public function registrarPago(Request $request, CuentaPorCobrar $cuentaPorCobrar)
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01|max:' . $cuentaPorCobrar->monto_pendiente,
            'fecha_pago' => 'required|date',
            'forma_pago' => 'required|string|exists:formas_pago,clave',
            'referencia' => 'nullable|string|max:100',
            'notas' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            // Registrar el pago
            $cuentaPorCobrar->registrarPago($validated['monto']);

            // TODO: Aquí podrías crear un registro de pago
            // para llevar el historial completo

            DB::commit();

            return redirect()->route('cuentas-cobrar.show', $cuentaPorCobrar->id)
                ->with('success', 'Pago registrado exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al registrar pago: ' . $e->getMessage());
        }
    }
}