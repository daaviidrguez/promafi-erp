<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\InventarioMovimiento;
use App\Models\Producto;
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

    public function movimientos(Request $request)
    {
        $productoId = $request->get('producto_id');
        $tipo = $request->get('tipo');
        $query = InventarioMovimiento::with(['producto', 'usuario', 'factura', 'remision', 'ordenCompra'])
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
        $movimientos = $producto->movimientos()->with(['usuario', 'factura', 'remision', 'ordenCompra'])->paginate(20);
        return view('inventario.show-producto', compact('producto', 'movimientos'));
    }
}
