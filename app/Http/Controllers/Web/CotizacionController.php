<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CotizacionController.php

use App\Http\Controllers\Controller;
use App\Models\Cotizacion;
use App\Models\CotizacionDetalle;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Sugerencia;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaImpuesto;
use App\Models\CuentaPorCobrar;
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
        $folio = $empresa ? $empresa->obtenerSiguienteFolioCotizacion() : 'COT-0001';

        // Modo edición: cargar cotización con detalles ordenados para repoblar el formulario
        if ($request->has('id')) {
            $cotizacion = Cotizacion::with(['detalles' => fn ($q) => $q->orderBy('orden'), 'detalles.producto'])
                ->findOrFail($request->id);

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
            'productos.*.unidad' => 'nullable|string|max:10',
            'productos.*.precio_unitario' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.es_producto_manual' => 'nullable|boolean',
            'productos.*.sugerencia_id' => 'nullable|exists:sugerencias,id',
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
                'total' => $totalGeneral,
                
                // Condiciones de pago
                'tipo_venta' => $validated['tipo_venta'],
                'dias_credito_aplicados' => $validated['tipo_venta'] === 'credito' 
                    ? ($validated['dias_credito'] ?? 0) 
                    : 0,
                'condiciones_pago' => $validated['condiciones_pago'],
                'observaciones' => $validated['observaciones'],
            ]);

            // Al editar, invalidar PDF para que se regenere con los datos actuales
            if ($cotizacionId) {
                $cotizacion->pdf_path = null;
            }

            $cotizacion->save();

            // Crear detalles
            foreach ($validated['productos'] as $index => $item) {
                $producto = null;
                if (!empty($item['producto_id'])) {
                    $producto = Producto::find($item['producto_id']);
                }

                $sugerenciaId = !empty($item['sugerencia_id']) ? (int) $item['sugerencia_id'] : null;
                $esManual = $item['es_producto_manual'] ?? false;
                $unidadDetalle = !empty($item['unidad']) ? $item['unidad'] : ($producto?->unidad ?? 'PZA');

                // Si es partida manual sin sugerencia elegida, guardar/actualizar en sugerencias para futuras cotizaciones
                if ($esManual && !$sugerenciaId && !empty(trim($item['descripcion'] ?? ''))) {
                    $sugerencia = Sugerencia::firstOrCreate(
                        [
                            'descripcion' => trim($item['descripcion']),
                            'unidad' => $unidadDetalle,
                        ],
                        [
                            'codigo' => null,
                            'precio_unitario' => $item['precio_unitario'],
                        ]
                    );
                    $sugerencia->update(['precio_unitario' => $item['precio_unitario']]);
                    $sugerenciaId = $sugerencia->id;
                }

                CotizacionDetalle::create([
                    'cotizacion_id' => $cotizacion->id,
                    'producto_id' => $producto?->id,
                    'sugerencia_id' => $sugerenciaId,
                    'codigo' => $producto?->codigo ?? '-',
                    'descripcion' => $item['descripcion'],
                    'es_producto_manual' => $esManual,
                    'cantidad' => $item['cantidad'],
                    'unidad' => $unidadDetalle,
                    'precio_unitario' => $item['precio_unitario'],
                    'descuento_porcentaje' => $item['descuento_porcentaje'] ?? 0,
                    'tasa_iva' => $item['tasa_iva'] ?? null,
                    'orden' => $index,
                ]);
                // Actualizar precio más reciente en la sugerencia para próximas cotizaciones
                if ($sugerenciaId) {
                    Sugerencia::where('id', $sugerenciaId)->update(['precio_unitario' => $item['precio_unitario']]);
                }
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
     * Crear productos desde partidas manuales (cotización aceptada/enviada).
     * Crea un producto por cada detalle manual con descripción, unidad y precio de venta.
     */
    public function crearProductosDesdeManuales($id)
    {
        DB::beginTransaction();
        try {
            $cotizacion = Cotizacion::with('detalles')->findOrFail($id);

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
                    'precio_venta' => (float) $detalle->precio_unitario,
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
        DB::beginTransaction();
        try {
            $cotizacion = Cotizacion::with(['detalles.producto', 'cliente', 'empresa'])->findOrFail($id);

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

            $folio = $empresa->folio_factura;
            $metodoPago = strtolower($cotizacion->tipo_venta ?? 'contado') === 'credito' ? 'PPD' : 'PUE';
            $formaPago = '03'; // Transferencia por defecto
            $usoCfdi = $cliente->uso_cfdi_default ?? 'G03';

            $factura = Factura::create([
                'serie' => $empresa->serie_factura,
                'folio' => $folio,
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
                $valorUnitario = (float) $d->precio_unitario;
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
                // El descuento de inventario se hace al timbrar la factura, no en borrador
            }

            $empresa->incrementarFolioFactura();

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
            ->get(['id', 'codigo', 'nombre', 'unidad', 'precio_venta', 'tasa_iva', 'tipo_factor', 'objeto_impuesto', 'tipo_impuesto']);

        // Coherencia con datos fiscales: Exento → tasa_iva null para cotización/factura
        $productos = $productos->map(function ($p) {
            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'unidad' => $p->unidad ?? 'PZA',
                'precio_venta' => $p->precio_venta,
                'tasa_iva' => ($p->tipo_factor ?? 'Tasa') === 'Exento' ? null : (float) $p->tasa_iva,
                'tipo_factor' => $p->tipo_factor ?? 'Tasa',
                'objeto_impuesto' => $p->objeto_impuesto ?? '02',
                'tipo_impuesto' => $p->tipo_impuesto ?? '002',
            ];
        });

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