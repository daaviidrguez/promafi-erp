<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/ProductoController.php

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\CategoriaProducto;
use App\Models\ClaveProdServicio;
use App\Models\UnidadMedidaSat;
use App\Models\Proveedor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProductoController extends Controller
{
    private const SESSION_SORT_KEY = 'productos_catalogo.sort';

    private const SESSION_DIR_KEY = 'productos_catalogo.dir';

    private const SESSION_PSI_NEXT_NUM = 'productos_catalogo.psi_next_num';

    private function obtenerSiguientePsiNumDesde(int $desde): int
    {
        $usados = Producto::withTrashed()
            ->where('codigo', 'like', 'PSI-%')
            ->pluck('codigo')
            ->map(function ($codigo) {
                if (!is_string($codigo)) {
                    return null;
                }
                if (preg_match('/^PSI-(\d+)$/', $codigo, $m)) {
                    return (int) $m[1];
                }
                return null;
            })
            ->filter(fn ($n) => $n !== null)
            ->unique()
            ->values()
            ->all();

        $set = array_flip($usados);

        $n = max(1, $desde);
        while (isset($set[$n])) {
            $n++;
        }

        return $n;
    }

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
        $productos = $query->with(['categoria.parent'])->paginate(20)->appends($appends);

        $categorias = CategoriaProducto::with('parent')->activas()->orderBy('nombre')->get();

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

        // Precarga consecutiva PSI-N para nuevos productos.
        // Se reutiliza el mismo consecutivo si el usuario NO guarda el precargado.
        $psiNextNum = (int) session()->get(self::SESSION_PSI_NEXT_NUM, 0);
        if ($psiNextNum < 1) {
            $psiNextNum = $this->obtenerSiguientePsiNumDesde(1);
            session()->put(self::SESSION_PSI_NEXT_NUM, $psiNextNum);
        } else {
            // Si por alguna razón el candidato ya existe, brincamos al siguiente disponible.
            $codigoCandidato = 'PSI-' . $psiNextNum;
            if (Producto::where('codigo', $codigoCandidato)->exists()) {
                $psiNextNum = $this->obtenerSiguientePsiNumDesde($psiNextNum + 1);
                session()->put(self::SESSION_PSI_NEXT_NUM, $psiNextNum);
            }
        }

        $codigoConsecutivo = 'PSI-' . $psiNextNum;

        return view('productos.create', compact(
            'categorias',
            'unidadesMedida',
            'claveSatEtiqueta',
            'claveSatDefault',
            'codigoConsecutivo'
        ));
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
            'marca' => 'nullable|string|max:120',
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
        ], [
            'codigo.unique' => 'El código ya existe en el sistema.',
        ]);

        $request->validate([
            'imagenes' => 'nullable|array|max:3',
            'imagenes.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ], [
            'imagenes.max' => 'Máximo 3 imágenes.',
            'imagenes.*.image' => 'Cada archivo debe ser una imagen válida.',
            'imagenes.*.max' => 'Cada imagen no debe superar 5 MB.',
        ]);

        // Normalización: siempre guardamos el código en mayúsculas.
        $validated['codigo'] = strtoupper(trim((string) $validated['codigo']));

        // Avanza consecutivo SOLO si se guardó el código precargado.
        $psiNextNum = (int) $request->session()->get(self::SESSION_PSI_NEXT_NUM, 0);
        $psiCandidateCode = $psiNextNum > 0 ? ('PSI-' . $psiNextNum) : null;
        $codigoGuardado = (string) $validated['codigo'];

        $validated['activo'] = true;
        $validated['tipo_impuesto'] = $validated['tipo_impuesto'] ?? '002';
        $validated['aplica_iva'] = ($validated['tipo_factor'] ?? 'Tasa') !== 'Exento';
        $validated['stock'] = 0; // Stock se gestiona desde el módulo Inventario

        $producto = Producto::create($validated);

        $this->procesarImagenesEnCreacion($request, $producto);

        if ($psiCandidateCode && $codigoGuardado === $psiCandidateCode) {
            $nuevoNum = $this->obtenerSiguientePsiNumDesde($psiNextNum + 1);
            $request->session()->put(self::SESSION_PSI_NEXT_NUM, $nuevoNum);
        }

        return redirect()->route('productos.show', $producto->id)
            ->with('success', 'Producto creado exitosamente');
    }

    public function show(Producto $producto)
    {
        $producto->load(['categoria.parent', 'codigosProveedores.proveedor']);
        $catalogo = ClaveProdServicio::where('clave', $producto->clave_sat)->first();
        $claveSatEtiqueta = $catalogo ? $catalogo->etiqueta : $producto->clave_sat;
        $proveedores = Proveedor::activos()->orderBy('nombre')->get();

        return view('productos.show', compact('producto', 'claveSatEtiqueta', 'proveedores'));
    }

    public function edit(Producto $producto)
    {
        $categorias = CategoriaProducto::with('parent')->activas()->orderBy('nombre')->get();
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
            'marca' => 'nullable|string|max:120',
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
        ], [
            'codigo.unique' => 'El código ya existe en el sistema.',
        ]);

        $request->validate([
            'quitar_imagen' => 'nullable|array',
            'quitar_imagen.*' => 'integer|in:0,1,2',
            'imagenes' => 'nullable|array',
            'imagenes.*' => 'required|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ], [
            'imagenes.*.image' => 'Cada archivo debe ser una imagen válida.',
            'imagenes.*.max' => 'Cada imagen no debe superar 5 MB.',
        ]);

        $validated['tipo_impuesto'] = $validated['tipo_impuesto'] ?? '002';
        $validated['aplica_iva'] = ($validated['tipo_factor'] ?? 'Tasa') !== 'Exento';
        unset($validated['stock']); // Stock solo se modifica desde Inventario
        $producto->update($validated);

        $redirectImagenes = $this->procesarImagenesEnActualizacion($request, $producto);
        if ($redirectImagenes instanceof \Illuminate\Http\RedirectResponse) {
            return $redirectImagenes;
        }

        return redirect()->route('productos.show', $producto->id)
            ->with('success', 'Producto actualizado exitosamente');
    }

    public function destroy(Producto $producto)
    {
        if ($this->productoTieneRelacionesParaAuditoria($producto->id)) {
            return back()->with('error', 'No se puede eliminar el producto porque tiene movimientos o relaciones (ventas/facturas/compras/inventario). Por trazabilidad y auditoría debe conservarse.');
        }

        // Borrado físico SOLO si no hay trazabilidad.
        $producto->forceDelete();

        return redirect()->route('productos.index')
            ->with('success', 'Producto eliminado exitosamente');
    }

    private function productoTieneRelacionesParaAuditoria(int $productoId): bool
    {
        // Inventario (entradas/salidas)
        if (DB::table('inventario_movimientos')->where('producto_id', $productoId)->exists()) return true;

        // Ventas / facturación / documentos
        if (DB::table('facturas_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;
        if (DB::table('notas_credito_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;
        if (DB::table('devolucion_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;

        // Compras
        if (DB::table('facturas_compra_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;
        if (DB::table('ordenes_compra_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;
        if (DB::table('cotizaciones_compra_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;

        // Cotizaciones / remisiones (ventas)
        if (DB::table('cotizaciones_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;
        // Nota: en este ERP algunas instalaciones guardan el producto en `remisiones_detalle` y otras en `remisiones`.
        if (Schema::hasTable('remisiones_detalle') && DB::table('remisiones_detalle')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;
        if (DB::table('remisiones')->where('producto_id', $productoId)->whereNotNull('producto_id')->exists()) return true;

        // Listas de precios
        if (DB::table('listas_precios_detalle')->where('producto_id', $productoId)->exists()) return true;

        // Relaciones catálogo proveedor (no auditoría, pero evita huérfanos y duplicidad)
        if (DB::table('producto_proveedores')->where('producto_id', $productoId)->exists()) return true;

        return false;
    }

    private function procesarImagenesEnCreacion(Request $request, Producto $producto): void
    {
        $files = array_values(array_filter($request->file('imagenes', []) ?: []));
        $files = array_slice($files, 0, 3);
        if ($files === []) {
            return;
        }

        $paths = [];
        foreach ($files as $file) {
            $paths[] = $file->store('productos', 'public');
        }

        $producto->imagenes = $paths;
        $producto->imagen_principal = $paths[0] ?? null;
        $producto->save();
    }

    private function procesarImagenesEnActualizacion(Request $request, Producto $producto): ?\Illuminate\Http\RedirectResponse
    {
        $actuales = $producto->rutasImagenes();
        $quitar = array_unique(array_map('intval', $request->input('quitar_imagen', [])));

        $mantener = [];
        foreach ($actuales as $indice => $path) {
            $path = trim((string) $path);
            if (in_array($indice, $quitar, true)) {
                if ($path !== '') {
                    Storage::disk('public')->delete($path);
                }
            } else {
                if ($path !== '') {
                    $mantener[] = $path;
                }
            }
        }

        $files = array_values(array_filter($request->file('imagenes', []) ?: []));
        $maxNuevos = max(0, 3 - count($mantener));
        if (count($files) > $maxNuevos) {
            return back()->withInput()->withErrors([
                'imagenes' => 'Solo puedes subir hasta '.$maxNuevos.' imagen(es) nueva(s) (máximo 3 en total).',
            ]);
        }

        foreach ($files as $file) {
            $mantener[] = $file->store('productos', 'public');
        }

        $mantener = array_values(array_filter($mantener));
        $producto->imagenes = $mantener !== [] ? $mantener : null;
        $producto->imagen_principal = $mantener[0] ?? null;
        $producto->save();

        return null;
    }
}