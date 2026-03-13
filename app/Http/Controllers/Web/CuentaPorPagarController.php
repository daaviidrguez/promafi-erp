<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CuentaPorPagar;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CuentaPorPagarController extends Controller
{
    public function index(Request $request)
    {
        $estado = $request->get('estado');
        $proveedor_id = $request->get('proveedor_id');

        $cuentas = CuentaPorPagar::with(['proveedor', 'ordenCompra', 'facturaCompra'])
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
        $cuentaPorPagar->load(['proveedor', 'ordenCompra.detalles', 'facturaCompra.detalles.impuestos']);
        return view('cuentas-por-pagar.show', compact('cuentaPorPagar'));
    }

    public function registrarPago(Request $request, CuentaPorPagar $cuentaPorPagar)
    {
        $validated = $request->validate([
            'monto' => 'required|numeric|min:0.01|max:' . (float) $cuentaPorPagar->monto_pendiente,
            'fecha_pago' => 'required|date',
            'referencia' => 'nullable|string|max:100',
            'notas' => 'nullable|string',
            'comprobante_pago' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        DB::beginTransaction();
        try {
            $cuentaPorPagar->registrarPago($validated['monto']);

            if ($request->hasFile('comprobante_pago')) {
                $dir = storage_path('app/cuentas-por-pagar/comprobantes/' . $cuentaPorPagar->id);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if ($cuentaPorPagar->comprobante_pago_path) {
                    $oldPath = storage_path('app/' . $cuentaPorPagar->comprobante_pago_path);
                    if (file_exists($oldPath)) {
                        unlink($oldPath);
                    }
                }
                $file = $request->file('comprobante_pago');
                $filename = $file->hashName();
                $file->move($dir, $filename);
                $path = 'cuentas-por-pagar/comprobantes/' . $cuentaPorPagar->id . '/' . $filename;
                $cuentaPorPagar->update(['comprobante_pago_path' => $path]);
            }
            DB::commit();
            return redirect()->route('cuentas-por-pagar.show', $cuentaPorPagar->id)->with('success', 'Pago registrado');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    public function verComprobante(CuentaPorPagar $cuentaPorPagar)
    {
        if (!$cuentaPorPagar->comprobante_pago_path) {
            abort(404, 'No hay comprobante de pago registrado');
        }
        $path = Storage::disk('local')->path($cuentaPorPagar->comprobante_pago_path);
        if (!file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }
        $mime = mime_content_type($path);
        return response()->file($path, ['Content-Type' => $mime]);
    }

    public function descargarComprobante(CuentaPorPagar $cuentaPorPagar)
    {
        if (!$cuentaPorPagar->comprobante_pago_path) {
            abort(404, 'No hay comprobante de pago registrado');
        }
        $path = Storage::disk('local')->path($cuentaPorPagar->comprobante_pago_path);
        if (!file_exists($path)) {
            abort(404, 'Archivo no encontrado');
        }
        $nombre = 'Comprobante_pago_' . preg_replace('/[^a-zA-Z0-9\-_]/', '_', optional($cuentaPorPagar->facturaCompra)->folio_completo ?? optional($cuentaPorPagar->ordenCompra)->folio ?? $cuentaPorPagar->id);
        return response()->download($path, $nombre . '.' . pathinfo($path, PATHINFO_EXTENSION));
    }
}
