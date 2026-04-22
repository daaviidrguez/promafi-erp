<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ComplementoPago;
use App\Models\Factura;
use App\Models\FacturaCompra;
use App\Models\FacturaDetalle;
use App\Models\OrdenCompra;
use App\Models\Empresa;
use App\Helpers\IsrResicoHelper;
use Carbon\Carbon;
use Illuminate\Http\Request;

class TableroAnualController extends Controller
{
    /**
     * Tablero anual diseñado para RESICO (626): 12 tarjetas por mes con total ventas, subtotal,
     * ingresos cobrados (base para ISR RESICO), IVA, ISR estimado por tabla de tasas y utilidad.
     * Ingresos = PUE por fecha de emisión; PPD por complementos cobrados en el mes (criterio RESICO).
     */
    public function index(Request $request)
    {
        $año = (int) ($request->get('año') ?? now()->year);
        $empresa = Empresa::principal();
        $aplicaResico = ($empresa->tipo_persona ?? 'moral') === 'fisica'
            && ($empresa->regimen_fiscal ?? '') === config('isr_resico.regimen_clave', '626');

        $meses = [];
        $nombresMeses = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
            7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        for ($mes = 1; $mes <= 12; $mes++) {
            $inicio = Carbon::create($año, $mes, 1)->startOfDay();
            $fin = $inicio->copy()->endOfMonth();

            // Ingresos del mes (base sin IVA): en RESICO es la base para ISR
            $ventasSinIva = 0.0;
            $ivaTraslado = 0.0;

            // PUE: ingresos e IVA por fecha de emisión (cobro en una exhibición)
            $facturasPue = Factura::where('estado', 'timbrada')
                ->where('metodo_pago', 'PUE')
                ->whereBetween('fecha_emision', [$inicio, $fin])
                ->with('detalles.impuestos')
                ->get();
            foreach ($facturasPue as $f) {
                $ventasSinIva += $this->baseFactura($f);
                $ivaTraslado += $this->ivaTrasladadoFactura($f);
            }

            // PPD: ingresos e IVA se reconocen al cobrar (complementos con fecha_emision en el mes)
            $complementos = ComplementoPago::where('estado', 'timbrado')
                ->whereBetween('fecha_emision', [$inicio, $fin])
                ->with(['pagosRecibidos.documentosRelacionados.factura.detalles.impuestos'])
                ->get();
            foreach ($complementos as $comp) {
                foreach ($comp->pagosRecibidos as $pago) {
                    foreach ($pago->documentosRelacionados as $doc) {
                        $factura = $doc->factura;
                        if ($factura && ($factura->metodo_pago ?? '') === 'PPD' && (float) $factura->total > 0) {
                            $baseF = $this->baseFactura($factura);
                            $prop = (float) $doc->monto_pagado / (float) $factura->total;
                            $ventasSinIva += $baseF * $prop;
                            $ivaTraslado += $this->ivaTrasladadoFactura($factura) * $prop;
                        }
                    }
                }
            }

            // IVA acreditable: órdenes y facturas de compra del mes
            $ordenes = OrdenCompra::whereIn('estado', ['aceptada', 'recibida', 'convertida_compra'])
                ->whereBetween('fecha', [$inicio, $fin])
                ->get();
            $facturasCompra = FacturaCompra::whereBetween('fecha_emision', [$inicio, $fin])
                ->with('detalles.impuestos')
                ->get();
            $ivaAcreditable = (float) $ordenes->sum('iva');
            foreach ($facturasCompra as $fc) {
                foreach ($fc->detalles ?? [] as $d) {
                    foreach ($d->impuestos ?? [] as $imp) {
                        if (($imp->tipo ?? '') === 'traslado' && ($imp->impuesto ?? '') === '002') {
                            $ivaAcreditable += (float) $imp->importe;
                        }
                    }
                }
            }
            $ivaPagar = max(0, $ivaTraslado - $ivaAcreditable);

            // ISR RESICO: tabla por rangos de ingreso mensual (sobre ingresos cobrados, no sobre utilidad)
            $isrEstimado = 0.0;
            if ($aplicaResico) {
                $isrEstimado = IsrResicoHelper::calcularIsr($ventasSinIva);
            }

            // Utilidad: ventas (base) - costos de lo vendido (detalles × costo unitario producto)
            $costosVenta = 0.0;
            $detallesMes = FacturaDetalle::whereHas('factura', function ($q) use ($inicio, $fin) {
                $q->where('estado', 'timbrada')->whereBetween('fecha_emision', [$inicio, $fin]);
            })->with('producto')->get();
            foreach ($detallesMes as $d) {
                $costoUnit = (float) ($d->producto->costo_promedio ?? $d->producto->costo ?? 0);
                $costosVenta += (float) $d->cantidad * $costoUnit;
            }
            $utilidad = $ventasSinIva - $costosVenta;

            $meses[$mes] = [
                'nombre' => $nombresMeses[$mes],
                'subtotal' => round($ventasSinIva, 2),
                'total_ventas' => round($ventasSinIva + $ivaTraslado, 2),
                'ventas_sin_iva' => round($ventasSinIva, 2),
                'iva_traslado' => round($ivaTraslado, 2),
                'iva_acreditable' => round($ivaAcreditable, 2),
                'iva_a_pagar' => round($ivaPagar, 2),
                'isr_estimado_resico' => round($isrEstimado, 2),
                'utilidad' => round($utilidad, 2),
            ];
        }

        return view('tablero-anual.index', compact('año', 'meses', 'nombresMeses', 'aplicaResico'));
    }

    private function baseFactura(Factura $factura): float
    {
        return max(0, (float) $factura->subtotal - (float) ($factura->descuento ?? 0));
    }

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
}
