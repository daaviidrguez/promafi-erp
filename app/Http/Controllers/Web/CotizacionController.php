<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CotizacionController.php

use App\Http\Controllers\Controller;
use App\Helpers\IsrResicoHelper;
use App\Models\Cotizacion;
use App\Models\CotizacionAdjunto;
use App\Models\CotizacionDetalle;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\ListaPrecio;
use App\Models\Sugerencia;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaImpuesto;
use App\Models\CuentaPorCobrar;
use App\Models\FormaPago;
use App\Models\User;
use App\Models\EntradaAnticipada;
use App\Models\EntradaAnticipadaDetalle;
use App\Services\EntradaAnticipadaService;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Mail\CotizacionEnviada;
use Illuminate\Http\JsonResponse;

class CotizacionController extends Controller
{
    /**
     * Query base de cotizaciones: vendedor solo ve las suyas, admin ve todas.
     */
    private function queryCotizaciones()
    {
        return Cotizacion::paraUsuarioActual();
    }

    /**
     * Autorizar acceso a una cotización: vendedor solo puede acceder a las suyas.
     */
    private function authorizeCotizacion(Cotizacion $cotizacion): void
    {
        if (Auth::user()->isVendedor() && (int) $cotizacion->usuario_id !== (int) Auth::id()) {
            abort(403, 'No tienes permiso para acceder a esta cotización.');
        }
    }

    /**
     * Listado de cotizaciones
     */
    public function index(Request $request)
    {
        $query = $this->queryCotizaciones()->with(['cliente', 'usuario', 'factura']);

        // Filtros
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }

