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
            return view('notas-credito.create', ['devolucion' => $devolucion, 'factura' => $devolucion->factura]);
        }

        if (!$factura_id) {
            return redirect()->route('notas-credito.index')->with('error', 'Indica la factura o la devolución.');
        }

        $factura = Factura::with(['detalles.producto', 'detalles.impuestos', 'cliente'])->findOrFail($factura_id);
        if (!$factura->estaTimbrada()) {
            return redirect()->route('facturas.show', $factura->id)->with('error', 'Solo se pueden emitir notas de crédito de facturas timbradas.');
        }

        return view('notas-credito.create', ['factura' => $factura, 'devolucion' => null]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'factura_id' => 'required|exists:facturas,id',
            'devolucion_id' => 'nullable|exists:devoluciones,id',
            'fecha_emision' => 'required|date',
            'motivo_cfdi' => 'required|string|in:01,02,03,04,05,06,07',
            'forma_pago' => 'required|string|in:23,15,03',
            'observaciones' => 'nullable|string',
            'lineas' => 'required|array|min:1',
            'lineas.*.factura_detalle_id' => 'required|exists:facturas_detalle,id',
            'lineas.*.cantidad' => 'required|numeric|min:0.01',
        ]);

        $factura = Factura::with(['detalles.impuestos', 'cliente', 'empresa'])->findOrFail($validated['factura_id']);
        if (!$factura->uuid) {
            return back()->withInput()->with('error', 'La factura debe estar timbrada (tener UUID).');
        }

        $empresa = Empresa::principal();
        if (!$empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura los datos de la empresa.');
        }

        $folio = $empresa->folio_nota_credito ?? 1;
        $serie = $empresa->serie_nota_credito ?? 'NC';

        DB::beginTransaction();
        try {
            $subtotal = 0;
            $descuentoTotal = 0;
            $ivaTotal = 0;

            foreach ($validated['lineas'] as $lin) {
                $fd = FacturaDetalle::with('impuestos')->find($lin['factura_detalle_id']);
                if (!$fd || $fd->factura_id != $factura->id) {
                    continue;
                }
                $cant = (float) $lin['cantidad'];
                if ($cant > (float) $fd->cantidad) {
                    $cant = (float) $fd->cantidad;
                }
                $valorUnit = (float) $fd->valor_unitario;
                $importe = round($cant * $valorUnit, 2);
                $desc = round((float) ($fd->descuento ?? 0) * ($cant / (float) $fd->cantidad), 2);
                $baseImp = $importe - $desc;
                $subtotal += $importe;
                $descuentoTotal += $desc;
                foreach ($fd->impuestos as $imp) {
                    $factor = $cant / (float) $fd->cantidad;
                    $ivaTotal += round((float) $imp->importe * $factor, 2);
                }
            }

            if ($subtotal < 0.01) {
                DB::rollBack();
                return back()->withInput()->with('error', 'El total debe ser mayor a 0.');
            }

            $total = round($subtotal - $descuentoTotal + $ivaTotal, 2);

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
                if ($cant > (float) $fd->cantidad) {
                    $cant = (float) $fd->cantidad;
                }
                $valorUnit = (float) $fd->valor_unitario;
                $importe = round($cant * $valorUnit, 2);
                $desc = round((float) ($fd->descuento ?? 0) * ($cant / (float) $fd->cantidad), 2);
                $baseImp = $importe - $desc;

                $det = NotaCreditoDetalle::create([
                    'nota_credito_id' => $nota->id,
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
