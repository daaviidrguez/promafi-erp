<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Devolucion;
use App\Models\DevolucionDetalle;
use App\Models\Factura;
use App\Models\Cliente;
use App\Models\Empresa;
use Illuminate\Http\Request;

class DevolucionController extends Controller
{
    public function index(Request $request)
    {
        $estado = $request->get('estado');
        $factura_id = $request->get('factura_id');

        $devoluciones = Devolucion::with(['factura', 'cliente', 'usuario'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($factura_id, fn ($q) => $q->where('factura_id', $factura_id))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('devoluciones.index', compact('devoluciones', 'estado', 'factura_id'));
    }

    public function create(Request $request)
    {
        $factura_id = $request->get('factura_id');
        if (!$factura_id) {
            return redirect()->route('devoluciones.index')->with('error', 'Indica la factura para registrar la devolución.');
        }

        $factura = Factura::with(['detalles.producto', 'detalles.impuestos', 'cliente'])->findOrFail($factura_id);
        if (!$factura->estaTimbrada()) {
            return redirect()->route('facturas.show', $factura->id)->with('error', 'Solo se pueden registrar devoluciones de facturas timbradas.');
        }

        // Cantidades ya devueltas por factura_detalle_id (para límite y visibilidad)
        $cantidadesDevueltas = DevolucionDetalle::whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
            ->whereHas('devolucion', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', '!=', 'cancelada'))
            ->selectRaw('factura_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
            ->groupBy('factura_detalle_id')
            ->pluck('total_devuelto', 'factura_detalle_id')
            ->map(fn ($v) => (float) $v);

        $devolucionesAnteriores = Devolucion::where('factura_id', $factura->id)
            ->with('detalles.facturaDetalle')
            ->orderBy('fecha_devolucion', 'desc')
            ->get();

        return view('devoluciones.create', compact('factura', 'cantidadesDevueltas', 'devolucionesAnteriores'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'factura_id' => 'required|exists:facturas,id',
            'fecha_devolucion' => 'required|date',
            'motivo' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string',
            'lineas' => 'required|array|min:1',
            'lineas.*.factura_detalle_id' => 'required|exists:facturas_detalle,id',
            'lineas.*.cantidad_devuelta' => 'required|numeric|min:0',
            'lineas.*.motivo_linea' => 'nullable|string|max:255',
        ]);

        $factura = Factura::with('detalles')->findOrFail($validated['factura_id']);
        if (!$factura->estaTimbrada()) {
            return back()->withInput()->with('error', 'La factura debe estar timbrada.');
        }

        $cantidadesDevueltas = DevolucionDetalle::whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
            ->whereHas('devolucion', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', '!=', 'cancelada'))
            ->selectRaw('factura_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
            ->groupBy('factura_detalle_id')
            ->pluck('total_devuelto', 'factura_detalle_id')
            ->map(fn ($v) => (float) $v);

        $empresa = Empresa::principal();
        $devolucion = Devolucion::create([
            'factura_id' => $factura->id,
            'cliente_id' => $factura->cliente_id,
            'empresa_id' => $empresa?->id,
            'fecha_devolucion' => $validated['fecha_devolucion'],
            'motivo' => $validated['motivo'] ?? null,
            'estado' => 'borrador',
            'observaciones' => $validated['observaciones'] ?? null,
            'usuario_id' => auth()->id(),
        ]);

        foreach ($validated['lineas'] as $lin) {
            $cant = (float) $lin['cantidad_devuelta'];
            if ($cant < 0.01) {
                continue;
            }
            $fd = $factura->detalles->firstWhere('id', $lin['factura_detalle_id']);
            if (!$fd) {
                continue;
            }
            $yaDevuelto = $cantidadesDevueltas->get($fd->id, 0);
            $cantPendiente = (float) $fd->cantidad - $yaDevuelto;
            if ($cant > $cantPendiente) {
                continue;
            }
            DevolucionDetalle::create([
                'devolucion_id' => $devolucion->id,
                'factura_detalle_id' => $fd->id,
                'producto_id' => $fd->producto_id,
                'cantidad_devuelta' => $cant,
                'motivo_linea' => $lin['motivo_linea'] ?? null,
            ]);
        }

        if ($devolucion->detalles()->count() === 0) {
            $devolucion->delete();
            return back()->withInput()->with('error', 'Debes indicar al menos una línea con cantidad devuelta mayor a 0.');
        }

        return redirect()->route('devoluciones.show', $devolucion->id)
            ->with('success', 'Devolución registrada. Puedes autorizarla y luego generar la nota de crédito.');
    }

    public function show(Devolucion $devolucion)
    {
        $devolucion->load(['factura.detalles.producto', 'cliente', 'detalles.facturaDetalle.producto', 'usuario', 'notasCredito']);
        return view('devoluciones.show', compact('devolucion'));
    }

    public function autorizar(Devolucion $devolucion)
    {
        if ($devolucion->estado !== 'borrador') {
            return back()->with('error', 'Solo se pueden autorizar devoluciones en borrador.');
        }
        $devolucion->update(['estado' => 'autorizada']);
        return back()->with('success', 'Devolución autorizada. Ya puedes generar la nota de crédito.');
    }

    public function cancelar(Devolucion $devolucion)
    {
        $devolucion->loadMissing('notasCredito');

        if (! $devolucion->puedeCancelar()) {
            return back()->with('error', 'Solo se pueden cancelar devoluciones autorizadas sin nota de crédito generada.');
        }

        $devolucion->update(['estado' => 'cancelada']);

        return back()->with('success', 'Devolución cancelada correctamente. Se liberó la factura para su flujo de cancelación.');
    }
}
