<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\NotaCredito;
use App\Models\NotaCreditoDetalle;
use App\Models\NotaCreditoImpuesto;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaImpuesto;
use App\Models\Devolucion;
use App\Models\DevolucionDetalle;
use App\Models\Empresa;
use App\Models\FormaPago;
use App\Models\MetodoPago;
use App\Services\PACServiceInterface;
use App\Services\PDFService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotaCreditoController extends Controller
{
    public function __construct(
        protected PACServiceInterface $pacService,
        protected PDFService $pdfService
    ) {}

    public function index(Request $request)
    {
        $estado = $request->get('estado');
        $cliente_id = $request->get('cliente_id');

        $notas = NotaCredito::with(['factura', 'cliente', 'usuario'])
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($cliente_id, fn ($q) => $q->where('cliente_id', $cliente_id))
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $clientes = \App\Models\Cliente::activos()->orderBy('nombre')->get();

        return view('notas-credito.index', compact('notas', 'estado', 'cliente_id', 'clientes'));
    }

    public function create(Request $request)
    {
        $factura_id = $request->get('factura_id');
        $devolucion_id = $request->get('devolucion_id');

        if ($devolucion_id) {
            $devolucion = Devolucion::with(['factura.detalles.producto', 'factura.detalles.impuestos', 'detalles.facturaDetalle'])->findOrFail($devolucion_id);
            if (!$devolucion->puedeGenerarNotaCredito()) {
                return redirect()->route('devoluciones.show', $devolucion->id)
                    ->with('error', 'Autoriza la devolución antes de generar la nota de crédito.');
            }
            $facturaDevol = $devolucion->factura;
            $ncBorrador = $facturaDevol ? NotaCredito::where('factura_id', $facturaDevol->id)->where('estado', 'borrador')->first() : null;
            if ($ncBorrador) {
                return redirect()->route('notas-credito.show', $ncBorrador->id)
                    ->with('info', 'Esta factura ya tiene una nota de crédito en borrador. Complétala o elimínala antes de crear otra.');
            }
            return view('notas-credito.create', [
                'devolucion' => $devolucion,
                'factura' => $devolucion->factura,
                'cantidadesDevueltas' => collect(),
                'cantidadesAcreditadas' => collect(),
                'devolucionesAnteriores' => collect(),
            ]);
        }

        if (!$factura_id) {
            return redirect()->route('notas-credito.index')->with('error', 'Indica la factura o la devolución.');
        }

        $factura = Factura::with(['detalles.producto', 'detalles.impuestos', 'cliente', 'cuentaPorCobrar'])->findOrFail($factura_id);
        if (!$factura->estaTimbrada()) {
            return redirect()->route('facturas.show', $factura->id)->with('error', 'Solo se pueden emitir notas de crédito de facturas timbradas.');
        }

        // Si ya existe NC en borrador para esta factura, redirigir al show (evitar duplicados)
        $ncBorrador = NotaCredito::where('factura_id', $factura->id)->where('estado', 'borrador')->first();
        if ($ncBorrador) {
            return redirect()->route('notas-credito.show', $ncBorrador->id)
                ->with('info', 'Esta factura ya tiene una nota de crédito en borrador. Complétala o elimínala antes de crear otra.');
        }

        // Para NC directa (sin devolución): saldo restante acreditable
        $saldoAcreditable = (float) $factura->saldo_acreditable;
        if ($saldoAcreditable < 0.01) {
            return redirect()->route('facturas.show', $factura->id)->with('error', 'La factura no tiene saldo pendiente para acreditar. El saldo restante es cero.');
        }

        return view('notas-credito.create', [
            'factura' => $factura,
            'devolucion' => null,
            'saldoAcreditable' => $saldoAcreditable,
            'cantidadesDevueltas' => collect(),
            'cantidadesAcreditadas' => collect(),
            'devolucionesAnteriores' => collect(),
        ]);
    }

    public function store(Request $request)
    {
        $rules = [
            'factura_id' => 'required|exists:facturas,id',
            'devolucion_id' => 'nullable|exists:devoluciones,id',
            'fecha_emision' => 'required|date',
            'motivo_cfdi' => 'required|string|in:01,02,03,04,05,06,07',
            'forma_pago' => 'required|string|in:23,15,03',
            'observaciones' => 'nullable|string',
        ];
        if ($request->filled('devolucion_id')) {
            $rules['lineas'] = 'required|array|min:1';
            $rules['lineas.*.factura_detalle_id'] = 'required|exists:facturas_detalle,id';
            $rules['lineas.*.cantidad'] = 'required|numeric|min:0.01';
        } else {
            $rules['monto_a_acreditar'] = 'required|numeric|min:0.01';
        }
        $validated = $request->validate($rules);

        $factura = Factura::with(['detalles.impuestos', 'cliente', 'empresa', 'cuentaPorCobrar'])->findOrFail($validated['factura_id']);
        if (!$factura->uuid) {
            return back()->withInput()->with('error', 'La factura debe estar timbrada (tener UUID).');
        }

        // No permitir crear otra NC si ya existe una en borrador para esta factura
        $ncBorradorExistente = NotaCredito::where('factura_id', $factura->id)->where('estado', 'borrador')->first();
        if ($ncBorradorExistente) {
            return redirect()->route('notas-credito.show', $ncBorradorExistente->id)
                ->with('error', 'Esta factura ya tiene una nota de crédito en borrador. No se puede crear otra hasta completarla o eliminarla.');
        }

        // Cantidades disponibles (coherente con devoluciones: cant facturada - devoluciones - NCs sin devolución)
        $cantidadesDevueltas = DevolucionDetalle::whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
            ->whereHas('devolucion', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', '!=', 'cancelada'))
            ->selectRaw('factura_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
            ->groupBy('factura_detalle_id')
            ->pluck('total_devuelto', 'factura_detalle_id')
            ->map(fn ($v) => (float) $v);

        $cantidadesAcreditadas = NotaCreditoDetalle::whereNotNull('factura_detalle_id')
            ->whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
            ->whereHas('notaCredito', fn ($q) => $q->where('factura_id', $factura->id)->whereNull('devolucion_id'))
            ->selectRaw('factura_detalle_id, SUM(cantidad) as total_acreditado')
            ->groupBy('factura_detalle_id')
            ->pluck('total_acreditado', 'factura_detalle_id')
            ->map(fn ($v) => (float) $v);

        $empresa = Empresa::principal();
        if (!$empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura los datos de la empresa.');
        }

        // NC directa (monto sobre saldo): construir lineas prorrateando monto
        if (empty($validated['devolucion_id']) && isset($validated['monto_a_acreditar'])) {
            $monto = (float) $validated['monto_a_acreditar'];
            $saldoAcreditable = (float) $factura->saldo_acreditable;
            if ($monto > $saldoAcreditable) {
                return back()->withInput()->with('error', 'El monto a acreditar no puede exceder el saldo restante: $' . number_format($saldoAcreditable, 2, '.', ','));
            }
            if ($monto < 0.01) {
                return back()->withInput()->with('error', 'El monto a acreditar debe ser mayor a 0.');
            }
            $factor = $factura->total > 0 ? ($monto / (float) $factura->total) : 0;
            $lineasProrrateadas = [];
            foreach ($factura->detalles as $fd) {
                $baseLinea = (float) $fd->importe - (float) ($fd->descuento ?? 0);
                $importeNc = round($baseLinea * $factor, 2);
                if ($importeNc < 0.01) {
                    continue;
                }
                $cantNc = (float) $fd->valor_unitario > 0
                    ? round($importeNc / (float) $fd->valor_unitario, 4)
                    : 0;
                if ($cantNc < 0.0001) {
                    continue;
                }
                $lineasProrrateadas[] = [
                    'factura_detalle_id' => $fd->id,
                    'cantidad' => $cantNc,
                ];
            }
            if (empty($lineasProrrateadas)) {
                $primerDetalle = $factura->detalles->first();
                if ($primerDetalle && (float) $primerDetalle->valor_unitario > 0) {
                    $basePrimera = (float) $primerDetalle->importe - (float) ($primerDetalle->descuento ?? 0);
                    $cantFallback = $basePrimera > 0 ? max(0.01, round(($monto / (float) $factura->total) * $basePrimera / (float) $primerDetalle->valor_unitario, 4)) : 0.01;
                    $lineasProrrateadas[] = [
                        'factura_detalle_id' => $primerDetalle->id,
                        'cantidad' => $cantFallback,
                    ];
                }
            }
            $validated['lineas'] = $lineasProrrateadas;
            $cantidadesDevueltas = collect();
            $cantidadesAcreditadas = collect();
        } else {
            $cantidadesDevueltas = DevolucionDetalle::whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
                ->whereHas('devolucion', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', '!=', 'cancelada'))
                ->selectRaw('factura_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
                ->groupBy('factura_detalle_id')
                ->pluck('total_devuelto', 'factura_detalle_id')
                ->map(fn ($v) => (float) $v);

            $cantidadesAcreditadas = NotaCreditoDetalle::whereNotNull('factura_detalle_id')
                ->whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
                ->whereHas('notaCredito', fn ($q) => $q->where('factura_id', $factura->id)->whereNull('devolucion_id'))
                ->selectRaw('factura_detalle_id, SUM(cantidad) as total_acreditado')
                ->groupBy('factura_detalle_id')
                ->pluck('total_acreditado', 'factura_detalle_id')
                ->map(fn ($v) => (float) $v);
        }

        $folio = $empresa->folio_nota_credito ?? 1;
        $serie = $empresa->serie_nota_credito ?? 'NC';

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;
            $retencionTotal = 0;

            foreach ($validated['lineas'] as $lin) {
                $fd = FacturaDetalle::with('impuestos')->find($lin['factura_detalle_id']);
                if (!$fd || $fd->factura_id != $factura->id) {
                    continue;
                }
                $cant = (float) $lin['cantidad'];
                $yaDevuelto = $cantidadesDevueltas->get($fd->id, 0);
                $yaAcreditado = $cantidadesAcreditadas->get($fd->id, 0);
                $cantPendiente = (float) $fd->cantidad - $yaDevuelto - $yaAcreditado;
                if ($cant > $cantPendiente) {
                    $cant = max(0, $cantPendiente);
                }
                if ($cant < 0.01) {
                    continue;
                }
                $valorUnit = (float) $fd->valor_unitario;
                $importe = round($cant * $valorUnit, 2);
                $desc = round((float) ($fd->descuento ?? 0) * ($cant / (float) $fd->cantidad), 2);
                $baseImp = $importe - $desc;
                $subtotal += $importe;
                $descuentoTotal += $desc;
                foreach ($fd->impuestos as $imp) {
                    $factor = $cant / (float) $fd->cantidad;
                    $importeImp = round((float) $imp->importe * $factor, 2);
                    if (($imp->tipo ?? 'traslado') === 'retencion') {
                        $retencionTotal += $importeImp;
                    } else {
                        $ivaTotal += $importeImp;
                    }
                }
            }

            if ($subtotal < 0.01) {
                DB::rollBack();
                return back()->withInput()->with('error', 'El total debe ser mayor a 0.');
            }

            // Total = Subtotal - Descuento + Traslados - Retenciones (incluye ISR cuando emisor RESICO + receptor PM)
            $total = round($subtotal - $descuentoTotal + $ivaTotal - $retencionTotal, 2);

            $nota = NotaCredito::create([
                'serie' => $serie,
                'folio' => $folio,
                'tipo_comprobante' => 'E',
                'estado' => 'borrador',
                'factura_id' => $factura->id,
                'cliente_id' => $factura->cliente_id,
                'empresa_id' => $empresa->id,
                'devolucion_id' => $validated['devolucion_id'] ?? null,
                'rfc_emisor' => $empresa->rfc,
                'nombre_emisor' => $empresa->razon_social,
                'regimen_fiscal_emisor' => $empresa->regimen_fiscal ?? '601',
                'rfc_receptor' => $factura->rfc_receptor,
                'nombre_receptor' => $factura->nombre_receptor,
                'uso_cfdi' => $factura->uso_cfdi,
                'regimen_fiscal_receptor' => $factura->regimen_fiscal_receptor,
                'domicilio_fiscal_receptor' => $factura->domicilio_fiscal_receptor,
                'lugar_expedicion' => $empresa->codigo_postal ?? '01000',
                'fecha_emision' => $validated['fecha_emision'],
                'forma_pago' => $validated['forma_pago'],
                'metodo_pago' => 'PUE',
                'moneda' => $factura->moneda ?? 'MXN',
                'tipo_cambio' => $factura->tipo_cambio ?? 1,
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'total' => $total,
                'motivo_cfdi' => $validated['motivo_cfdi'],
                'uuid_referencia' => $factura->uuid,
                'tipo_relacion' => '01',
                'observaciones' => $validated['observaciones'] ?? null,
                'usuario_id' => auth()->id(),
            ]);

            $orden = 0;
            foreach ($validated['lineas'] as $lin) {
                $fd = FacturaDetalle::with('impuestos')->find($lin['factura_detalle_id']);
                if (!$fd || $fd->factura_id != $factura->id) {
                    continue;
                }
                $cant = (float) $lin['cantidad'];
                $yaDevuelto = $cantidadesDevueltas->get($fd->id, 0);
                $yaAcreditado = $cantidadesAcreditadas->get($fd->id, 0);
                $cantPendiente = (float) $fd->cantidad - $yaDevuelto - $yaAcreditado;
                if ($cant > $cantPendiente) {
                    $cant = max(0, $cantPendiente);
                }
                if ($cant < 0.01) {
                    continue;
                }
                $valorUnit = (float) $fd->valor_unitario;
                $importe = round($cant * $valorUnit, 2);
                $desc = round((float) ($fd->descuento ?? 0) * ($cant / (float) $fd->cantidad), 2);
                $baseImp = $importe - $desc;

                $det = NotaCreditoDetalle::create([
                    'nota_credito_id' => $nota->id,
                    'factura_detalle_id' => $fd->id,
                    'producto_id' => $fd->producto_id,
                    'clave_prod_serv' => $fd->clave_prod_serv ?? '01010101',
                    'clave_unidad' => $fd->clave_unidad ?? 'H87',
                    'unidad' => $fd->unidad ?? 'Pieza',
                    'no_identificacion' => $fd->no_identificacion,
                    'descripcion' => $fd->descripcion,
                    'cantidad' => $cant,
                    'valor_unitario' => $valorUnit,
                    'importe' => $importe,
                    'descuento' => $desc,
                    'base_impuesto' => $baseImp,
                    'objeto_impuesto' => $fd->objeto_impuesto ?? '02',
                    'orden' => ++$orden,
                ]);

                foreach ($fd->impuestos as $imp) {
                    $factor = $cant / (float) $fd->cantidad;
                    NotaCreditoImpuesto::create([
                        'nota_credito_detalle_id' => $det->id,
                        'tipo' => $imp->tipo,
                        'impuesto' => $imp->impuesto,
                        'tipo_factor' => $imp->tipo_factor ?? 'Tasa',
                        'tasa_o_cuota' => $imp->tasa_o_cuota,
                        'base' => round((float) $imp->base * $factor, 2),
                        'importe' => round((float) $imp->importe * $factor, 2),
                    ]);
                }
            }

            $empresa->folio_nota_credito = ($empresa->folio_nota_credito ?? 1) + 1;
            $empresa->save();

            DB::commit();

            return redirect()->route('notas-credito.show', $nota->id)
                ->with('success', 'Nota de crédito creada en borrador. Emítela (timbrar) desde la ficha.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function show(NotaCredito $notaCredito)
    {
        $notaCredito->load(['factura', 'cliente', 'detalles.producto', 'detalles.impuestos', 'usuario', 'devolucion']);
        return view('notas-credito.show', compact('notaCredito'));
    }

    public function edit(NotaCredito $notaCredito)
    {
        if ($notaCredito->estado !== 'borrador') {
            return redirect()->route('notas-credito.show', $notaCredito->id)
                ->with('error', 'Solo se pueden editar notas de crédito en borrador.');
        }
        $notaCredito->load(['factura.detalles.producto', 'factura.detalles.impuestos', 'factura.cliente', 'factura.cuentaPorCobrar', 'detalles', 'devolucion.detalles.facturaDetalle']);
        $factura = $notaCredito->factura;
        $devolucion = $notaCredito->devolucion;
        $saldoAcreditable = $devolucion ? null : (float) $factura->saldo_acreditable;
        $cantidadesDevueltas = collect();
        $cantidadesAcreditadas = collect();
        $devolucionesAnteriores = collect();
        if ($devolucion) {
            $cantidadesDevueltas = DevolucionDetalle::whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
                ->whereHas('devolucion', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', '!=', 'cancelada'))
                ->selectRaw('factura_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
                ->groupBy('factura_detalle_id')
                ->pluck('total_devuelto', 'factura_detalle_id')
                ->map(fn ($v) => (float) $v);
            $cantidadesAcreditadas = NotaCreditoDetalle::whereNotNull('factura_detalle_id')
                ->whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
                ->whereHas('notaCredito', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', 'timbrada')->whereNull('devolucion_id'))
                ->selectRaw('factura_detalle_id, SUM(cantidad) as total_acreditado')
                ->groupBy('factura_detalle_id')
                ->pluck('total_acreditado', 'factura_detalle_id')
                ->map(fn ($v) => (float) $v);
        } else {
            $cantidadesAcreditadas = NotaCreditoDetalle::whereNotNull('factura_detalle_id')
                ->whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
                ->whereHas('notaCredito', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', 'timbrada')->whereNull('devolucion_id'))
                ->selectRaw('factura_detalle_id, SUM(cantidad) as total_acreditado')
                ->groupBy('factura_detalle_id')
                ->pluck('total_acreditado', 'factura_detalle_id')
                ->map(fn ($v) => (float) $v);
        }
        return view('notas-credito.edit', compact('notaCredito', 'factura', 'devolucion', 'saldoAcreditable', 'cantidadesDevueltas', 'cantidadesAcreditadas', 'devolucionesAnteriores'));
    }

    public function update(Request $request, NotaCredito $notaCredito)
    {
        if ($notaCredito->estado !== 'borrador') {
            return redirect()->route('notas-credito.show', $notaCredito->id)
                ->with('error', 'Solo se pueden editar notas de crédito en borrador.');
        }
        $rules = [
            'fecha_emision' => 'required|date',
            'motivo_cfdi' => 'required|string|in:01,02,03,04,05,06,07',
            'forma_pago' => 'required|string|in:23,15,03',
            'observaciones' => 'nullable|string',
        ];
        if ($notaCredito->devolucion_id) {
            $rules['lineas'] = 'required|array|min:1';
            $rules['lineas.*.factura_detalle_id'] = 'required|exists:facturas_detalle,id';
            $rules['lineas.*.cantidad'] = 'required|numeric|min:0.01';
        } else {
            $rules['monto_a_acreditar'] = 'required|numeric|min:0.01';
        }
        $validated = $request->validate($rules);
        $factura = $notaCredito->factura()->with(['detalles.impuestos', 'cliente', 'empresa', 'cuentaPorCobrar'])->firstOrFail();

        $cantidadesDevueltas = DevolucionDetalle::whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
            ->whereHas('devolucion', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', '!=', 'cancelada'))
            ->selectRaw('factura_detalle_id, SUM(cantidad_devuelta) as total_devuelto')
            ->groupBy('factura_detalle_id')
            ->pluck('total_devuelto', 'factura_detalle_id')
            ->map(fn ($v) => (float) $v);

        $cantidadesAcreditadas = NotaCreditoDetalle::whereNotNull('factura_detalle_id')
            ->whereIn('factura_detalle_id', $factura->detalles->pluck('id'))
            ->whereHas('notaCredito', fn ($q) => $q->where('factura_id', $factura->id)->where('estado', 'timbrada')->where('id', '!=', $notaCredito->id)->whereNull('devolucion_id'))
            ->selectRaw('factura_detalle_id, SUM(cantidad) as total_acreditado')
            ->groupBy('factura_detalle_id')
            ->pluck('total_acreditado', 'factura_detalle_id')
            ->map(fn ($v) => (float) $v);

        if (empty($notaCredito->devolucion_id) && isset($validated['monto_a_acreditar'])) {
            $monto = (float) $validated['monto_a_acreditar'];
            $saldoAcreditable = (float) $factura->saldo_acreditable;
            if ($monto > $saldoAcreditable) {
                return back()->withInput()->with('error', 'El monto no puede exceder el saldo restante: $' . number_format($saldoAcreditable, 2, '.', ','));
            }
            $factor = $factura->total > 0 ? ($monto / (float) $factura->total) : 0;
            $lineasProrrateadas = [];
            foreach ($factura->detalles as $fd) {
                $baseLinea = (float) $fd->importe - (float) ($fd->descuento ?? 0);
                $importeNc = round($baseLinea * $factor, 2);
                if ($importeNc < 0.01) continue;
                $cantNc = (float) $fd->valor_unitario > 0 ? round($importeNc / (float) $fd->valor_unitario, 4) : 0;
                if ($cantNc < 0.0001) continue;
                $lineasProrrateadas[] = ['factura_detalle_id' => $fd->id, 'cantidad' => $cantNc];
            }
            if (empty($lineasProrrateadas)) {
                $primer = $factura->detalles->first();
                if ($primer && (float) $primer->valor_unitario > 0) {
                    $basePrimera = (float) $primer->importe - (float) ($primer->descuento ?? 0);
                    $cantFallback = $basePrimera > 0 ? max(0.01, round(($monto / (float) $factura->total) * $basePrimera / (float) $primer->valor_unitario, 4)) : 0.01;
                    $lineasProrrateadas[] = ['factura_detalle_id' => $primer->id, 'cantidad' => $cantFallback];
                }
            }
            $validated['lineas'] = $lineasProrrateadas;
        }

        DB::beginTransaction();
        try {
            foreach ($notaCredito->detalles as $det) {
                NotaCreditoImpuesto::where('nota_credito_detalle_id', $det->id)->delete();
            }
            NotaCreditoDetalle::where('nota_credito_id', $notaCredito->id)->delete();

            $subtotal = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;
            $retencionTotal = 0;
            foreach ($validated['lineas'] as $lin) {
                $fd = FacturaDetalle::with('impuestos')->find($lin['factura_detalle_id']);
                if (!$fd || $fd->factura_id != $factura->id) continue;
                $cant = (float) $lin['cantidad'];
                $yaDevuelto = $cantidadesDevueltas->get($fd->id, 0);
                $yaAcreditado = $cantidadesAcreditadas->get($fd->id, 0);
                $cantPendiente = (float) $fd->cantidad - $yaDevuelto - $yaAcreditado;
                if ($cant > $cantPendiente) $cant = max(0, $cantPendiente);
                if ($cant < 0.01) continue;
                $valorUnit = (float) $fd->valor_unitario;
                $importe = round($cant * $valorUnit, 2);
                $desc = round((float) ($fd->descuento ?? 0) * ($cant / (float) $fd->cantidad), 2);
                $subtotal += $importe;
                $descuentoTotal += $desc;
                foreach ($fd->impuestos as $imp) {
                    $factor = $cant / (float) $fd->cantidad;
                    $importeImp = round((float) $imp->importe * $factor, 2);
                    ($imp->tipo ?? 'traslado') === 'retencion' ? ($retencionTotal += $importeImp) : ($ivaTotal += $importeImp);
                }
            }
            if ($subtotal < 0.01) {
                DB::rollBack();
                return back()->withInput()->with('error', 'El total debe ser mayor a 0.');
            }
            $total = round($subtotal - $descuentoTotal + $ivaTotal - $retencionTotal, 2);

            $notaCredito->update([
                'fecha_emision' => $validated['fecha_emision'],
                'forma_pago' => $validated['forma_pago'],
                'motivo_cfdi' => $validated['motivo_cfdi'],
                'observaciones' => $validated['observaciones'] ?? null,
                'subtotal' => $subtotal,
                'descuento' => $descuentoTotal,
                'total' => $total,
            ]);

            $orden = 0;
            foreach ($validated['lineas'] as $lin) {
                $fd = FacturaDetalle::with('impuestos')->find($lin['factura_detalle_id']);
                if (!$fd || $fd->factura_id != $factura->id) continue;
                $cant = (float) $lin['cantidad'];
                $yaDevuelto = $cantidadesDevueltas->get($fd->id, 0);
                $yaAcreditado = $cantidadesAcreditadas->get($fd->id, 0);
                $cantPendiente = (float) $fd->cantidad - $yaDevuelto - $yaAcreditado;
                if ($cant > $cantPendiente) $cant = max(0, $cantPendiente);
                if ($cant < 0.01) continue;
                $valorUnit = (float) $fd->valor_unitario;
                $importe = round($cant * $valorUnit, 2);
                $desc = round((float) ($fd->descuento ?? 0) * ($cant / (float) $fd->cantidad), 2);
                $det = NotaCreditoDetalle::create([
                    'nota_credito_id' => $notaCredito->id,
                    'factura_detalle_id' => $fd->id,
                    'producto_id' => $fd->producto_id,
                    'clave_prod_serv' => $fd->clave_prod_serv ?? '01010101',
                    'clave_unidad' => $fd->clave_unidad ?? 'H87',
                    'unidad' => $fd->unidad ?? 'Pieza',
                    'no_identificacion' => $fd->no_identificacion,
                    'descripcion' => $fd->descripcion,
                    'cantidad' => $cant,
                    'valor_unitario' => $valorUnit,
                    'importe' => $importe,
                    'descuento' => $desc,
                    'base_impuesto' => $importe - $desc,
                    'objeto_impuesto' => $fd->objeto_impuesto ?? '02',
                    'orden' => ++$orden,
                ]);
                foreach ($fd->impuestos as $imp) {
                    $factor = $cant / (float) $fd->cantidad;
                    NotaCreditoImpuesto::create([
                        'nota_credito_detalle_id' => $det->id,
                        'tipo' => $imp->tipo,
                        'impuesto' => $imp->impuesto,
                        'tipo_factor' => $imp->tipo_factor ?? 'Tasa',
                        'tasa_o_cuota' => $imp->tasa_o_cuota,
                        'base' => round((float) $imp->base * $factor, 2),
                        'importe' => round((float) $imp->importe * $factor, 2),
                    ]);
                }
            }
            DB::commit();
            return redirect()->route('notas-credito.show', $notaCredito->id)
                ->with('success', 'Nota de crédito actualizada.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', 'Error: ' . $e->getMessage());
        }
    }

    public function destroy(NotaCredito $notaCredito)
    {
        if ($notaCredito->estado !== 'borrador') {
            return redirect()->route('notas-credito.show', $notaCredito->id)
                ->with('error', 'Solo se pueden eliminar notas de crédito en borrador.');
        }
        $facturaId = $notaCredito->factura_id;
        DB::beginTransaction();
        try {
            foreach ($notaCredito->detalles as $det) {
                NotaCreditoImpuesto::where('nota_credito_detalle_id', $det->id)->delete();
            }
            NotaCreditoDetalle::where('nota_credito_id', $notaCredito->id)->delete();
            $notaCredito->delete();
            DB::commit();
            return redirect()->route('facturas.show', $facturaId)
                ->with('success', 'Nota de crédito en borrador eliminada correctamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('notas-credito.show', $notaCredito->id)
                ->with('error', 'Error al eliminar: ' . $e->getMessage());
        }
    }

    public function timbrar(NotaCredito $notaCredito)
    {
        if (!$notaCredito->puedeTimbrar()) {
            return back()->with('error', 'Esta nota de crédito no puede ser timbrada.');
        }

        DB::beginTransaction();
        try {
            $resultado = $this->pacService->timbrarNotaCredito($notaCredito);
            if (!$resultado['success']) {
                throw new \Exception($resultado['message']);
            }

            $notaCredito->update([
                'estado' => 'timbrada',
                'uuid' => $resultado['uuid'],
                'pac_cfdi_id' => $resultado['pac_cfdi_id'] ?? null,
                'fecha_timbrado' => $resultado['fecha_timbrado'] ?? now(),
                'no_certificado_sat' => $resultado['no_certificado_sat'] ?? null,
                'sello_cfdi' => $resultado['sello_cfdi'] ?? null,
                'sello_sat' => $resultado['sello_sat'] ?? null,
                'cadena_original' => $resultado['cadena_original'] ?? null,
                'xml_content' => $resultado['xml'] ?? null,
            ]);

            if (!empty($resultado['xml'])) {
                $dir = storage_path('app/notas-credito/' . now()->format('Y/m'));
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                $path = $dir . '/' . $notaCredito->folio_completo . '.xml';
                file_put_contents($path, $resultado['xml']);
                $notaCredito->update(['xml_path' => 'notas-credito/' . now()->format('Y/m') . '/' . $notaCredito->folio_completo . '.xml']);
            }

            $pdfPath = $this->pdfService->generarNotaCreditoPDF($notaCredito);
            $notaCredito->update(['pdf_path' => $pdfPath]);

            $notaCredito->cliente->actualizarSaldo();

            DB::commit();

            return redirect()->route('notas-credito.show', $notaCredito->id)
                ->with('success', ($resultado['message'] ?? 'Nota de crédito timbrada.') . ' PDF generado.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error al timbrar: ' . $e->getMessage());
        }
    }

    public function verPDF(NotaCredito $notaCredito)
    {
        if (!$notaCredito->pdf_path || !file_exists(storage_path('app/' . $notaCredito->pdf_path))) {
            $pdfPath = $this->pdfService->generarNotaCreditoPDF($notaCredito);
            $notaCredito->update(['pdf_path' => $pdfPath]);
        }
        return response()->file(storage_path('app/' . $notaCredito->pdf_path));
    }

    public function descargarPDF(NotaCredito $notaCredito)
    {
        if (!$notaCredito->pdf_path || !file_exists(storage_path('app/' . $notaCredito->pdf_path))) {
            $pdfPath = $this->pdfService->generarNotaCreditoPDF($notaCredito);
            $notaCredito->update(['pdf_path' => $pdfPath]);
        }
        return response()->download(
            storage_path('app/' . $notaCredito->pdf_path),
            $notaCredito->folio_completo . '.pdf'
        );
    }

    public function descargarXML(NotaCredito $notaCredito)
    {
        if (!$notaCredito->xml_path) {
            return back()->with('error', 'XML no disponible');
        }
        $path = storage_path('app/' . $notaCredito->xml_path);
        if (!file_exists($path)) {
            return back()->with('error', 'Archivo no encontrado');
        }
        return response()->download($path, $notaCredito->folio_completo . '.xml');
    }
}
