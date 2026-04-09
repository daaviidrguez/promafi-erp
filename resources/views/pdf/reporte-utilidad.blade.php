<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de utilidad</title>
<style>
@page { margin: 8mm 10mm; size: letter landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 7pt; color: #1F2937; }
h1 { font-size: 11pt; color: #0B3C5D; margin: 0 0 6px 0; }
.meta { font-size: 7pt; color: #4B5563; margin-bottom: 8px; line-height: 1.35; }
.tbl { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
.tbl thead { background: #0B3C5D; color: #fff; }
.tbl th, .tbl td { padding: 3px 4px; border-bottom: 1px solid #E5E7EB; text-align: left; word-wrap: break-word; }
.tbl th.right, .tbl td.right { text-align: right; }
.tbl th.center, .tbl td.center { text-align: center; }
.tbl td { font-size: 6.5pt; }
.totals { margin-top: 10px; font-size: 8pt; text-align: right; }
.totals strong { color: #0B3C5D; }
.note { margin-top: 12px; font-size: 6.5pt; color: #6B7280; }
</style>
</head>
<body>

<h1>Reporte de utilidad</h1>
<div class="meta">
    @if($empresa)
        <strong>{{ $empresa->nombre_comercial ?? $empresa->razon_social }}</strong><br>
    @endif
    Período: {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}
    @if(!empty($etiquetaFiltros))
        <br>Filtros: {{ $etiquetaFiltros }}
    @endif
    <br>Generado: {{ now()->format('d/m/Y H:i') }}
</div>

<table class="tbl">
    <thead>
        <tr>
            <th style="width:5%;">Pedido</th>
            <th style="width:5%;">Factura</th>
            <th style="width:5%;">Fecha fact.</th>
            <th style="width:7%;">Cliente</th>
            <th style="width:8%;">Prod. / concepto</th>
            <th class="right" style="width:4%;">C. unit.</th>
            <th class="right" style="width:4%;">Venta u.</th>
            <th class="right" style="width:3%;">Marg. %</th>
            <th class="right" style="width:4%;">Util. u.</th>
            <th class="right" style="width:3%;">Cant.</th>
            <th class="right" style="width:4%;">Costo</th>
            <th class="right" style="width:4%;">IVA acr.</th>
            <th class="right" style="width:5%;">Costo c/IVA</th>
            <th class="right" style="width:4%;">Ingreso</th>
            <th class="right" style="width:4%;">Utilidad</th>
            <th class="center" style="width:4%;">Entreg.</th>
            <th class="center" style="width:4%;">Pagada</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lineas as $l)
        <tr>
            <td>{{ $l['oc'] ?? '—' }}</td>
            <td>{{ $l['factura'] }}</td>
            <td>{{ $l['fecha'] }}</td>
            <td>{{ Str::limit($l['cliente'], 28) }}</td>
            <td>{{ Str::limit($l['concepto'], 32) }}</td>
            <td class="right">${{ number_format($l['costo_unitario'] ?? 0, 4, '.', ',') }}</td>
            <td class="right">${{ number_format($l['ingreso_unitario'] ?? 0, 4, '.', ',') }}</td>
            <td class="right">{{ number_format($l['margen_pct'] ?? 0, 1) }}%</td>
            <td class="right">${{ number_format($l['utilidad_unitaria'] ?? 0, 4, '.', ',') }}</td>
            <td class="right">{{ number_format($l['cantidad'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['costo'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['iva_acreditable'] ?? 0, 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['costo_con_iva'] ?? 0, 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['ingreso'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['utilidad'], 2, '.', ',') }}</td>
            <td class="center">{{ $l['entregado_destino'] ?? 'No' }}</td>
            <td class="center">{{ $l['pagada'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="17" style="text-align:center;padding:14px;color:#6B7280;">Sin datos con los filtros aplicados.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="totals">
    <div><strong>Total ingresos:</strong> ${{ number_format($totalIngreso, 2, '.', ',') }}</div>
    <div><strong>Total costos:</strong> ${{ number_format($totalCosto, 2, '.', ',') }}</div>
    <div><strong>Utilidad:</strong> ${{ number_format($totalUtilidad, 2, '.', ',') }}</div>
    <div><strong>Margen:</strong> {{ number_format($margen, 1) }}%</div>
</div>

<p class="note">
    El costo se obtiene del producto (costo o costo promedio). Conceptos sin producto asignado tienen costo cero.
    <strong>Pedido:</strong> orden de compra capturada en la factura. <strong>IVA acr.:</strong> 16% sobre costo; <strong>costo c/IVA:</strong> costo + IVA acreditable.
    <strong>Entregado:</strong> mismo criterio que logística para la línea de factura. <strong>Pagada:</strong> PUE como Pagada; PPD según saldo de cuenta por cobrar.
</p>

</body>
</html>
