<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CotizacionController.php

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Empresa;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\CotizacionEnviada;

class CotizacionController extends Controller
{
    /**
     * Listado de cotizaciones
     */
    public function index(Request $request)
    {
        $query = Cotizacion::with(['cliente', 'usuario']);

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

        // Actualizar estado de vencidas automáticamente
        Cotizacion::vencidas()->update(['estado' => 'vencida']);

        $cotizaciones = $query->orderBy('created_at', 'desc')->paginate(20);

        // Estadísticas
        $estadisticas = [
            'borradores' => Cotizacion::estado('borrador')->count(),
            'enviadas' => Cotizacion::estado('enviada')->count(),
            'aceptadas' => Cotizacion::estado('aceptada')->count(),
            'por_vencer' => Cotizacion::porVencer()->count(),
        ];

        $clientes = Cliente::activos()->orderBy('nombre')->get();

        return view('cotizaciones.index', compact('cotizaciones', 'estadisticas', 'clientes'));
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
        $folio = Cotizacion::generarFolio();

        // Modo edición
        if ($request->has('id')) {
            $cotizacion = Cotizacion::with('detalles.producto')->findOrFail($request->id);

            if (!$cotizacion->puedeEditarse()) {
                return redirect()->route('cotizaciones.show', $cotizacion->id)
                    ->with('error', 'Esta cotización no puede editarse');
            }
        }

        return view('cotizaciones.create', compact('empresa', 'folio', 'cotizacion'));
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
            'condiciones_pago' => 'nullable|string',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.es_producto_manual' => 'nullable|boolean',
        ]);

        DB::beginTransaction();
        try {
            $cliente = Cliente::findOrFail($validated['cliente_id']);
            $empresa = Empresa::principal();

            // Calcular totales
            $subtotalGeneral = 0;
            $descuentoGeneral = 0;
            $ivaGeneral = 0;

            foreach ($validated['productos'] as $item) {
                $importes = CotizacionDetalle::calcularImportes($item);
                $subtotalGeneral += $importes['subtotal'];
                $descuentoGeneral += $importes['descuento_monto'];
                $ivaGeneral += $importes['iva_monto'];
            }

            $totalGeneral = ($subtotalGeneral - $descuentoGeneral) + $ivaGeneral;

            // Crear o actualizar cotización
            $cotizacionId = $request->input('cotizacion_id');

            if ($cotizacionId) {
                $cotizacion = Cotizacion::findOrFail($cotizacionId);

                if (!$cotizacion->puedeEditarse()) {
                    throw new \Exception('Esta cotización no puede editarse');
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
                'cliente_municipio' => $cliente->municipio,
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
                'total' => $totalGeneral,
                
                // Condiciones de pago
                'tipo_venta' => $validated['tipo_venta'],
                'dias_credito_aplicados' => $validated['tipo_venta'] === 'credito' 
                    ? ($validated['dias_credito'] ?? 0) 
                    : 0,
                'condiciones_pago' => $validated['condiciones_pago'],
                'observaciones' => $validated['observaciones'],
            ]);

            $cotizacion->save();

            // Crear detalles
            foreach ($validated['productos'] as $index => $item) {
                $producto = null;
                if (!empty($item['producto_id'])) {
                    $producto = Producto::find($item['producto_id']);
                }

                CotizacionDetalle::create([
                    'cotizacion_id' => $cotizacion->id,
                    'producto_id' => $producto?->id,
                    'codigo' => $producto?->codigo ?? 'MANUAL',
                    'descripcion' => $item['descripcion'],
                    'es_producto_manual' => $item['es_producto_manual'] ?? false,
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento_porcentaje' => $item['descuento_porcentaje'] ?? 0,
                    'tasa_iva' => $item['tasa_iva'] ?? null,
                    'orden' => $index,
                ]);
            }

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
            'usuario'
        ])->findOrFail($id);

        return view('cotizaciones.show', compact('cotizacion'));
    }



    /**
     * Aceptar cotización
     */
    public function aceptar($id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);

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
     * Convertir a factura
     */
    public function convertirFactura($id)
    {
        DB::beginTransaction();
        try {
            $cotizacion = Cotizacion::with('detalles')->findOrFail($id);

            if (!$cotizacion->puedeFacturarse()) {
                throw new \Exception('Esta cotización no puede facturarse');
            }

            $empresa = Empresa::principal();

            // Crear factura (similar al código que ya tienes)
            // Aquí irá la lógica de conversión que ya implementaste
            // en el controlador de facturas

            // Por ahora, solo marcamos como facturada
            $cotizacion->marcarComoFacturada();

            DB::commit();

            return redirect()->route('facturas.index')
                ->with('success', 'Cotización convertida a factura exitosamente');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al convertir: ' . $e->getMessage());
        }
    }

    /**
     * Eliminar cotización
     */
    public function destroy($id)
    {
        try {
            $cotizacion = Cotizacion::findOrFail($id);

            if (!$cotizacion->puedeEliminarse()) {
                return back()->with('error', 'Esta cotización no puede eliminarse');
            }

            // Eliminar PDF si existe
            if ($cotizacion->pdf_path && file_exists(storage_path('app/' . $cotizacion->pdf_path))) {
                unlink(storage_path('app/' . $cotizacion->pdf_path));
            }

            $cotizacion->delete();

            return redirect()->route('cotizaciones.index')
                ->with('success', 'Cotización eliminada exitosamente');

        } catch (\Exception $e) {
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
            ->where(function($query) use ($search) {
                $query->where('nombre', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'nombre', 'rfc', 'email', 'dias_credito']);

        return response()->json($clientes);
    }

    /**
     * API: Búsqueda de productos
     */
    public function buscarProductos(Request $request)
    {
        $search = $request->get('q', '');

        $productos = Producto::where('activo', true)
            ->where(function($query) use ($search) {
                $query->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'precio_venta', 'tasa_iva']);

        return response()->json($productos);
    }

    /**
     * API: Estadísticas
     */
    public function estadisticas()
    {
        return response()->json([
            'borradores' => Cotizacion::estado('borrador')->count(),
            'enviadas' => Cotizacion::estado('enviada')->count(),
            'aceptadas' => Cotizacion::estado('aceptada')->count(),
            'por_vencer' => Cotizacion::porVencer()->count(),
            'vencidas' => Cotizacion::estado('vencida')->count(),
        ]);
    }
}