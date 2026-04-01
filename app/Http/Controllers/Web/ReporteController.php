<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ComplementoPago;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\OrdenCompra;
use App\Models\FacturaCompra;
use App\Models\Producto;
use App\Models\Empresa;
use App\Exports\ReporteUtilidadExport;
use App\Helpers\IsrResicoHelper;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReporteController extends Controller
{
    /**
     * Reporte fiscal mensual: ingresos cobrados (sin IVA), IVA trasladado, IVA acreditable, IVA a pagar, ISR RESICO.
     * Los ingresos cobrados son la base gravable (subtotal - descuento); el IVA no forma parte por ser trasladado al cliente.
     */
    public function fiscal(Request $request)
    {
        $mes = (int) ($request->get('mes') ?? now()->month);
        $año = (int) ($request->get('año') ?? now()->year);
        $empresa = Empresa::principal();

        $inicio = Carbon::create($año, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        // Ingresos cobrados: base gravable SIN IVA (el IVA es trasladado al cliente, no forma parte del ingreso ISR)
        $complementos = ComplementoPago::where('estado', 'timbrado')
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->with(['pagosRecibidos.documentosRelacionados.factura.detalles.impuestos'])
            ->get();

        $ingresosCobrados = 0.0;  // Subtotal - descuento (sin IVA)
        $ivaTrasladado = 0.0;

        // PUE: ingresos e IVA se reconocen al timbrar (pago en una exhibición). Base = subtotal - descuento
        $facturasPue = Factura::where('estado', 'timbrada')
            ->where('metodo_pago', 'PUE')
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->with('detalles.impuestos')
            ->get();
        foreach ($facturasPue as $f) {
            $ingresosCobrados += $this->baseFactura($f);
            $ivaTrasladado += $this->ivaTrasladadoFactura($f);
        }

        // PPD: ingresos e IVA se reconocen conforme se pagan (complementos). Proporción de base sobre monto pagado
        foreach ($complementos as $comp) {
            foreach ($comp->pagosRecibidos as $pago) {
                foreach ($pago->documentosRelacionados as $doc) {
                    $factura = $doc->factura;
                    if ($factura && ($factura->metodo_pago ?? '') === 'PPD' && (float) $factura->total > 0) {
                        $baseFactura = $this->baseFactura($factura);
                        $prop = (float) $doc->monto_pagado / (float) $factura->total;
                        $ingresosCobrados += $baseFactura * $prop;
                        $ivaFactura = $this->ivaTrasladadoFactura($factura);
                        $ivaTrasladado += $ivaFactura * $prop;
                    }
                }
            }
        }

        // IVA acreditable: de órdenes de compra y facturas de compra del mes
        $ordenes = OrdenCompra::whereIn('estado', ['aceptada', 'recibida'])
            ->whereBetween('fecha', [$inicio, $fin])
            ->get();
        $facturasCompra = FacturaCompra::whereBetween('fecha_emision', [$inicio, $fin])
            ->with('detalles.impuestos')
            ->get();
        $ivaOrdenes = $ordenes->sum(fn ($o) => (float) ($o->iva ?? 0));
        $ivaFacturasCompra = $facturasCompra->sum(function ($fc) {
            $total = 0;
            foreach ($fc->detalles ?? [] as $d) {
                foreach ($d->impuestos ?? [] as $imp) {
                    if (($imp->tipo ?? '') === 'traslado' && ($imp->impuesto ?? '') === '002') {
                        $total += (float) $imp->importe;
                    }
                }
            }
            return $total;
        });
        $ivaAcreditable = $ivaOrdenes + $ivaFacturasCompra;
        $comprasTotal = $ordenes->sum(fn ($o) => (float) $o->total) + $facturasCompra->sum(fn ($fc) => (float) $fc->total);

        $ivaPagar = max(0, $ivaTrasladado - $ivaAcreditable);

        // ISR RESICO: solo si empresa es persona física con régimen 626
        $isrEstimado = 0.0;
        $aplicaResico = ($empresa->tipo_persona ?? 'moral') === 'fisica' && ($empresa->regimen_fiscal ?? '') === config('isr_resico.regimen_clave', '626');
        if ($aplicaResico) {
            $isrEstimado = IsrResicoHelper::calcularIsr($ingresosCobrados);
        }

        return view('reportes.fiscal', compact(
            'mes', 'año', 'ingresosCobrados', 'ivaTrasladado', 'ivaAcreditable', 'ivaPagar',
            'isrEstimado', 'aplicaResico', 'complementos', 'ordenes', 'facturasCompra', 'comprasTotal'
        ));
    }

    /**
     * Base gravable de factura (subtotal - descuento, sin IVA).
     * El IVA es trasladado al cliente y no forma parte de la base del ISR.
     */
    private function baseFactura(Factura $factura): float
    {
        return max(0, (float) $factura->subtotal - (float) ($factura->descuento ?? 0));
    }

    /**
     * IVA trasladado total de una factura (desde facturas_impuestos)
     */
    private function ivaTrasladadoFactura(Factura $factura): float
    {
        $total = 0.0;
        foreach ($factura->detalles ?? [] as $d) {
            foreach ($d->impuestos ?? [] as $imp) {
                if (($imp->tipo ?? '') === 'traslado' && ($imp->impuesto ?? '') === '002') {
                    $total += (float) $imp->importe;
                }
            }
        }
        return $total;
    }

    /**
     * Reporte de ventas mensuales (facturas emitidas)
     */
    public function ventas(Request $request)
    {
        $mes = (int) ($request->get('mes') ?? now()->month);
        $año = (int) ($request->get('año') ?? now()->year);

        $inicio = Carbon::create($año, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        $facturas = Factura::where('estado', 'timbrada')
            ->whereBetween('fecha_emision', [$inicio, $fin])
            ->with(['cliente'])
            ->orderBy('fecha_emision')
            ->get();

        $totalVentas = $facturas->sum(fn ($f) => (float) $f->total);
        $subtotalVentas = $facturas->sum(fn ($f) => (float) $f->subtotal);
        $ivaVentas = 0.0;
        foreach ($facturas as $f) {
            $ivaVentas += $this->ivaTrasladadoFactura($f);
        }

        return view('reportes.ventas', compact('mes', 'año', 'facturas', 'totalVentas', 'subtotalVentas', 'ivaVentas'));
    }

    /**
     * Reporte de compras
     */
    public function compras(Request $request)
    {
        $mes = (int) ($request->get('mes') ?? now()->month);
        $año = (int) ($request->get('año') ?? now()->year);

        $inicio = Carbon::create($año, $mes, 1)->startOfDay();
        $fin = $inicio->copy()->endOfMonth();

        $ordenes = OrdenCompra::whereIn('estado', ['aceptada', 'recibida'])
            ->whereBetween('fecha', [$inicio, $fin])
            ->with(['proveedor'])
            ->orderBy('fecha')
            ->get();

        $facturasCompra = FacturaCompra::whereBetween('fecha_emision', [$inicio, $fin])
            ->with(['proveedor', 'detalles.impuestos'])
            ->orderBy('fecha_emision')
            ->get();

        $totalCompras = $ordenes->sum(fn ($o) => (float) $o->total) + $facturasCompra->sum(fn ($fc) => (float) $fc->total);
        $subtotalCompras = $ordenes->sum(fn ($o) => (float) $o->subtotal) + $facturasCompra->sum(fn ($fc) => (float) ($fc->subtotal ?? 0));
        $ivaCompras = $ordenes->sum(fn ($o) => (float) ($o->iva ?? 0))
            + $facturasCompra->sum(fn ($fc) => $fc->detalles->sum(fn ($d) => $d->impuestos->where('tipo', 'traslado')->where('impuesto', '002')->sum('importe')));

        $comprasMerge = collect()
            ->concat($ordenes->map(fn ($o) => (object) ['tipo' => 'orden', 'folio' => $o->folio, 'fecha' => $o->fecha, 'proveedor' => $o->proveedor->nombre ?? $o->proveedor_nombre ?? '-', 'total' => (float) $o->total, 'id' => $o->id, 'route' => 'ordenes-compra.show']))
            ->concat($facturasCompra->map(fn ($fc) => (object) ['tipo' => 'factura', 'folio' => $fc->folio_completo, 'fecha' => $fc->fecha_emision, 'proveedor' => $fc->proveedor->nombre ?? $fc->nombre_emisor ?? '-', 'total' => (float) ($fc->total ?? 0), 'id' => $fc->id, 'route' => 'compras.show']))
            ->sortBy('fecha');

        return view('reportes.compras', compact('mes', 'año', 'ordenes', 'facturasCompra', 'comprasMerge', 'totalCompras', 'subtotalCompras', 'ivaCompras'));
    }

    /**
     * Reporte de utilidad (ingresos - costos) con filtros
     */
    public function utilidad(Request $request)
    {
        $core = $this->construirDatosReporteUtilidad($request);

        $clientes = Cliente::activos()->orderBy('nombre')->get(['id', 'nombre']);
        $productos = Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']);
        $facturas = Factura::where('estado', 'timbrada')
            ->whereDate('fecha_emision', '>=', $core['fechaDesde'])
            ->whereDate('fecha_emision', '<=', $core['fechaHasta'])
            ->when($core['clienteId'], fn ($q) => $q->where('cliente_id', $core['clienteId']))
            ->orderBy('fecha_emision', 'desc')
            ->get(['id', 'serie', 'folio', 'fecha_emision', 'cliente_id']);

        return view('reportes.utilidad', array_merge($core, compact(
            'clientes', 'productos', 'facturas'
        )));
    }

    /**
     * Exportar reporte de utilidad (PDF o Excel) con los mismos filtros que la vista.
     */
    public function utilidadExport(Request $request)
    {
        foreach (['cliente_id', 'producto_id', 'factura_id'] as $clave) {
            if ($request->input($clave) === '' || $request->input($clave) === null) {
                $request->merge([$clave => null]);
            }
        }

        $validated = $request->validate([
            'formato' => 'required|in:pdf,xlsx',
            'fecha_desde' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
            'cliente_id' => 'nullable|exists:clientes,id',
            'producto_id' => 'nullable|exists:productos,id',
            'factura_id' => 'nullable|exists:facturas,id',
        ]);

        $core = $this->construirDatosReporteUtilidad($request);
        $lineas = $this->lineasExportablesUtilidad($core['filas']);

        $slug = 'reporte-utilidad_'.now()->format('Y-m-d_His');
        $etiquetaFiltros = $this->etiquetaFiltrosUtilidad($core);

        if ($validated['formato'] === 'xlsx') {
            return Excel::download(
                new ReporteUtilidadExport(
                    $lineas,
                    $core['totalIngreso'],
                    $core['totalCosto'],
                    $core['totalUtilidad'],
                    $core['margen']
                ),
                $slug.'.xlsx'
            );
        }

        $empresa = Empresa::principal();
        $html = view('pdf.reporte-utilidad', [
            'empresa' => $empresa,
            'lineas' => $lineas,
            'totalIngreso' => $core['totalIngreso'],
            'totalCosto' => $core['totalCosto'],
            'totalUtilidad' => $core['totalUtilidad'],
            'margen' => $core['margen'],
            'fechaDesde' => $core['fechaDesde'],
            'fechaHasta' => $core['fechaHasta'],
            'etiquetaFiltros' => $etiquetaFiltros,
        ])->render();

        $options = new Options;
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('letter', 'landscape');
        $dompdf->render();

        return response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$slug.'.pdf"',
        ]);
    }

    /**
     * @return array{
     *   filas: array<int, array{detalle: FacturaDetalle, ingreso: float, costo: float, utilidad: float}>,
     *   totalIngreso: float,
     *   totalCosto: float,
     *   totalUtilidad: float,
     *   margen: float,
     *   fechaDesde: string,
     *   fechaHasta: string,
     *   clienteId: mixed,
     *   productoId: mixed,
     *   facturaId: mixed
     * }
     */
    private function construirDatosReporteUtilidad(Request $request): array
    {
        $fechaDesde = $request->get('fecha_desde', now()->startOfMonth()->format('Y-m-d'));
        $fechaHasta = $request->get('fecha_hasta', now()->format('Y-m-d'));
        $clienteId = $request->get('cliente_id');
        $productoId = $request->get('producto_id');
        $facturaId = $request->get('factura_id');

        $query = FacturaDetalle::with(['factura.cliente', 'factura.cuentaPorCobrar', 'producto'])
            ->whereHas('factura', function ($q) use ($fechaDesde, $fechaHasta, $clienteId, $facturaId) {
                $q->where('estado', 'timbrada')
                    ->whereDate('fecha_emision', '>=', $fechaDesde)
                    ->whereDate('fecha_emision', '<=', $fechaHasta);
                if ($clienteId) {
                    $q->where('cliente_id', $clienteId);
                }
                if ($facturaId) {
                    $q->where('id', $facturaId);
                }
            });

        if ($productoId) {
            $query->where('producto_id', $productoId);
        }

        $detalles = $query->get()->sortBy('factura.fecha_emision')->values();

        $totalIngreso = 0.0;
        $totalCosto = 0.0;
        $filas = [];

        foreach ($detalles as $d) {
            $ingreso = (float) $d->importe;
            $costo = 0.0;
            if ($d->producto_id && $d->producto) {
                $costoUnitario = (float) ($d->producto->costo ?? $d->producto->costo_promedio ?? 0);
                $costo = $d->cantidad * $costoUnitario;
            }
            $utilidad = $ingreso - $costo;
            $totalIngreso += $ingreso;
            $totalCosto += $costo;

            $filas[] = [
                'detalle' => $d,
                'ingreso' => $ingreso,
                'costo' => $costo,
                'utilidad' => $utilidad,
            ];
        }

        $totalUtilidad = $totalIngreso - $totalCosto;
        $margen = $totalIngreso > 0 ? ($totalUtilidad / $totalIngreso) * 100 : 0;

        return [
            'filas' => $filas,
            'totalIngreso' => $totalIngreso,
            'totalCosto' => $totalCosto,
            'totalUtilidad' => $totalUtilidad,
            'margen' => $margen,
            'fechaDesde' => $fechaDesde,
            'fechaHasta' => $fechaHasta,
            'clienteId' => $clienteId,
            'productoId' => $productoId,
            'facturaId' => $facturaId,
        ];
    }

    /**
     * PUE (contado): se considera pagada al timbrar. PPD: según saldo de cuenta por cobrar.
     */
    private function etiquetaPagadaFactura(Factura $factura): string
    {
        if (($factura->metodo_pago ?? '') === 'PUE') {
            return 'Pagada';
        }
        $cx = $factura->cuentaPorCobrar;
        if ($cx) {
            return ((float) $cx->saldo_pendiente_real) <= 0.0000001 ? 'Pagada' : 'Pendiente';
        }

        return 'Pendiente';
    }

    /**
     * @param  array<int, array{detalle: FacturaDetalle, ingreso: float, costo: float, utilidad: float}>  $filas
     * @return array<int, array{factura: string, fecha: string, cliente: string, concepto: string, cantidad: float, ingreso: float, costo: float, utilidad: float, pagada: string}>
     */
    private function lineasExportablesUtilidad(array $filas): array
    {
        $lineas = [];
        foreach ($filas as $fila) {
            $d = $fila['detalle'];
            $factura = $d->factura;
            $folio = $factura->folio_completo ?? trim(($factura->serie ?? '').'-'.$factura->folio);
            if ($d->producto) {
                $concepto = ($d->producto->codigo ? $d->producto->codigo.' - ' : '')
                    .($d->descripcion ?? $d->producto->nombre);
            } else {
                $concepto = (string) ($d->descripcion ?? 'Concepto');
            }

            $lineas[] = [
                'factura' => $folio,
                'fecha' => $factura->fecha_emision->format('d/m/Y'),
                'cliente' => optional($factura->cliente)->nombre ?? (string) ($factura->nombre_receptor ?? ''),
                'concepto' => $concepto,
                'cantidad' => (float) $d->cantidad,
                'ingreso' => $fila['ingreso'],
                'costo' => $fila['costo'],
                'utilidad' => $fila['utilidad'],
                'pagada' => $this->etiquetaPagadaFactura($factura),
            ];
        }

        return $lineas;
    }

    private function etiquetaFiltrosUtilidad(array $core): string
    {
        $partes = [];
        if (! empty($core['clienteId'])) {
            $c = Cliente::find($core['clienteId']);
            if ($c) {
                $partes[] = 'Cliente: '.$c->nombre;
            }
        }
        if (! empty($core['productoId'])) {
            $p = Producto::find($core['productoId']);
            if ($p) {
                $partes[] = 'Producto: '.($p->codigo ? $p->codigo.' — ' : '').$p->nombre;
            }
        }
        if (! empty($core['facturaId'])) {
            $f = Factura::find($core['facturaId']);
            if ($f) {
                $partes[] = 'Factura: '.($f->folio_completo ?? trim(($f->serie ?? '').'-'.$f->folio));
            }
        }

        return implode(' · ', $partes);
    }
}
