<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
use App\Services\PDFService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InventarioController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $bajoStock = $request->boolean('bajo_stock');
        $query = Producto::where('controla_inventario', true)
            ->when($search, fn ($q) => $q->buscar($search))
            ->orderBy('nombre');
        if ($bajoStock) {
            $query->bajoStock();
        }
        $productos = $query->paginate(20);
        return view('inventario.index', compact('productos', 'search', 'bajoStock'));
    }

    public function showMovimiento(InventarioMovimiento $movimiento)
    {
        $movimiento->load(['producto', 'usuario', 'factura', 'remision', 'ordenCompra', 'facturaCompra']);
        return view('inventario.show-movimiento', compact('movimiento'));
    }

    public function movimientos(Request $request)
    {
        $productoId = $request->get('producto_id');
        $tipo = $request->get('tipo');
        $query = InventarioMovimiento::with(['producto', 'usuario', 'factura', 'remision', 'ordenCompra', 'facturaCompra'])
            ->when($productoId, fn ($q) => $q->where('producto_id', $productoId))
            ->when($tipo, fn ($q) => $q->where('tipo', $tipo))
            ->orderByDesc('created_at');
        $movimientos = $query->paginate(25);
        $productos = Producto::where('controla_inventario', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']);
        return view('inventario.movimientos', compact('movimientos', 'productos', 'productoId', 'tipo'));
    }

    public function createMovimiento(Request $request)
    {
        $productos = Producto::where('controla_inventario', true)->orderBy('nombre')->get();
        $productoId = $request->get('producto_id');
        return view('inventario.create-movimiento', compact('productos', 'productoId'));
    }

    public function storeMovimiento(Request $request)
    {
        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'tipo' => 'required|in:entrada_manual,salida_manual',
            'cantidad' => 'required|numeric|min:0.01',
            'observaciones' => 'nullable|string|max:500',
        ]);
        $producto = Producto::findOrFail($validated['producto_id']);
        if (!$producto->controla_inventario) {
            return back()->with('error', 'El producto no controla inventario');
        }
        try {
            InventarioMovimiento::registrar(
                $producto,
                $validated['tipo'],
                (float) $validated['cantidad'],
                auth()->id(),
                null,
                null,
                null,
                null,
                $validated['observaciones'] ?? null
            );
            $mensaje = $validated['tipo'] === 'entrada_manual' ? 'Entrada de inventario registrada' : 'Salida de inventario registrada';
            return redirect()->route('inventario.movimientos')->with('success', $mensaje);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function showProducto(Producto $producto)
    {
        if (!$producto->controla_inventario) {
            return redirect()->route('inventario.index')->with('error', 'El producto no controla inventario');
        }
        $producto->load('categoria');
        $movimientos = $producto->movimientos()->with(['usuario', 'factura', 'remision', 'ordenCompra', 'facturaCompra'])->paginate(20);
        return view('inventario.show-producto', compact('producto', 'movimientos'));
    }

    /**
     * Kardex: formulario de búsqueda por producto y rango de fechas; opcionalmente muestra resultados.
     */
    public function kardex(Request $request)
    {
        $productos = Producto::where('controla_inventario', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']);
        $productoId = $request->get('producto_id');
        $fechaDesde = $request->get('fecha_desde');
        $fechaHasta = $request->get('fecha_hasta');

        $producto = null;
        $movimientos = collect();
        $saldoInicial = 0.0;

        if ($productoId && $fechaDesde && $fechaHasta) {
            $producto = Producto::where('controla_inventario', true)->find($productoId);
            if (!$producto) {
                return redirect()->route('inventario.kardex')->with('error', 'Producto no encontrado o no controla inventario');
            }
            $desde = Carbon::parse($fechaDesde)->startOfDay();
            $hasta = Carbon::parse($fechaHasta)->endOfDay();
            $movimientos = InventarioMovimiento::where('producto_id', $producto->id)
                ->whereBetween('created_at', [$desde, $hasta])
                ->with(['usuario', 'factura', 'remision', 'ordenCompra', 'facturaCompra'])
                ->orderBy('created_at')
                ->get();
            if ($movimientos->isNotEmpty()) {
                $saldoInicial = (float) $movimientos->first()->stock_anterior;
            } else {
                $ultimoAntes = InventarioMovimiento::where('producto_id', $producto->id)
                    ->where('created_at', '<', $desde)
                    ->orderByDesc('created_at')
                    ->first();
                $saldoInicial = $ultimoAntes ? (float) $ultimoAntes->stock_resultante : (float) 0;
            }
        }

        return view('inventario.kardex', compact('productos', 'producto', 'movimientos', 'saldoInicial', 'fechaDesde', 'fechaHasta', 'productoId'));
    }

    /**
     * Descargar Kardex en PDF.
     */
    public function descargarKardexPdf(Request $request)
    {
        $request->validate([
            'producto_id' => 'required|exists:productos,id',
            'fecha_desde' => 'required|date',
            'fecha_hasta' => 'required|date|after_or_equal:fecha_desde',
        ]);
        $producto = Producto::where('controla_inventario', true)->findOrFail($request->producto_id);
        $fechaDesde = Carbon::parse($request->fecha_desde)->startOfDay();
        $fechaHasta = Carbon::parse($request->fecha_hasta)->endOfDay();
        $movimientos = InventarioMovimiento::where('producto_id', $producto->id)
            ->whereBetween('created_at', [$fechaDesde, $fechaHasta])
            ->with(['usuario', 'factura', 'remision', 'ordenCompra', 'facturaCompra'])
            ->orderBy('created_at')
            ->get();
        $saldoInicial = 0.0;
        if ($movimientos->isNotEmpty()) {
            $saldoInicial = (float) $movimientos->first()->stock_anterior;
        } else {
            $ultimoAntes = InventarioMovimiento::where('producto_id', $producto->id)
                ->where('created_at', '<', $fechaDesde)
                ->orderByDesc('created_at')
                ->first();
            $saldoInicial = $ultimoAntes ? (float) $ultimoAntes->stock_resultante : 0;
        }

        $pdfPath = app(PDFService::class)->generarKardexPDF($producto, $movimientos, $fechaDesde, $fechaHasta, $saldoInicial);
        $filename = 'Kardex_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $producto->codigo) . '_' . $fechaDesde->format('Y-m-d') . '_' . $fechaHasta->format('Y-m-d') . '.pdf';
        return response()->download(storage_path('app/' . $pdfPath), $filename);
    }
}
