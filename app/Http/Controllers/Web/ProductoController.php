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
    private const SESSION_SORT_KEY = 'productos_catalogo.sort';

    private const SESSION_DIR_KEY = 'productos_catalogo.dir';

    public function index(Request $request)
    {
        $search = $request->get('search');
        $categoria_id = $request->get('categoria_id');

        $allowedSort = ['codigo', 'nombre', 'precio_venta', 'stock', 'activo', 'categoria'];
        $defaultSort = 'nombre';
        $defaultDir = 'asc';

        if ($request->has('sort')) {
            $sort = $request->get('sort');
            if (! in_array($sort, $allowedSort, true)) {
                $sort = $defaultSort;
            }
            $dir = strtolower((string) $request->get('dir', 'asc')) === 'desc' ? 'desc' : 'asc';
            $request->session()->put(self::SESSION_SORT_KEY, $sort);
            $request->session()->put(self::SESSION_DIR_KEY, $dir);
        } else {
            $sort = $request->session()->get(self::SESSION_SORT_KEY, $defaultSort);
            if (! in_array($sort, $allowedSort, true)) {
                $sort = $defaultSort;
            }
            $dir = $request->session()->get(self::SESSION_DIR_KEY, $defaultDir);
            $dir = $dir === 'desc' ? 'desc' : 'asc';
        }

        $fCodigo = trim((string) $request->get('f_codigo', ''));
        $fNombre = trim((string) $request->get('f_nombre', ''));
        $fCategoriaCol = $request->get('f_categoria_col'); // '' | 'sin' | id
        $fPrecioMin = $request->get('f_precio_min');
        $fPrecioMax = $request->get('f_precio_max');
        $fStock = $request->get('f_stock', ''); // '' | na | inventario | bajo
        $fActivo = $request->get('f_activo', ''); // '' | 1 | 0

        $query = Producto::query();

        $query->when($search, function ($q) use ($search) {
            $q->buscar($search);
        });
        $query->when($categoria_id, function ($q) use ($categoria_id) {
            $q->where('categoria_id', $categoria_id);
        });

        if ($fCodigo !== '') {
            $query->where('codigo', 'like', '%' . $fCodigo . '%');
        }
        if ($fNombre !== '') {
            $query->where(function ($q) use ($fNombre) {
                $q->where('nombre', 'like', '%' . $fNombre . '%')
                    ->orWhere('descripcion', 'like', '%' . $fNombre . '%');
            });
        }
        if ($fCategoriaCol === 'sin') {
            $query->whereNull('categoria_id');
        } elseif ($fCategoriaCol !== '' && $fCategoriaCol !== null && is_numeric($fCategoriaCol)) {
            $query->where('categoria_id', (int) $fCategoriaCol);
        }
        if ($fPrecioMin !== '' && $fPrecioMin !== null && is_numeric($fPrecioMin)) {
            $query->where('precio_venta', '>=', (float) $fPrecioMin);
        }
        if ($fPrecioMax !== '' && $fPrecioMax !== null && is_numeric($fPrecioMax)) {
            $query->where('precio_venta', '<=', (float) $fPrecioMax);
        }
        if ($fStock === 'na') {
            $query->where('controla_inventario', false);
        } elseif ($fStock === 'inventario') {
            $query->where('controla_inventario', true);
        } elseif ($fStock === 'bajo') {
            $query->where('controla_inventario', true)
                ->whereColumn('stock', '<=', 'stock_minimo');
        }
        if ($fActivo === '1' || $fActivo === '0') {
            $query->where('activo', (bool) (int) $fActivo);
        }

        if ($sort === 'categoria') {
            $query->leftJoin('categorias_productos as cat_sort', 'productos.categoria_id', '=', 'cat_sort.id')
                ->select('productos.*')
                ->orderByRaw('COALESCE(cat_sort.nombre, \'\') ' . $dir);
        } else {
            $query->orderBy($sort, $dir);
        }

        $appends = array_merge($request->except('page'), [
            'sort' => $sort,
            'dir' => $dir,
        ]);
        $productos = $query->with('categoria')->paginate(20)->appends($appends);

        $categorias = CategoriaProducto::activas()->orderBy('nombre')->get();

        $hayFiltrosColumna = $fCodigo !== '' || $fNombre !== '' || ($fCategoriaCol !== '' && $fCategoriaCol !== null)
            || ($fPrecioMin !== '' && $fPrecioMin !== null) || ($fPrecioMax !== '' && $fPrecioMax !== null)
            || ($fStock !== '' && $fStock !== null) || ($fActivo === '1' || $fActivo === '0');
        $hayFiltros = $search || $categoria_id || $hayFiltrosColumna;
        $mostrarTablaFiltros = $productos->isNotEmpty() || $hayFiltros || Producto::exists();

        return view('productos.index', compact(
            'productos',
            'categorias',
            'search',
            'categoria_id',
            'sort',
            'dir',
            'fCodigo',
            'fNombre',
            'fCategoriaCol',
            'fPrecioMin',
            'fPrecioMax',
            'fStock',
            'fActivo',
            'hayFiltros',
            'mostrarTablaFiltros'
        ));
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