        if ($request->filled('cliente_id')) {
            $query->where('cliente_id', $request->cliente_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('folio', 'like', "%{$search}%")
                  ->orWhereHas('cliente', function($q2) use ($search) {
                      $q2->where('nombre', 'like', "%{$search}%")
                         ->orWhere('rfc', 'like', "%{$search}%");
                  });
            });
        }

        if ($request->filled('fecha_inicio')) {
            $query->where('fecha', '>=', $request->fecha_inicio);
        }

        if ($request->filled('fecha_fin')) {
            $query->where('fecha', '<=', $request->fecha_fin);
        }

        // Filtro por asesor (solo útil cuando el usuario puede ver varias)
        if ($request->filled('asesor_id') && !Auth::user()->isVendedor()) {
            $query->where('usuario_id', $request->asesor_id);
        }

        // Actualizar estado de vencidas automáticamente
        Cotizacion::vencidas()->update(['estado' => 'vencida']);

        $cotizaciones = $query->orderBy('created_at', 'desc')->paginate(20);

        // Estadísticas (vendedor solo cuenta las suyas)
        $statsQuery = $this->queryCotizaciones();
        $estadisticas = [
            'borradores' => (clone $statsQuery)->estado('borrador')->count(),
            'enviadas' => (clone $statsQuery)->estado('enviada')->count(),
            'aceptadas' => (clone $statsQuery)->estado('aceptada')->count(),
            'por_vencer' => (clone $statsQuery)->porVencer()->count(),
        ];

        $clientes = Cliente::activos()->orderBy('nombre')->get();

        $asesores = Auth::user()->isVendedor()
            ? collect()
            : User::activos()
                ->whereHas('role', fn ($q) => $q->where('name', 'vendedor'))
                ->orderBy('name')
                ->get(['id', 'name']);

        return view('cotizaciones.index', compact('cotizaciones', 'estadisticas', 'clientes', 'asesores'));
    }

    /**
     * Formulario crear/editar
     */
    public function create(Request $request)
    {
        $empresa = Empresa::principal();

        if (!$empresa) {
            return redirect()->route('dashboard')
                ->with('error', 'Debes configurar los datos de la empresa primero');
        }

        $cotizacion = null;
        $folio = Cotizacion::siguienteFolioDisponible($empresa);

        // Modo edición: cargar cotización con detalles ordenados para repoblar el formulario
        if ($request->has('id')) {
            $cotizacion = Cotizacion::with(['detalles' => fn ($q) => $q->orderBy('orden'), 'detalles.producto'])
                ->findOrFail($request->id);
            $this->authorizeCotizacion($cotizacion);

            if (!$cotizacion->puedeEditarse()) {
                return redirect()->route('cotizaciones.show', $cotizacion->id)
                    ->with('error', 'Esta cotización no puede editarse');
            }
        }

        $formasPago = FormaPago::activos()->get();
        return view('cotizaciones.create', compact('empresa', 'folio', 'cotizacion', 'formasPago'));
    }

    /**
     * Guardar cotización
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'fecha_vencimiento' => 'required|date|after_or_equal:fecha',
            'tipo_venta' => 'required|in:contado,credito',
            'dias_credito' => 'nullable|integer|min:0',
            'forma_pago' => 'nullable|string|exists:formas_pago,clave',
            'condiciones_pago' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'referencia_comercial' => 'nullable|string|max:255',
            'referencia_url' => 'nullable|string|max:2048',
            'referencia_url_2' => 'nullable|string|max:2048',
            'referencia_url_3' => 'nullable|string|max:2048',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.origen' => 'nullable|string|max:100',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.unidad' => 'nullable|string|max:10',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.utilidad' => 'nullable|numeric|min:0|lt:100',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.es_producto_manual' => 'nullable|boolean',
            'productos.*.sugerencia_id' => 'nullable|exists:sugerencias,id',
            'productos.*.codigo' => 'nullable|string|max:50',
            'productos.*.imagenes_mantener' => 'nullable|array|max:3',
            'productos.*.imagenes_mantener.*' => 'nullable|string|max:500',
            'productos.*.imagenes' => 'nullable|array|max:3',
            'productos.*.imagenes.*' => 'nullable|image|mimes:jpeg,jpg,png,gif,webp|max:5120',
        ], [
            'productos.*.imagenes.*.image' => 'Cada archivo debe ser una imagen válida.',
            'productos.*.imagenes.*.max' => 'Cada imagen no debe superar 5 MB.',
        ]);

        foreach ($request->input('productos', []) as $idx => $item) {
            $mantener = count(array_filter((array) ($item['imagenes_mantener'] ?? [])));
            $nuevas = count(array_filter($request->file("productos.{$idx}.imagenes", []) ?: []));
            if ($mantener + $nuevas > 3) {
                return back()->withInput()->withErrors([
                    "productos.{$idx}.imagenes" => 'Máximo 3 imágenes por partida.',
                ]);
            }
        }

        DB::beginTransaction();
        try {
            $cliente = Cliente::findOrFail($validated['cliente_id']);
            $empresa = Empresa::principal();

            // Calcular totales
            $subtotalGeneral = 0;
            $descuentoGeneral = 0;
            $ivaGeneral = 0;

            foreach ($validated['productos'] as $item) {
                $item['utilidad'] = CotizacionDetalle::normalizarUtilidad($item['utilidad'] ?? null);
                $importes = CotizacionDetalle::calcularImportes($item);
                $subtotalGeneral += $importes['subtotal'];
                $descuentoGeneral += $importes['descuento_monto'];
                $ivaGeneral += $importes['iva_monto'];
            }

            $totalGeneral = ($subtotalGeneral - $descuentoGeneral) + $ivaGeneral;

            $isrRetenido = 0.0;
            if (IsrResicoHelper::aplicaRetencionIsrPm($empresa, $cliente)) {
                $isrRetenido = IsrResicoHelper::calcularRetencionIsrPm($subtotalGeneral, $descuentoGeneral);
            }
            $totalGeneral -= $isrRetenido;

            // Crear o actualizar cotización
            $cotizacionId = $request->input('cotizacion_id');
            $imagenesAntiguas = [];

            if ($cotizacionId) {
                $cotizacion = Cotizacion::with('detalles')->findOrFail($cotizacionId);

                if (!$cotizacion->puedeEditarse()) {
                    throw new \Exception('Esta cotización no puede editarse');
                }

                foreach ($cotizacion->detalles as $detalleAntiguo) {
                    foreach ($detalleAntiguo->rutasImagenes() as $path) {
                        $imagenesAntiguas[] = $path;
                    }
                }

                // Eliminar detalle anterior
                $cotizacion->detalles()->delete();
            } else {
                $cotizacion = new Cotizacion();
                $cotizacion->folio = Cotizacion::generarFolio();
                $cotizacion->estado = 'borrador';
                $cotizacion->usuario_id = auth()->id();
            }

            // Datos de la cotización
            $cotizacion->fill([
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresa->id,
                
                // Snapshot del cliente
                'cliente_nombre' => $cliente->nombre,
                'cliente_rfc' => $cliente->rfc,
                'cliente_email' => $cliente->email,
                'cliente_telefono' => $cliente->telefono,
                'cliente_calle' => $cliente->calle,
                'cliente_numero_exterior' => $cliente->numero_exterior,
                'cliente_numero_interior' => $cliente->numero_interior,
                'cliente_colonia' => $cliente->colonia,
                'cliente_municipio' => $cliente->ciudad ?? $cliente->municipio ?? null,
                'cliente_estado' => $cliente->estado,
                'cliente_codigo_postal' => $cliente->codigo_postal,
                
                // Fechas
                'fecha' => $validated['fecha'],
                'fecha_vencimiento' => $validated['fecha_vencimiento'],
                
                // Moneda
                'moneda' => 'MXN',
                'tipo_cambio' => 1,
                
                // Totales
                'subtotal' => $subtotalGeneral,
                'descuento' => $descuentoGeneral,
                'iva' => $ivaGeneral,
                'isr_retenido' => $isrRetenido,
                'total' => $totalGeneral,
                
                // Condiciones de pago
                'tipo_venta' => $validated['tipo_venta'],
                'dias_credito_aplicados' => $validated['tipo_venta'] === 'credito' 
                    ? ($validated['dias_credito'] ?? 0) 
                    : 0,
                'forma_pago' => $validated['forma_pago'] ?? $cliente->forma_pago ?? '03',
                'condiciones_pago' => $validated['condiciones_pago'],
                'observaciones' => $validated['observaciones'],
                'referencia_comercial' => $validated['referencia_comercial'] ?? null,
                'referencia_url' => $validated['referencia_url'] ?? null,
                'referencia_url_2' => $validated['referencia_url_2'] ?? null,
                'referencia_url_3' => $validated['referencia_url_3'] ?? null,
            ]);

            // Al editar, invalidar PDF para que se regenere con los datos actuales
            if ($cotizacionId) {
                $cotizacion->pdf_path = null;
            }

            $cotizacion->save();

            $imagenesUsadas = [];

            // Crear detalles
            foreach ($validated['productos'] as $index => $item) {
                $producto = null;
                if (!empty($item['producto_id'])) {
                    $producto = Producto::find($item['producto_id']);
                }

                $sugerenciaId = !empty($item['sugerencia_id']) ? (int) $item['sugerencia_id'] : null;
                $esManual = $item['es_producto_manual'] ?? false;
                $unidadDetalle = !empty($item['unidad']) ? $item['unidad'] : ($producto?->unidad ?? 'PZA');
                if (strlen($unidadDetalle) > 10) {
                    $unidadDetalle = substr($unidadDetalle, 0, 10);
                }

                $utilidad = CotizacionDetalle::normalizarUtilidad($item['utilidad'] ?? null);
                $precioVenta = CotizacionDetalle::precioUnitarioVenta(
                    (float) $item['precio_unitario'],
                    $utilidad
                );

                // Partida manual sin sugerencia: guardar/actualizar en sugerencias para futuras cotizaciones
                if ($esManual && !$sugerenciaId && !empty(trim($item['descripcion'] ?? ''))) {
                    $sugerencia = Sugerencia::firstOrCreate(
                        [
                            'descripcion' => trim($item['descripcion']),
                            'unidad' => $unidadDetalle,
                        ],
                        [
                            'codigo' => null,
                            'precio_unitario' => $precioVenta,
                        ]
                    );
                    $sugerencia->update(['precio_unitario' => $precioVenta]);
                    $sugerenciaId = $sugerencia->id;
                }

                $codigoDetalle = $producto?->codigo
                    ?? (!empty($item['codigo']) && trim((string) $item['codigo']) !== '' && trim((string) $item['codigo']) !== '-'
                        ? trim((string) $item['codigo'])
                        : '-');

                CotizacionDetalle::create([
                    'cotizacion_id' => $cotizacion->id,
                    'producto_id' => $producto?->id,
                    'sugerencia_id' => $sugerenciaId,
                    'codigo' => $codigoDetalle,
                    'descripcion' => $item['descripcion'],
                    'origen' => isset($item['origen']) ? (trim((string) $item['origen']) ?: null) : null,
                    'es_producto_manual' => $esManual,
                    'cantidad' => $item['cantidad'],
                    'unidad' => $unidadDetalle,
                    'precio_unitario' => $item['precio_unitario'],
                    'utilidad' => $utilidad,
                    'descuento_porcentaje' => $item['descuento_porcentaje'] ?? 0,
                    'tasa_iva' => $item['tasa_iva'] ?? null,
                    'orden' => $index,
                    'imagenes' => $this->procesarImagenesPartida($request, $index, $cotizacion->id, $imagenesUsadas),
                ]);
                // Actualizar precio más reciente en la sugerencia para próximas cotizaciones (precio de venta)
                if ($sugerenciaId) {
                    Sugerencia::where('id', $sugerenciaId)->update(['precio_unitario' => $precioVenta]);
                }
            }

            $this->eliminarImagenesHuerfanas($imagenesAntiguas, $imagenesUsadas);

            DB::commit();

            return redirect()->route('cotizaciones.show', $cotizacion->id)
                ->with('success', $cotizacionId ? 'Cotización actualizada' : 'Cotización creada exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Error al guardar: ' . $e->getMessage());
        }
    }

    /**
     * Ver detalle de cotización
     */
    public function show($id)
    {
        $cotizacion = Cotizacion::with([
            'cliente',
            'detalles.producto',
            'usuario',
            'factura',
            'adjuntos.usuario',
        ])->findOrFail($id);
        $this->authorizeCotizacion($cotizacion);

        return view('cotizaciones.show', compact('cotizacion'));
    }

    /**
     * Subir documento de respaldo interno (PDF de proveedor, etc.).
     */
    public function subirAdjunto(Request $request, $id)
    {
        abort_unless(auth()->user()?->can('cotizaciones.adjuntos'), 403);

        $cotizacion = Cotizacion::findOrFail($id);
        $this->authorizeCotizacion($cotizacion);

        $validated = $request->validate([
            'archivo' => 'required|file|mimes:pdf|max:10240',
            'nota' => 'nullable|string|max:255',
        ], [
            'archivo.required' => 'Selecciona un archivo PDF.',
            'archivo.mimes' => 'Solo se permiten archivos PDF.',
            'archivo.max' => 'El archivo no debe superar 10 MB.',
        ]);

        $file = $request->file('archivo');
        $nombreOriginal = $file->getClientOriginalName();
        $dir = storage_path('app/documentos/cotizacion_adjuntos/' . $cotizacion->id);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = $file->hashName();
        $file->move($dir, $filename);
        $relativePath = 'documentos/cotizacion_adjuntos/' . $cotizacion->id . '/' . $filename;

        CotizacionAdjunto::create([
            'cotizacion_id' => $cotizacion->id,
            'nombre_original' => $nombreOriginal,
            'path' => $relativePath,
            'mime_type' => 'application/pdf',
            'size' => filesize(storage_path('app/' . $relativePath)) ?: null,
            'nota' => $validated['nota'] ?? null,
            'usuario_id' => Auth::id(),
        ]);

        return redirect()->route('cotizaciones.show', $cotizacion->id)
            ->with('success', 'Documento de respaldo cargado correctamente.');
    }

    /**
     * Ver PDF de respaldo en el navegador.
     */
    public function verAdjunto($cotizacionId, $adjuntoId)
    {
        $cotizacion = Cotizacion::findOrFail($cotizacionId);
        $this->authorizeCotizacion($cotizacion);

        $adjunto = CotizacionAdjunto::where('cotizacion_id', $cotizacion->id)
            ->findOrFail($adjuntoId);

        if (! $adjunto->existeEnDisco()) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->file($adjunto->rutaAbsoluta(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . addslashes($adjunto->nombre_original) . '"',
        ]);
    }

    /**
     * Descargar documento de respaldo.
     */
    public function descargarAdjunto($cotizacionId, $adjuntoId)
    {
        $cotizacion = Cotizacion::findOrFail($cotizacionId);
        $this->authorizeCotizacion($cotizacion);

        $adjunto = CotizacionAdjunto::where('cotizacion_id', $cotizacion->id)
            ->findOrFail($adjuntoId);

        if (! $adjunto->existeEnDisco()) {
            abort(404, 'Archivo no encontrado.');
        }

        return response()->download(
            $adjunto->rutaAbsoluta(),
            $adjunto->nombre_original
        );
    }

    /**
     * Eliminar documento de respaldo.
     */
    public function eliminarAdjunto($cotizacionId, $adjuntoId)
    {
        abort_unless(auth()->user()?->can('cotizaciones.adjuntos'), 403);

        $cotizacion = Cotizacion::findOrFail($cotizacionId);
        $this->authorizeCotizacion($cotizacion);

        $adjunto = CotizacionAdjunto::where('cotizacion_id', $cotizacion->id)
            ->findOrFail($adjuntoId);

        $adjunto->eliminarDelDisco();
        $adjunto->delete();

        return redirect()->route('cotizaciones.show', $cotizacion->id)
            ->with('success', 'Documento de respaldo eliminado.');
    }



    /**
     * Aceptar cotización
     */
    public function aceptar($id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);
            $this->authorizeCotizacion($cotizacion);

            if (!$cotizacion->puedeAceptarse()) {
                return back()->with('error', 'Esta cotización no puede aceptarse');
            }

            $cotizacion->aceptar();

            return redirect()->route('cotizaciones.show', $id)
                ->with('success', 'Cotización aceptada exitosamente');

        } catch (\Exception $e) {
            return back()->with('error', 'Error al aceptar: ' . $e->getMessage());
        }
    }

    /**
     * Enviar cotización por email
     */
    public function enviar($id)
    {
        try {
            $cotizacion = Cotizacion::with('cliente', 'detalles')->findOrFail($id);
            $this->authorizeCotizacion($cotizacion);

            if (!$cotizacion->puedeEnviarse()) {
                return back()->with('error', 'Solo se pueden enviar cotizaciones aceptadas');
            }

            if (empty($cotizacion->cliente_email)) {
                return back()->with('error', 'El cliente no tiene email registrado');
            }

            // Generar PDF si no existe
            if (!$cotizacion->pdf_path || !file_exists(storage_path('app/' . $cotizacion->pdf_path))) {
                $pdfPath = app(PDFService::class)->generarCotizacionPDF($cotizacion);
                $cotizacion->pdf_path = $pdfPath;
                $cotizacion->save();
            }

            // Enviar email
            Mail::to($cotizacion->cliente_email)
                ->send(new CotizacionEnviada($cotizacion));

            // Marcar como enviada
            $cotizacion->marcarComoEnviada();

            return redirect()->route('cotizaciones.show', $id)
                ->with('success', 'Cotización enviada exitosamente a ' . $cotizacion->cliente_email);

        } catch (\Exception $e) {
            return back()->with('error', 'Error al enviar: ' . $e->getMessage());
        }
    }

    /**
     * Generar PDF
     */
    public function generarPDF($id)
    {
        try {
            $cotizacion = Cotizacion::with('detalles')->findOrFail($id);
            $this->authorizeCotizacion($cotizacion);

            $pdfPath = app(PDFService::class)->generarCotizacionPDF($cotizacion);

            $cotizacion->pdf_path = $pdfPath;
            $cotizacion->save();

            return response()->download(
                storage_path('app/' . $pdfPath),
                'Cotizacion_' . $cotizacion->folio . '.pdf'
            );

        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Descargar PDF
     */
    public function descargarPDF($id)
    {
        $cotizacion = Cotizacion::findOrFail($id);
        $this->authorizeCotizacion($cotizacion);

        if (!$cotizacion->pdf_path || !file_exists(storage_path('app/' . $cotizacion->pdf_path))) {
            return $this->generarPDF($id);
        }

        return response()->download(
            storage_path('app/' . $cotizacion->pdf_path),
            'Cotizacion_' . $cotizacion->folio . '.pdf'
        );
    }

    /**
     * Ver PDF en navegador
     */
    public function verPDF($id)
    {
        try {
            $cotizacion = Cotizacion::with('detalles')->findOrFail($id);
            $this->authorizeCotizacion($cotizacion);

            if (!$cotizacion->pdf_path || !file_exists(storage_path('app/' . $cotizacion->pdf_path))) {
                $pdfPath = app(PDFService::class)->generarCotizacionPDF($cotizacion);
                $cotizacion->pdf_path = $pdfPath;
                $cotizacion->save();
            }

            return response()->file(storage_path('app/' . $cotizacion->pdf_path));

        } catch (\Exception $e) {
            return back()->with('error', 'Error al mostrar PDF: ' . $e->getMessage());
        }
    }

    /**
     * Ver imagen de una partida (autenticado; no depende del symlink public/storage).
     */
    public function verImagenPartida($cotizacionId, $detalleId, int $indice)
    {
        $cotizacion = Cotizacion::findOrFail($cotizacionId);
        $this->authorizeCotizacion($cotizacion);

        $detalle = CotizacionDetalle::where('cotizacion_id', $cotizacion->id)
            ->findOrFail($detalleId);

        $paths = $detalle->rutasImagenes();
        if (! array_key_exists($indice, $paths)) {
            abort(404);
        }

        $relativePath = $paths[$indice];
        if (! $this->esRutaImagenCotizacionValida($relativePath, $cotizacion->id)) {
            abort(404);
        }

        $fullPath = Storage::disk('public')->path($relativePath);
        if (! is_file($fullPath)) {
            abort(404);
        }

        return response()->file($fullPath, [
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }

    /**
     * Crear productos desde partidas manuales (cotización aceptada/enviada).
     * Crea un producto por cada detalle manual con descripción, unidad y precio de venta.
     */
    public function crearProductosDesdeManuales($id)
    {
        DB::beginTransaction();
        try {
            $cotizacion = Cotizacion::with('detalles')->findOrFail($id);
            $this->authorizeCotizacion($cotizacion);

            if (!$cotizacion->puedeFacturarse()) {
                return back()->with('error', 'La cotización debe estar aceptada o enviada.');
            }

            $manuales = $cotizacion->detalles()->where('es_producto_manual', true)->orderBy('orden')->get();
            if ($manuales->isEmpty()) {
                return back()->with('error', 'No hay partidas manuales para crear productos.');
            }

            $creados = 0;
            foreach ($manuales as $detalle) {
                $codigo = 'COT-' . $cotizacion->id . '-' . $detalle->orden;
                $base = $codigo;
                $contador = 0;
                while (Producto::where('codigo', $codigo)->exists()) {
                    $contador++;
                    $codigo = $base . '-' . $contador;
                }

                $producto = Producto::create([
                    'codigo' => $codigo,
                    'nombre' => \Str::limit((string) $detalle->descripcion, 255),
                    'descripcion' => $detalle->descripcion,
                    'unidad' => $detalle->unidad ?? 'PZA',
                    'clave_sat' => '01010101',
                    'clave_unidad_sat' => 'H87',
                    'objeto_impuesto' => '02',
                    'tipo_impuesto' => '002',
                    'tipo_factor' => 'Tasa',
                    'tasa_iva' => (float) ($detalle->tasa_iva ?? 0.16),
                    'aplica_iva' => true,
                    'precio_venta' => $detalle->precioUnitarioVentaCalculado(),
                    'stock' => 0,
                    'controla_inventario' => true,
                    'activo' => true,
                ]);

                $detalle->update([
                    'producto_id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'es_producto_manual' => false,
                ]);
                $creados++;
            }

            DB::commit();

            return redirect()->route('cotizaciones.show', $cotizacion)
                ->with('success', "Se crearon {$creados} producto(s) desde las partidas manuales. Puedes convertir a factura cuando tengan stock.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al crear productos: ' . $e->getMessage());
        }
    }

    /**
     * Convertir a factura
     */
    public function convertirFactura($id)
    {
        abort_unless(auth()->user()?->can('facturas.crear'), 403);

        DB::beginTransaction();
        try {
            $cotizacion = Cotizacion::with(['detalles.producto', 'cliente', 'empresa'])
                ->lockForUpdate()
                ->findOrFail($id);
            $this->authorizeCotizacion($cotizacion);

            if (Factura::withTrashed()->where('cotizacion_id', $cotizacion->id)->exists()) {
                throw new \Exception('Esta cotización ya fue convertida a factura.');
            }

            if (!$cotizacion->puedeFacturarse()) {
                throw new \Exception('Esta cotización no puede facturarse.');
            }

            $motivo = $cotizacion->motivoNoConvertirAFactura();
            if ($motivo !== null) {
                throw new \Exception($motivo);
            }

            $empresa = $cotizacion->empresa ?? Empresa::principal();
            if (!$empresa) {
                throw new \Exception('No hay empresa configurada.');
            }

            $cliente = $cotizacion->cliente;
            if (!$cliente) {
                throw new \Exception('La cotización no tiene cliente asociado.');
            }

            $metodoPago = strtolower($cotizacion->tipo_venta ?? 'contado') === 'credito' ? 'PPD' : 'PUE';
            $folioReservado = Empresa::reservarFolioFacturaCredito($empresa->id);
            $serieFactura = $folioReservado['serie'];
            $folioFactura = $folioReservado['folio'];
            $formaPago = $cotizacion->forma_pago ?? $cliente->forma_pago ?? '03';
            $usoCfdi = $cliente->uso_cfdi_default ?? 'G03';

            $retencionISR = (float) ($cotizacion->isr_retenido ?? 0);
            if ($retencionISR <= 0 && IsrResicoHelper::aplicaRetencionIsrPm($empresa, $cliente)) {
                $retencionISR = IsrResicoHelper::calcularRetencionIsrPm(
                    (float) $cotizacion->subtotal,
                    (float) ($cotizacion->descuento ?? 0)
                );
            }
            $baseGravableTotal = max(0.01, (float) $cotizacion->subtotal - (float) ($cotizacion->descuento ?? 0));

            $factura = Factura::create([
                'serie' => $serieFactura,
                'folio' => $folioFactura,
                'tipo_comprobante' => 'I',
                'estado' => 'borrador',
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresa->id,
                'rfc_emisor' => $empresa->rfc,
                'nombre_emisor' => $empresa->razon_social,
                'regimen_fiscal_emisor' => $empresa->regimen_fiscal,
                'rfc_receptor' => $cliente->rfc,
                'nombre_receptor' => $cliente->nombre,
                'uso_cfdi' => $usoCfdi,
                'regimen_fiscal_receptor' => $cliente->regimen_fiscal,
                'domicilio_fiscal_receptor' => $cliente->codigo_postal,
                'lugar_expedicion' => $empresa->codigo_postal,
                'fecha_emision' => now()->toDateString(),
                'forma_pago' => $formaPago,
                'metodo_pago' => $metodoPago,
                'moneda' => $cotizacion->moneda ?? 'MXN',
                'tipo_cambio' => $cotizacion->tipo_cambio ?? 1,
                'subtotal' => $cotizacion->subtotal,
                'descuento' => $cotizacion->descuento ?? 0,
                'total' => $cotizacion->total,
                'cotizacion_id' => $cotizacion->id,
                'observaciones' => $cotizacion->observaciones,
                'usuario_id' => auth()->id(),
            ]);

            foreach ($cotizacion->detalles as $index => $d) {
                $producto = $d->producto;
                if (!$producto) {
                    continue;
                }
                $valorUnitario = $d->precioUnitarioVentaCalculado();
                $cantidad = (float) $d->cantidad;
                $descuentoMonto = (float) ($d->descuento_monto ?? 0);
                $importe = $cantidad * $valorUnitario;
                $baseImpuesto = $importe - $descuentoMonto;
                $objetoImpuesto = $producto->objeto_impuesto ?? '02';

                $detalle = FacturaDetalle::create([
                    'factura_id' => $factura->id,
                    'producto_id' => $producto->id,
                    'clave_prod_serv' => $producto->clave_sat ?? '01010101',
                    'clave_unidad' => $producto->clave_unidad_sat ?? 'H87',
                    'unidad' => $producto->unidad ?? 'Pieza',
                    'no_identificacion' => $producto->codigo,
                    'descripcion' => $d->descripcion,
                    'cantidad' => $cantidad,
                    'valor_unitario' => $valorUnitario,
                    'importe' => $importe,
                    'descuento' => $descuentoMonto,
                    'base_impuesto' => $baseImpuesto,
                    'objeto_impuesto' => $objetoImpuesto,
                    'orden' => $index,
                ]);

                if (in_array($objetoImpuesto, ['02', '03'], true)) {
                    $tipoFactor = $producto->tipo_factor ?? 'Tasa';
                    $tasa = (float) ($producto->tasa_iva ?? 0);
                    FacturaImpuesto::create([
                        'factura_detalle_id' => $detalle->id,
                        'tipo' => 'traslado',
                        'impuesto' => $producto->tipo_impuesto ?? '002',
                        'tipo_factor' => $tipoFactor,
                        'tasa_o_cuota' => $tipoFactor === 'Tasa' ? $tasa : null,
                        'base' => $baseImpuesto,
                        'importe' => $tipoFactor === 'Tasa' && $tasa > 0 ? round($baseImpuesto * $tasa, 2) : null,
                    ]);
                }

                if ($retencionISR > 0 && $baseImpuesto > 0) {
                    $retencionLinea = round($retencionISR * ($baseImpuesto / $baseGravableTotal), 2);
                    if ($retencionLinea > 0) {
                        FacturaImpuesto::create([
                            'factura_detalle_id' => $detalle->id,
                            'tipo' => 'retencion',
                            'impuesto' => '001',
                            'tipo_factor' => 'Tasa',
                            'tasa_o_cuota' => config('isr_resico.tasa_retencion_pm_a_resico', 0.0125),
                            'base' => $baseImpuesto,
                            'importe' => $retencionLinea,
                        ]);
                    }
                }
                // El descuento de inventario se hace al timbrar la factura, no en borrador
            }

            if ($metodoPago === 'PPD') {
                $diasCredito = (int) ($cotizacion->dias_credito_aplicados ?? $cliente->dias_credito ?? 0);
                $fechaVencimiento = $diasCredito > 0 ? now()->addDays($diasCredito) : now();

                CuentaPorCobrar::create([
                    'factura_id' => $factura->id,
                    'cliente_id' => $cliente->id,
                    'monto_total' => $cotizacion->total,
                    'monto_pagado' => 0,
                    'monto_pendiente' => $cotizacion->total,
                    'fecha_emision' => $factura->fecha_emision,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                ]);
                $cliente->actualizarSaldo();
            }

            $cotizacion->marcarComoFacturada();

            DB::commit();

            return redirect()->route('facturas.show', $factura->id)
                ->with('success', 'Cotización convertida a factura en borrador. Puede timbrar cuando esté listo.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al convertir: ' . $e->getMessage());
        }
    }

    /**
     * Asignar un producto del catálogo a una partida de la cotización (lupita en show).
     */
    public function asignarProductoDetalle(Request $request, $cotizacion, CotizacionDetalle $detalle): JsonResponse
    {
        $cotizacion = Cotizacion::with(['detalles', 'detalles.producto'])->findOrFail($cotizacion);
        $this->authorizeCotizacion($cotizacion);

        if (!$cotizacion->puedeFacturarse()) {
            return response()->json(['success' => false, 'message' => 'La cotización debe estar aceptada o enviada.'], 422);
        }

        if ((int) $detalle->cotizacion_id !== (int) $cotizacion->id) {
            return response()->json(['success' => false, 'message' => 'La partida no pertenece a la cotización.'], 422);
        }

        $validated = $request->validate([
            'producto_id' => 'required|exists:productos,id',
        ]);

        $producto = Producto::where('activo', true)->findOrFail((int) $validated['producto_id']);

        // Vinculamos producto + código; conservamos descripción y precio ya cotizados.
        $detalle->update([
            'producto_id' => $producto->id,
            'codigo' => $producto->codigo,
            'unidad' => $detalle->unidad ?? $producto->unidad ?? 'PZA',
            'es_producto_manual' => false,
            'tasa_iva' => $detalle->tasa_iva !== null
                ? $detalle->tasa_iva
                : ((($producto->tipo_factor ?? 'Tasa') === 'Exento') ? null : (float) ($producto->tasa_iva ?? 0.16)),
        ]);

        return response()->json([
            'success' => true,
            'producto' => $this->payloadProductoAsignado($producto),
        ]);
    }

    /**
     * Creación rápida de producto desde una partida (modal lupa) y asignación inmediata.
     * Código PSI consecutivo; clave SAT opcional (si vacía → 01010101 provisional).
     */
    public function crearProductoRapidoDetalle(Request $request, $cotizacion, CotizacionDetalle $detalle): JsonResponse
    {
        abort_unless(auth()->user()?->can('productos.crear'), 403);

        $cotizacion = Cotizacion::with(['detalles'])->findOrFail($cotizacion);
        $this->authorizeCotizacion($cotizacion);

        if (! $cotizacion->puedeFacturarse()) {
            return response()->json(['success' => false, 'message' => 'La cotización debe estar aceptada o enviada.'], 422);
        }

        if ((int) $detalle->cotizacion_id !== (int) $cotizacion->id) {
            return response()->json(['success' => false, 'message' => 'La partida no pertenece a la cotización.'], 422);
        }

        $request->validate([
            'forzar' => 'nullable|boolean',
            'clave_sat' => 'nullable|string|max:20',
        ]);

        $claveSat = $this->normalizarClaveSatRapida($request->input('clave_sat'));
        if ($claveSat === null) {
            return response()->json([
                'success' => false,
                'message' => 'La Clave Prod./Serv. debe tener 8 dígitos (o déjela vacía para usar 01010101 provisional).',
            ], 422);
        }

        $nombre = \Illuminate\Support\Str::limit(trim((string) $detalle->descripcion), 255);
        if ($nombre === '') {
            return response()->json(['success' => false, 'message' => 'La partida no tiene descripción para nombrar el producto.'], 422);
        }

        if (! $request->boolean('forzar')) {
            $similar = $this->nombreProductoActivoSiDescripcionSuperaSimilitud($nombre);
            if ($similar !== null) {
                return response()->json([
                    'success' => false,
                    'needs_confirm' => true,
                    'message' => 'El texto coincide con un producto existente («'
                        . \Illuminate\Support\Str::limit($similar, 120)
                        . '»). Busque en la lupita o confirme para crear uno nuevo de todas formas.',
                ], 422);
            }
        }

        DB::beginTransaction();
        try {
            $psiNum = $this->obtenerSiguientePsiNumDesde(1);
            $codigoPsi = 'PSI-' . $psiNum;
            while (Producto::where('codigo', $codigoPsi)->exists()) {
                $psiNum++;
                $codigoPsi = 'PSI-' . $psiNum;
            }

            $tipoFactor = $detalle->tasa_iva === null ? 'Exento' : 'Tasa';
            $tasaIva = $detalle->tasa_iva === null ? 0.0 : (float) $detalle->tasa_iva;

            $producto = Producto::create([
                'codigo' => $codigoPsi,
                'nombre' => $nombre,
                'descripcion' => $detalle->descripcion,
                'categoria_id' => null,
                'clave_sat' => $claveSat,
                'clave_unidad_sat' => 'H87',
                'unidad' => $detalle->unidad ?? 'PZA',
                'objeto_impuesto' => '02',
                'tipo_impuesto' => '002',
                'tipo_factor' => $tipoFactor,
                'tasa_iva' => $tasaIva,
                'precio_venta' => $detalle->precioUnitarioVentaCalculado(),
                'costo' => 0,
                'stock_minimo' => 0,
                'stock_maximo' => 0,
                'controla_inventario' => true,
                'aplica_iva' => $tipoFactor !== 'Exento',
                'tasa_ieps' => 0,
                'stock' => 0,
                'activo' => true,
            ]);

            $detalle->update([
                'producto_id' => $producto->id,
                'codigo' => $producto->codigo,
                'unidad' => $detalle->unidad ?? $producto->unidad ?? 'PZA',
                'es_producto_manual' => false,
            ]);

            DB::commit();

            $esProvisional = $claveSat === '01010101';
            $mensaje = $esProvisional
                ? 'Producto '.$producto->codigo.' creado y asignado (clave SAT provisional 01010101). Complete clave SAT y stock en catálogo antes de timbrar.'
                : 'Producto '.$producto->codigo.' creado y asignado con clave SAT '.$claveSat.'.';
            session()->flash('success', $mensaje);

            return response()->json([
                'success' => true,
                'producto_id' => $producto->id,
                'codigo' => $producto->codigo,
                'producto' => $this->payloadProductoAsignado($producto),
                'message' => $mensaje,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['success' => false, 'message' => 'No se pudo crear el producto: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Vacío → 01010101. Si hay valor, debe quedar en exactamente 8 dígitos.
     * @return string|null null si el formato es inválido
     */
    private function normalizarClaveSatRapida(mixed $clave): ?string
    {
        $raw = trim((string) ($clave ?? ''));
        if ($raw === '') {
            return '01010101';
        }

        // Si viene "clave - descripción", tomar solo la clave.
        if (str_contains($raw, ' - ')) {
            $raw = trim(explode(' - ', $raw, 2)[0]);
        }

        $digitos = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digitos === '') {
            return '01010101';
        }

        if (! preg_match('/^\d{8}$/', $digitos)) {
            return null;
        }

        return $digitos;
    }

    /**
     * Asistente: cotización aceptada/enviada → resolver catálogo → entradas anticipadas por proveedor.
     */
    public function crearEntradaAnticipada($cotizacion)
    {
        abort_unless(auth()->user()?->can('entradas_anticipadas.crear'), 403);

        $cotizacion = Cotizacion::with(['detalles.producto', 'cliente'])->findOrFail($cotizacion);
        $this->authorizeCotizacion($cotizacion);

        if (! $cotizacion->puedeCrearEntradaAnticipada()) {
            return redirect()->route('cotizaciones.show', $cotizacion->id)
                ->with('error', 'La cotización debe estar aceptada o enviada y tener partidas con cantidad.');
        }

        $empresa = Empresa::principal();
        if (! $empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura la empresa primero.');
        }

        $lineas = $this->lineasWizardEntradaAnticipada($cotizacion);
        $entradasPrevias = $this->entradasPreviasDesdeCotizacion($cotizacion);

        return view('cotizaciones.crear-entrada-anticipada', compact(
            'cotizacion',
            'empresa',
            'lineas',
            'entradasPrevias'
        ));
    }

    /**
     * Crea una entrada anticipada (un proveedor / un grupo) desde partidas de la cotización.
     * Responde JSON para el asistente multi-proveedor.
     */
    public function storeEntradaAnticipada(Request $request, $cotizacion, EntradaAnticipadaService $service)
    {
        abort_unless(auth()->user()?->can('entradas_anticipadas.crear'), 403);

        $cotizacion = Cotizacion::with(['detalles.producto'])->findOrFail($cotizacion);
        $this->authorizeCotizacion($cotizacion);

        if (! $cotizacion->puedeCrearEntradaAnticipada()) {
            return response()->json([
                'success' => false,
                'message' => 'La cotización debe estar aceptada o enviada y tener partidas con cantidad.',
            ], 422);
        }

        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'fecha_recepcion' => 'required|date',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.detalle_id' => 'required|integer',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad_recibida' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario_estimado' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'confirmar' => 'nullable|boolean',
        ], [
            'proveedor_id.required' => 'Seleccione un proveedor para este grupo.',
            'productos.required' => 'El grupo debe tener al menos una partida.',
            'productos.min' => 'El grupo debe tener al menos una partida.',
            'productos.*.cantidad_recibida.min' => 'La cantidad recibida debe ser mayor a cero.',
        ]);

        $usadas = $this->cantidadesEnEntradasActivasPorDetalle($cotizacion);
        $detallesPorId = $cotizacion->detalles->keyBy('id');
        $lineas = [];

        foreach ($validated['productos'] as $linea) {
            $detalle = $detallesPorId->get((int) $linea['detalle_id']);
            if (! $detalle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Una de las partidas no pertenece a esta cotización.',
                ], 422);
            }

            $productoId = (int) $linea['producto_id'];
            if ((int) $detalle->producto_id !== $productoId) {
                $producto = Producto::where('activo', true)->find($productoId);
                if (! $producto) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Uno de los productos ya no está activo en el catálogo.',
                    ], 422);
                }
                $detalle->update([
                    'producto_id' => $producto->id,
                    'codigo' => $producto->codigo,
                    'es_producto_manual' => false,
                ]);
            }

            $yaUsada = (float) ($usadas[$detalle->id] ?? 0);
            $pendiente = max(0, (float) $detalle->cantidad - $yaUsada);
            $cantidad = (float) $linea['cantidad_recibida'];
            if ($cantidad > $pendiente + 0.001) {
                return response()->json([
                    'success' => false,
                    'message' => 'La cantidad de «'.\Illuminate\Support\Str::limit((string) $detalle->descripcion, 80)
                        .'» excede el pendiente ('.$pendiente.').',
                ], 422);
            }

            $lineas[] = [
                'producto_id' => $productoId,
                'cotizacion_detalle_id' => $detalle->id,
                'descripcion' => $linea['descripcion'],
                'cantidad_ordenada' => (float) $detalle->cantidad,
                'cantidad_recibida' => $cantidad,
                'precio_unitario_estimado' => (float) $linea['precio_unitario_estimado'],
                'descuento_porcentaje' => (float) ($linea['descuento_porcentaje'] ?? 0),
                'tasa_iva' => $linea['tasa_iva'] ?? null,
            ];

            // Reserva local para validar varias líneas del mismo detalle en el mismo request.
            $usadas[$detalle->id] = $yaUsada + $cantidad;
        }

        $obsBase = trim((string) ($validated['observaciones'] ?? ''));
        $obsRef = 'Desde cotización '.$cotizacion->folio;
        $observaciones = $obsBase === ''
            ? $obsRef
            : ($obsBase.(str_contains($obsBase, $cotizacion->folio) ? '' : ' · '.$obsRef));

        try {
            $ea = $service->crearDirecta((int) $validated['proveedor_id'], $lineas, [
                'fecha_recepcion' => $validated['fecha_recepcion'],
                'observaciones' => $observaciones,
                'moneda' => $cotizacion->moneda ?? 'MXN',
                'tipo_cambio' => $cotizacion->tipo_cambio ?? 1,
                'cotizacion_id' => $cotizacion->id,
            ]);

            if ($request->boolean('confirmar')) {
                $ea = $service->confirmar($ea);
                $msg = 'Entrada '.$ea->folio.' confirmada. Mercancía registrada en inventario.';
            } else {
                $msg = 'Entrada '.$ea->folio.' guardada en borrador.';
            }

            $ea->load('proveedor');
            $cotizacionFresh = $cotizacion->fresh(['detalles.producto']);
            $lineasPayload = $this->lineasWizardEntradaAnticipada($cotizacionFresh);
            $entradasPayload = $this->entradasPreviasDesdeCotizacion($cotizacionFresh);

            return response()->json([
                'success' => true,
                'message' => $msg,
                'entrada' => [
                    'id' => $ea->id,
                    'folio' => $ea->folio,
                    'estado' => $ea->estado,
                    'estado_etiqueta' => $ea->etiquetaEstado(),
                    'proveedor' => $ea->proveedor?->nombre,
                    'url' => route('entradas-anticipadas.show', $ea->id),
                ],
                'lineas' => $lineasPayload,
                'entradas_previas' => $entradasPayload,
                'pendientes' => collect($lineasPayload)
                    ->filter(fn ($l) => $l['tiene_producto'] && (float) $l['pendiente'] > 0.001)
                    ->count(),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * @return array<int, float> detalle_id => cantidad ya en EA activas
     */
    private function cantidadesEnEntradasActivasPorDetalle(Cotizacion $cotizacion): array
    {
        return EntradaAnticipadaDetalle::query()
            ->whereNotNull('cotizacion_detalle_id')
            ->whereHas('entradaAnticipada', function ($q) use ($cotizacion) {
                $q->where('cotizacion_id', $cotizacion->id)
                    ->where('estado', '!=', 'cancelada');
            })
            ->selectRaw('cotizacion_detalle_id, SUM(cantidad_recibida) as total')
            ->groupBy('cotizacion_detalle_id')
            ->pluck('total', 'cotizacion_detalle_id')
            ->map(fn ($v) => (float) $v)
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function lineasWizardEntradaAnticipada(Cotizacion $cotizacion): array
    {
        $usadas = $this->cantidadesEnEntradasActivasPorDetalle($cotizacion);

        return $cotizacion->detalles
            ->filter(fn ($d) => (float) $d->cantidad > 0)
            ->values()
            ->map(function (CotizacionDetalle $d) use ($usadas) {
                $producto = $d->producto;
                $tasaIva = $producto
                    ? EntradaAnticipadaDetalle::resolverTasaIva($producto, null)
                    : ($d->tasa_iva !== null ? (float) $d->tasa_iva : 0.16);
                $costoSugerido = $producto ? (float) ($producto->costo ?? 0) : 0.0;
                $cantidad = (float) $d->cantidad;
                $usada = (float) ($usadas[$d->id] ?? 0);
                $pendiente = max(0, round($cantidad - $usada, 2));

                return [
                    'detalle_id' => $d->id,
                    'producto_id' => $d->producto_id,
                    'codigo' => $producto?->codigo ?? $d->codigo,
                    'descripcion' => $d->descripcion,
                    'unidad' => $d->unidad ?? $producto?->unidad ?? 'PZA',
                    'cantidad' => $cantidad,
                    'cantidad_en_ea' => $usada,
                    'pendiente' => $pendiente,
                    'precio_venta' => $d->precioUnitarioVentaCalculado(),
                    'precio_unitario_estimado' => $costoSugerido,
                    'costo_catalogo' => $costoSugerido,
                    'tasa_iva' => $tasaIva,
                    'tiene_producto' => (bool) $d->producto_id,
                    // Campos de UI (paso 2 multi-proveedor); el cliente los gestiona.
                    'proveedor_id' => null,
                    'proveedor_etiqueta' => null,
                    'cantidad_grupo' => $pendiente,
                ];
            })
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function entradasPreviasDesdeCotizacion(Cotizacion $cotizacion): array
    {
        return EntradaAnticipada::with('proveedor')
            ->where('cotizacion_id', $cotizacion->id)
            ->where('estado', '!=', 'cancelada')
            ->orderByDesc('id')
            ->get()
            ->map(fn (EntradaAnticipada $ea) => [
                'id' => $ea->id,
                'folio' => $ea->folio,
                'estado' => $ea->estado,
                'estado_etiqueta' => $ea->etiquetaEstado(),
                'proveedor' => $ea->proveedor?->nombre,
                'url' => route('entradas-anticipadas.show', $ea->id),
            ])
            ->all();
    }

    /**
     * @return array{id:int,codigo:string,nombre:string,costo:float,tasa_iva:float|null}
     */
    private function payloadProductoAsignado(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'codigo' => (string) $producto->codigo,
            'nombre' => (string) $producto->nombre,
            'costo' => (float) ($producto->costo ?? 0),
            'tasa_iva' => EntradaAnticipadaDetalle::resolverTasaIva($producto, null),
        ];
    }

    /**
     * Siguiente número libre del consecutivo PSI- (incluye soft-deleted).
     */
    private function obtenerSiguientePsiNumDesde(int $desde): int
    {
        $usados = Producto::withTrashed()
            ->where('codigo', 'like', 'PSI-%')
            ->pluck('codigo')
            ->map(function ($codigo) {
                if (! is_string($codigo)) {
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

    /**
     * @return string|null nombre del producto activo si similar_text > 80% y casi idéntico.
     */
    private function nombreProductoActivoSiDescripcionSuperaSimilitud(string $descripcion): ?string
    {
        $desc = mb_strtoupper(trim($descripcion));
        if (mb_strlen($desc) < 10) {
            return null;
        }

        foreach (Producto::query()->where('activo', true)->select(['id', 'nombre', 'descripcion'])->cursor() as $p) {
            foreach ([$p->nombre, $p->descripcion] as $campo) {
                if (! is_string($campo) || trim($campo) === '') {
                    continue;
                }
                $cmp = mb_strtoupper(trim($campo));
                if (mb_strlen($cmp) < 10) {
                    continue;
                }
                $percent = 0.0;
                similar_text($desc, $cmp, $percent);
                if ($percent > 80 && $this->sonDescripcionesCasiIdenticasParaBloqueoSimilitud($desc, $cmp)) {
                    return (string) $p->nombre;
                }
            }
        }

        return null;
    }

    private function sonDescripcionesCasiIdenticasParaBloqueoSimilitud(string $a, string $b): bool
    {
        $na = preg_replace('/\s+/u', ' ', $a) ?? $a;
        $nb = preg_replace('/\s+/u', ' ', $b) ?? $b;
        if ($na === $nb) {
            return true;
        }
        $lenA = mb_strlen($na);
        $lenB = mb_strlen($nb);
        if ($lenA === 0 || $lenB === 0) {
            return false;
        }
        $ratio = min($lenA, $lenB) / max($lenA, $lenB);

        return $ratio >= 0.85;
    }

    /**
     * Eliminar cotización de forma permanente (libera el folio).
     */
    public function destroy($cotizacion)
    {
        DB::beginTransaction();
        try {
            $cotizacion = $cotizacion instanceof Cotizacion
                ? $cotizacion
                : Cotizacion::query()->findOrFail($cotizacion);

            $this->authorizeCotizacion($cotizacion);

            if (!$cotizacion->puedeEliminarse()) {
                DB::rollBack();

                return back()->with('error', 'Esta cotización no puede eliminarse (solo borrador, rechazada o vencida)');
            }

            // Eliminar PDF, adjuntos internos e imágenes de partidas si existen
            if ($cotizacion->pdf_path && file_exists(storage_path('app/' . $cotizacion->pdf_path))) {
                unlink(storage_path('app/' . $cotizacion->pdf_path));
            }

            $cotizacion->load(['detalles', 'adjuntos']);
            foreach ($cotizacion->adjuntos as $adjunto) {
                $adjunto->eliminarDelDisco();
                $adjunto->delete();
            }
            foreach ($cotizacion->detalles as $detalle) {
                $detalle->eliminarImagenesDelDisco();
            }

            // Hard delete: libera folio (el soft delete lo dejaba ocupado por el unique)
            $cotizacion->forceDelete();

            DB::commit();

            return redirect()->route('cotizaciones.index')
                ->with('success', 'Cotización eliminada permanentemente');

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }

    /**
     * API: Búsqueda de clientes
     */
    public function buscarClientes(Request $request)
    {
        $search = $request->get('q', '');

        $clientes = Cliente::activos()
            ->when($search, function ($query) use ($search) {
                $query->buscar($search);
            })
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'rfc', 'email', 'dias_credito', 'forma_pago', 'tipo_persona']);

        return response()->json($clientes);
    }

    /**
     * API: Búsqueda de productos, sugerencias y catálogo Truper (para cotizaciones create/edit)
     */
    public function buscarProductos(Request $request)
    {
        $search = trim($request->get('q', ''));
        $resultados = [];

        // Productos del catálogo (mínimo 2 caracteres)
        if (strlen($search) >= 2) {
            $productos = Producto::where('activo', true)
                ->where(function ($query) use ($search) {
                    $query->where('nombre', 'like', '%' . addcslashes($search, '%_\\') . '%')
                        ->orWhere('codigo', 'like', '%' . addcslashes($search, '%_\\') . '%');
                })
                ->limit(10)
                ->get(['id', 'codigo', 'nombre', 'unidad', 'precio_venta', 'tasa_iva', 'tipo_factor', 'objeto_impuesto', 'tipo_impuesto']);

            foreach ($productos as $p) {
                $resultados[] = [
                    'tipo' => 'producto',
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'unidad' => $p->unidad ?? 'PZA',
                    'precio_venta' => (float) $p->precio_venta,
                    'tasa_iva' => ($p->tipo_factor ?? 'Tasa') === 'Exento' ? null : (float) $p->tasa_iva,
                ];
            }
        }

        // Sugerencias (mínimo 2 caracteres, coherente con búsqueda de producto)
        if (strlen($search) >= 2) {
            $term = '%' . addcslashes($search, '%_\\') . '%';
            $sugerencias = Sugerencia::query()
                ->where(function ($q) use ($term) {
                    $q->where('descripcion', 'like', $term)
                        ->orWhere('codigo', 'like', $term);
                })
                ->orderBy('descripcion')
                ->limit(10)
                ->get(['id', 'codigo', 'descripcion', 'unidad', 'precio_unitario']);

            foreach ($sugerencias as $s) {
                $resultados[] = [
                    'tipo' => 'sugerencia',
                    'id' => $s->id,
                    'codigo' => $s->codigo ?? '',
                    'nombre' => $s->descripcion,
                    'unidad' => $s->unidad ?? 'PZA',
                    'precio_unitario' => (float) $s->precio_unitario,
                ];
            }
        }

        return response()->json($resultados);
    }

    /**
     * API: Listas de precios disponibles para un cliente
     */
    public function listasPreciosCliente(Request $request)
    {
        $clienteId = $request->get('cliente_id');
        if (!$clienteId) {
            return response()->json([]);
        }
        $listas = ListaPrecio::activas()
            ->paraCliente((int) $clienteId)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'descripcion']);
        return response()->json($listas);
    }

    /**
     * API: Productos de una lista de precios con precio calculado
     */
    public function productosListaPrecio(Request $request)
    {
        $listaId = $request->get('lista_id');
        if (!$listaId) {
            return response()->json([]);
        }
        $lista = ListaPrecio::with(['detalles.producto'])->find($listaId);
        if (!$lista) {
            return response()->json([]);
        }
        $items = $lista->detalles->map(function ($d) {
            $p = $d->producto;
            if (!$p) return null;
            $precio = $d->precio_resultante;
            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'unidad' => $p->unidad ?? 'PZA',
                'precio' => $precio,
                'tasa_iva' => ($p->tipo_factor ?? 'Tasa') === 'Exento' ? null : (float) $p->tasa_iva,
                'tipo_factor' => $p->tipo_factor ?? 'Tasa',
                'objeto_impuesto' => $p->objeto_impuesto ?? '02',
                'tipo_impuesto' => $p->tipo_impuesto ?? '002',
            ];
        })->filter()->values()->all();
        return response()->json($items);
    }

    /**
     * API: Estadísticas
     */
    public function estadisticas()
    {
        $q = $this->queryCotizaciones();
        return response()->json([
            'borradores' => (clone $q)->estado('borrador')->count(),
            'enviadas' => (clone $q)->estado('enviada')->count(),
            'aceptadas' => (clone $q)->estado('aceptada')->count(),
            'por_vencer' => (clone $q)->porVencer()->count(),
            'vencidas' => (clone $q)->estado('vencida')->count(),
        ]);
    }

    /**
     * Procesar imágenes de una partida (máx. 3): conservar existentes y subir nuevas.
     *
     * @param  array<int, string>  $imagenesUsadas  Referencia para rastrear rutas en uso tras edición
     * @return array<int, string>|null
     */
    private function procesarImagenesPartida(Request $request, int $index, int $cotizacionId, array &$imagenesUsadas): ?array
    {
        $mantenerInput = array_values(array_filter(array_map(
            static fn ($p) => is_string($p) ? trim($p) : '',
            (array) $request->input("productos.{$index}.imagenes_mantener", [])
        )));

        $paths = [];
        foreach ($mantenerInput as $path) {
            if ($path === '' || ! $this->esRutaImagenCotizacionValida($path, $cotizacionId)) {
                continue;
            }
            if (! Storage::disk('public')->exists($path)) {
                continue;
            }
            $paths[] = $path;
        }

        $files = array_values(array_filter($request->file("productos.{$index}.imagenes", []) ?: []));
        $maxNuevos = max(0, 3 - count($paths));
        $files = array_slice($files, 0, $maxNuevos);

        foreach ($files as $file) {
            $paths[] = $file->store('cotizaciones/imagenes/'.$cotizacionId, 'public');
        }

        $paths = array_values(array_slice($paths, 0, 3));
        foreach ($paths as $path) {
            $imagenesUsadas[] = $path;
        }

        return $paths !== [] ? $paths : null;
    }

    private function esRutaImagenCotizacionValida(string $path, int $cotizacionId): bool
    {
        $prefix = 'cotizaciones/imagenes/'.$cotizacionId.'/';

        return str_starts_with($path, $prefix) && ! str_contains($path, '..');
    }

    /**
     * @param  array<int, string>  $imagenesAntiguas
     * @param  array<int, string>  $imagenesUsadas
     */
    private function eliminarImagenesHuerfanas(array $imagenesAntiguas, array $imagenesUsadas): void
    {
        $usadas = array_unique($imagenesUsadas);
        foreach (array_unique($imagenesAntiguas) as $path) {
            if (! in_array($path, $usadas, true)) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}