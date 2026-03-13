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
use App\Helpers\IsrResicoHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
        $fechaDesde = $request->get('fecha_desde', now()->startOfMonth()->format('Y-m-d'));
        $fechaHasta = $request->get('fecha_hasta', now()->format('Y-m-d'));
        $clienteId = $request->get('cliente_id');
        $productoId = $request->get('producto_id');
        $facturaId = $request->get('factura_id');

        $query = FacturaDetalle::with(['factura.cliente', 'producto'])
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

        $clientes = Cliente::activos()->orderBy('nombre')->get(['id', 'nombre']);
        $productos = Producto::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']);
        $facturas = Factura::where('estado', 'timbrada')
            ->whereDate('fecha_emision', '>=', $fechaDesde)
            ->whereDate('fecha_emision', '<=', $fechaHasta)
            ->when($clienteId, fn ($q) => $q->where('cliente_id', $clienteId))
            ->orderBy('fecha_emision', 'desc')
            ->get(['id', 'serie', 'folio', 'fecha_emision', 'cliente_id']);

        return view('reportes.utilidad', compact(
            'filas', 'totalIngreso', 'totalCosto', 'totalUtilidad', 'margen',
            'fechaDesde', 'fechaHasta', 'clienteId', 'productoId', 'facturaId',
            'clientes', 'productos', 'facturas'
        ));
    }
}
