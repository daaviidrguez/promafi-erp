<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CuentaPorPagar;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CuentaPorPagarController extends Controller
{
    public function index(Request $request)
    {
        $estado = $request->get('estado');
        $proveedor_id = $request->get('proveedor_id');

        $cuentas = CuentaPorPagar::with(['proveedor', 'ordenCompra'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($proveedor_id, fn ($q) => $q->where('proveedor_id', $proveedor_id))
            ->orderBy('fecha_vencimiento', 'asc')
            ->paginate(20);

        $totales = [
            'pendiente' => CuentaPorPagar::pendientes()->sum('monto_pendiente'),
            'pagado' => CuentaPorPagar::where('estado', 'pagada')->sum('monto_pagado'),
        ];

        $proveedores = Proveedor::activos()->orderBy('nombre')->get();

        return view('cuentas-por-pagar.index', compact('cuentas', 'totales', 'proveedores', 'estado', 'proveedor_id'));
    }

    public function show(CuentaPorPagar $cuentaPorPagar)
    {
        $cuentaPorPagar->load(['proveedor', 'ordenCompra.detalles']);
        return view('cuentas-por-pagar.show', compact('cuentaPorPagar'));
    }

    public function registrarPago(Request $request, CuentaPorPagar $cuentaPorPagar)
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01|max:' . (float) $cuentaPorPagar->monto_pendiente,
            'fecha_pago' => 'required|date',
            'referencia' => 'nullable|string|max:100',
            'notas' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $cuentaPorPagar->registrarPago($validated['monto']);
            DB::commit();
            return redirect()->route('cuentas-por-pagar.show', $cuentaPorPagar->id)->with('success', 'Pago registrado');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }
}
