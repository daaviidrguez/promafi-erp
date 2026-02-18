<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/ProductoController.php

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\CategoriaProducto;
use Illuminate\Http\Request;

class ProductoController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $categoria_id = $request->get('categoria_id');
        
        $productos = Producto::with('categoria')
            ->when($search, function($query) use ($search) {
                $query->buscar($search);
            })
            ->when($categoria_id, function($query) use ($categoria_id) {
                $query->where('categoria_id', $categoria_id);
            })
            ->orderBy('nombre')
            ->paginate(20);

        $categorias = CategoriaProducto::activas()->orderBy('nombre')->get();

        return view('productos.index', compact('productos', 'categorias', 'search', 'categoria_id'));
    }

    public function create()
    {
        $categorias = CategoriaProducto::activas()->orderBy('nombre')->get();
        return view('productos.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50|unique:productos,codigo',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias_productos,id',
            'clave_sat' => 'required|string|max:8',
            'clave_unidad_sat' => 'required|string|max:3',
            'unidad' => 'required|string|max:20',
            'precio_venta' => 'required|numeric|min:0',
            'costo' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'stock_minimo' => 'nullable|numeric|min:0',
            'aplica_iva' => 'boolean',
        ]);

        $validated['activo'] = true;

        $producto = Producto::create($validated);

        return redirect()->route('productos.show', $producto->id)
            ->with('success', 'Producto creado exitosamente');
    }

    public function show(Producto $producto)
    {
        $producto->load('categoria');
        return view('productos.show', compact('producto'));
    }

    public function edit(Producto $producto)
    {
        $categorias = CategoriaProducto::activas()->orderBy('nombre')->get();
        return view('productos.edit', compact('producto', 'categorias'));
    }

    public function update(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50|unique:productos,codigo,' . $producto->id,
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias_productos,id',
            'clave_sat' => 'required|string|max:8',
            'precio_venta' => 'required|numeric|min:0',
            'costo' => 'nullable|numeric|min:0',
            'stock' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);

        $producto->update($validated);

        return redirect()->route('productos.show', $producto->id)
            ->with('success', 'Producto actualizado exitosamente');
    }

    public function destroy(Producto $producto)
    {
        $producto->delete();

        return redirect()->route('productos.index')
            ->with('success', 'Producto eliminado exitosamente');
    }
}