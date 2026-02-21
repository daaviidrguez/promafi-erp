<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/ProductoController.php

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\CategoriaProducto;
use App\Models\ClaveProdServicio;
use App\Models\UnidadMedidaSat;
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
        $unidadesMedida = UnidadMedidaSat::activos()->get();
        $claveSatDefault = old('clave_sat', '01010101');
        $catalogoCreate = ClaveProdServicio::where('clave', $claveSatDefault)->first();
        $claveSatEtiqueta = $catalogoCreate ? $catalogoCreate->etiqueta : $claveSatDefault;
        return view('productos.create', compact('categorias', 'unidadesMedida', 'claveSatEtiqueta', 'claveSatDefault'));
    }

    /**
     * Búsqueda de claves producto/servicio SAT para autocompletado (mín. 2 caracteres).
     */
    public function buscarClaveSat(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }
        $items = ClaveProdServicio::activos()
            ->where(function ($query) use ($q) {
                $query->where('clave', 'like', $q . '%')
                    ->orWhere('descripcion', 'like', '%' . $q . '%');
            })
            ->orderBy('clave')
            ->limit(15)
            ->get(['id', 'clave', 'descripcion']);
        return response()->json($items);
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
            'objeto_impuesto' => 'required|in:01,02,03',
            'tipo_impuesto' => 'nullable|string|max:3',
            'tipo_factor' => 'required|in:Tasa,Exento',
            'tasa_iva' => 'required|numeric|min:0|max:1',
            'precio_venta' => 'required|numeric|min:0',
            'costo' => 'nullable|numeric|min:0',
            'stock_minimo' => 'nullable|numeric|min:0',
            'stock_maximo' => 'nullable|numeric|min:0',
            'controla_inventario' => 'boolean',
            'aplica_iva' => 'boolean',
        ]);

        $validated['activo'] = true;
        $validated['tipo_impuesto'] = $validated['tipo_impuesto'] ?? '002';
        $validated['aplica_iva'] = ($validated['tipo_factor'] ?? 'Tasa') !== 'Exento';
        $validated['stock'] = 0; // Stock se gestiona desde el módulo Inventario

        $producto = Producto::create($validated);

        return redirect()->route('productos.show', $producto->id)
            ->with('success', 'Producto creado exitosamente');
    }

    public function show(Producto $producto)
    {
        $producto->load('categoria');
        $catalogo = ClaveProdServicio::where('clave', $producto->clave_sat)->first();
        $claveSatEtiqueta = $catalogo ? $catalogo->etiqueta : $producto->clave_sat;
        return view('productos.show', compact('producto', 'claveSatEtiqueta'));
    }

    public function edit(Producto $producto)
    {
        $categorias = CategoriaProducto::activas()->orderBy('nombre')->get();
        $unidadesMedida = UnidadMedidaSat::activos()->get();
        $claveSat = old('clave_sat', $producto->clave_sat);
        $catalogo = ClaveProdServicio::where('clave', $claveSat)->first();
        $claveSatEtiqueta = $catalogo ? $catalogo->etiqueta : $claveSat;
        return view('productos.edit', compact('producto', 'categorias', 'unidadesMedida', 'claveSatEtiqueta', 'claveSat'));
    }

    public function update(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'codigo' => 'required|string|max:50|unique:productos,codigo,' . $producto->id,
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'categoria_id' => 'nullable|exists:categorias_productos,id',
            'clave_sat' => 'required|string|max:8',
            'clave_unidad_sat' => 'required|string|max:3',
            'unidad' => 'required|string|max:20',
            'objeto_impuesto' => 'required|in:01,02,03',
            'tipo_impuesto' => 'nullable|string|max:3',
            'tipo_factor' => 'required|in:Tasa,Exento',
            'tasa_iva' => 'required|numeric|min:0|max:1',
            'precio_venta' => 'required|numeric|min:0',
            'costo' => 'nullable|numeric|min:0',
            'stock_minimo' => 'nullable|numeric|min:0',
            'stock_maximo' => 'nullable|numeric|min:0',
            'controla_inventario' => 'boolean',
            'aplica_iva' => 'boolean',
            'activo' => 'boolean',
        ]);

        $validated['tipo_impuesto'] = $validated['tipo_impuesto'] ?? '002';
        $validated['aplica_iva'] = ($validated['tipo_factor'] ?? 'Tasa') !== 'Exento';
        unset($validated['stock']); // Stock solo se modifica desde Inventario
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