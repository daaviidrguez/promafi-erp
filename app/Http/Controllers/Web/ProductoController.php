<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/ProductoController.php

use App\Exports\ProductoPlantillaExport;
use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\CategoriaProducto;
use App\Models\ClaveProdServicio;
use App\Models\UnidadMedidaSat;
use App\Models\Proveedor;
use App\Services\ProductoImportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ProductoController extends Controller
{
    private const SESSION_SORT_KEY = 'productos_catalogo.sort';

    private const SESSION_DIR_KEY = 'productos_catalogo.dir';

    private const SESSION_PSI_NEXT_NUM = 'productos_catalogo.psi_next_num';

    private function obtenerSiguientePsiNumDesde(int $desde): int
    {
        return \App\Support\ProductoPsiConsecutivo::siguienteNumeroDesde($desde);
    }

    /**
     * Categorías activas con padre (cache 10 min).
     */
    private function categoriasActivasCached()
    {
        return Cache::remember('catalogo:categorias_productos_activas', 600, function () {
            return CategoriaProducto::with('parent')->activas()->orderBy('nombre')->get();
        });
    }

    /**
     * Unidades SAT activas (cache 10 min).
     */
    private function unidadesMedidaCached()
    {
        return Cache::remember('catalogo:unidades_medida_sat_activos', 600, function () {
            return UnidadMedidaSat::activos()->get();
        });
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
            $q->where('productos.categoria_id', $categoria_id);
        });

        // Prefijo en código (aprovecha índice); nombre sin barrer descripcion TEXT.
        if ($fCodigo !== '') {
            $escaped = addcslashes($fCodigo, '%_\\');
            $query->where('productos.codigo', 'like', $escaped . '%');
        }
        if ($fNombre !== '') {
            $escaped = addcslashes($fNombre, '%_\\');
            $query->where('productos.nombre', 'like', '%' . $escaped . '%');
        }
        if ($fCategoriaCol === 'sin') {
            $query->whereNull('productos.categoria_id');
        } elseif ($fCategoriaCol !== '' && $fCategoriaCol !== null && is_numeric($fCategoriaCol)) {
            $query->where('productos.categoria_id', (int) $fCategoriaCol);
        }
        if ($fPrecioMin !== '' && $fPrecioMin !== null && is_numeric($fPrecioMin)) {
            $query->where('productos.precio_venta', '>=', (float) $fPrecioMin);
        }
        if ($fPrecioMax !== '' && $fPrecioMax !== null && is_numeric($fPrecioMax)) {
            $query->where('productos.precio_venta', '<=', (float) $fPrecioMax);
        }
        if ($fStock === 'na') {
            $query->where('productos.controla_inventario', false);
        } elseif ($fStock === 'inventario') {
            $query->where('productos.controla_inventario', true);
        } elseif ($fStock === 'bajo') {
            $query->where('productos.controla_inventario', true)
                ->whereColumn('productos.stock', '<=', 'productos.stock_minimo');
        }
        if ($fActivo === '1' || $fActivo === '0') {
            $query->where('productos.activo', (bool) (int) $fActivo);
        }

        $cols = Producto::columnasListado();
        $query->select($cols)
            ->selectRaw('LEFT(productos.descripcion, 80) as descripcion');

        if ($sort === 'categoria') {
            $query->leftJoin('categorias_productos as cat_sort', 'productos.categoria_id', '=', 'cat_sort.id')
                ->orderByRaw('COALESCE(cat_sort.nombre, \'\') ' . $dir);
        } else {
            $query->orderBy('productos.' . $sort, $dir);
        }

        $appends = array_merge($request->except('page'), [
            'sort' => $sort,
            'dir' => $dir,
        ]);
        $productos = $query
            ->with([
                'categoria:id,nombre,color,icono,parent_id',
                'categoria.parent:id,nombre,color,icono',
                'claveProdServicio:clave,descripcion',
            ])
            ->paginate(20)
            ->appends($appends);

        $categorias = $this->categoriasActivasCached();

        $hayFiltrosColumna = $fCodigo !== '' || $fNombre !== '' || ($fCategoriaCol !== '' && $fCategoriaCol !== null)
            || ($fPrecioMin !== '' && $fPrecioMin !== null) || ($fPrecioMax !== '' && $fPrecioMax !== null)
            || ($fStock !== '' && $fStock !== null) || ($fActivo === '1' || $fActivo === '0');
        $hayFiltros = $search || $categoria_id || $hayFiltrosColumna;
        $hayCatalogo = Cache::remember('productos:catalogo_existe', 120, fn () => Producto::query()->exists());
        $mostrarTablaFiltros = $productos->isNotEmpty() || $hayFiltros || $hayCatalogo;

        if ($request->boolean('imported') && ! session()->has('success')) {
            session()->flash('success', sprintf(
                'Importación completada: %s creados, %s actualizados, %s omitidos.',
                number_format((int) $request->get('c', 0)),
                number_format((int) $request->get('a', 0)),
                number_format((int) $request->get('o', 0))
            ));
        }

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
        $categorias = $this->categoriasActivasCached();
        $unidadesMedida = $this->unidadesMedidaCached();
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

    /**
     * Descarga plantilla Excel para importar productos.
     */
    public function descargarPlantilla(): BinaryFileResponse
    {
        if (! auth()->user()->can('productos.importar') && ! auth()->user()->isAdmin()) {
            abort(403, 'No tienes permiso para descargar la plantilla de productos.');
        }

        return Excel::download(
            new ProductoPlantillaExport,
            'plantilla_productos.xlsx',
            \Maatwebsite\Excel\Excel::XLSX
        );
    }

    /**
     * Importa un lote de hasta 500 filas (AJAX, progreso en frontend).
     * Upsert por codigo: crea o actualiza; no modifica stock ni vacía el catálogo.
     */
    public function importarLote(Request $request, ProductoImportService $service)
    {
        if (! auth()->user()->can('productos.importar') && ! auth()->user()->isAdmin()) {
            abort(403, 'No tienes permiso para importar productos.');
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1|max:500',
            'items.*.codigo' => 'nullable',
            'items.*.nombre' => 'nullable|string',
            'items.*.marca' => 'nullable|string',
            'items.*.descripcion' => 'nullable|string',
            'items.*.clave_sat' => 'nullable|string',
            'items.*.clave_unidad_sat' => 'nullable|string',
            'items.*.unidad' => 'nullable|string',
            'items.*.objeto_impuesto' => 'nullable|string',
            'items.*.tipo_impuesto' => 'nullable|string',
            'items.*.tipo_factor' => 'nullable|string',
            'items.*.tasa_iva' => 'nullable|numeric',
            'items.*.costo' => 'nullable|numeric',
            'items.*.precio_venta' => 'nullable|numeric',
            'items.*.precio_mayoreo' => 'nullable|numeric',
            'items.*.precio_minimo' => 'nullable|numeric',
            'items.*.stock_minimo' => 'nullable|numeric',
            'items.*.controla_inventario' => 'nullable',
            'items.*.aplica_iva' => 'nullable',
            'items.*.activo' => 'nullable',
        ]);

        $result = $service->upsertFilas($validated['items']);
        if (($result['creados'] ?? 0) > 0) {
            Cache::forget('productos:catalogo_existe');
        }

        return response()->json([
            'ok' => true,
            'creados' => $result['creados'],
            'actualizados' => $result['actualizados'],
            'omitidos' => $result['omitidos'],
            'procesados' => count($validated['items']),
        ]);
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
            'stock_minimo' => 'nullable|numeric|min:0',
            'stock_maximo' => 'nullable|numeric|min:0',
            'controla_inventario' => 'boolean',
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
        $validated['precio_venta'] = 0; // Se configura después desde el show (Costos y Precios)
        $validated['costo'] = 0;
        $validated['stock'] = 0; // Stock se gestiona desde el módulo Inventario

        $producto = Producto::create($validated);

        $this->procesarImagenesEnCreacion($request, $producto);
        Cache::forget('productos:catalogo_existe');

        if ($psiCandidateCode && $codigoGuardado === $psiCandidateCode) {
            $nuevoNum = $this->obtenerSiguientePsiNumDesde($psiNextNum + 1);
            $request->session()->put(self::SESSION_PSI_NEXT_NUM, $nuevoNum);
        }

        return redirect()->route('productos.show', $producto->id)
            ->with('success', 'Producto creado exitosamente. Configura costos y precios con el botón Editar.');
    }

    public function show(Producto $producto)
    {
        $producto->load([
            'categoria.parent',
            'codigosProveedores.proveedor:id,codigo,nombre,nombre_comercial',
        ]);
        $catalogo = ClaveProdServicio::where('clave', $producto->clave_sat)->first();
        $claveSatEtiqueta = $catalogo ? $catalogo->etiqueta : $producto->clave_sat;

        return view('productos.show', compact('producto', 'claveSatEtiqueta'));
    }

    /**
     * Actualiza costos y precio de venta desde el modal del show.
     * Regla: el precio se basa en max(costo estándar, costo promedio).
     */
    public function updatePrecios(Request $request, Producto $producto)
    {
        if ($request->input('costo_promedio') === '' || $request->input('costo_promedio') === null) {
            $request->merge(['costo_promedio' => null]);
        }

        $validated = $request->validate([
            'costo' => 'required|numeric|min:0',
            'costo_promedio' => 'nullable|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'margen_utilidad' => 'nullable|numeric|min:0|max:99.99',
        ], [
            'costo.required' => 'El costo estándar es obligatorio.',
            'precio_venta.required' => 'El precio de venta es obligatorio.',
        ]);

        $costo = round((float) $validated['costo'], 4);
        $costoPromedio = array_key_exists('costo_promedio', $validated) && $validated['costo_promedio'] !== null
            ? round((float) $validated['costo_promedio'], 4)
            : null;

        $precioVenta = round((float) $validated['precio_venta'], 2);

        // Si mandan margen y el precio llega en 0, recalcular con margen factorizado.
        $margen = isset($validated['margen_utilidad']) ? (float) $validated['margen_utilidad'] : null;
        $costoBase = max($costo, (float) ($costoPromedio ?? 0));
        if ($margen !== null && $precioVenta <= 0 && $costoBase > 0) {
            $precioVenta = Producto::precioDesdeMargenFactorizado($costoBase, $margen);
        }

        $producto->update([
            'costo' => $costo,
            'costo_promedio' => $costoPromedio,
            'precio_venta' => $precioVenta,
            'requiere_revision_precio' => false,
        ]);

        return redirect()
            ->route('productos.show', $producto->id)
            ->with('success', 'Costos y precios actualizados correctamente');
    }

    /**
     * Autocomplete de proveedores para el modal de código proveedor (sin dump completo).
     */
    public function buscarProveedores(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        if (strlen($q) < 2) {
            return response()->json([]);
        }

        return response()->json(
            Proveedor::activos()
                ->buscar($q)
                ->limit(15)
                ->get(['id', 'codigo', 'nombre', 'nombre_comercial'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'nombre_comercial' => $p->nombre_comercial ?? '',
                    'etiqueta' => $p->etiqueta_con_codigo,
                ])
        );
    }

    public function edit(Producto $producto)
    {
        $categorias = $this->categoriasActivasCached();
        $unidadesMedida = $this->unidadesMedidaCached();
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
            'stock_minimo' => 'nullable|numeric|min:0',
            'stock_maximo' => 'nullable|numeric|min:0',
            'controla_inventario' => 'boolean',
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
        // Precio/costo se editan desde el modal de Costos y Precios en el show
        unset($validated['precio_venta'], $validated['costo'], $validated['costo_promedio']);
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
        Cache::forget('productos:catalogo_existe');

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