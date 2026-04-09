<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de utilidad</title>
<style>
@page { margin: 7mm 8mm; size: letter landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 6.5pt; color: #1F2937; }
.meta { font-size: 6.5pt; color: #4B5563; margin-bottom: 8px; line-height: 1.35; }
.tbl { width: 100%; border-collapse: collapse; margin-top: 6px; table-layout: fixed; }
.tbl thead { background: #0B3C5D; color: #fff; }
.tbl th, .tbl td { padding: 2px 3px; border-bottom: 1px solid #E5E7EB; text-align: left; word-wrap: break-word; }
.tbl th.right, .tbl td.right { text-align: right; }
.tbl th.center, .tbl td.center { text-align: center; }
.tbl td { font-size: 6pt; }
.totals { margin-top: 10px; font-size: 7.5pt; text-align: right; }
.totals strong { color: #0B3C5D; }
.note { margin-top: 10px; font-size: 6pt; color: #6B7280; }
</style>
</head>
<body>

@include('pdf.partials.header-empresa-logo', [
    'empresa' => $empresa ?? null,
    'titulo' => 'Reporte de utilidad',
])
<div class="meta">
    Período: {{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}
    @if(!empty($etiquetaFiltros))
        <br>Filtros: {{ $etiquetaFiltros }}
    @endif
    <br>Generado: {{ now()->format('d/m/Y H:i') }}
</div>

<table class="tbl">
    <thead>
        <tr>
            <th style="width:4.5%;">Pedido</th>
            <th style="width:4.5%;">Fact.</th>
            <th style="width:4%;">Fecha</th>
            <th style="width:6%;">Cliente</th>
            <th style="width:7%;">Prod./conc.</th>
            <th class="right" style="width:3.5%;">C.u.</th>
            <th class="right" style="width:3.5%;">V.u.</th>
            <th class="right" style="width:3%;">Marg%</th>
            <th class="right" style="width:3.5%;">Ut.u.</th>
            <th class="right" style="width:2.8%;">Cant</th>
            <th class="right" style="width:3.5%;">Costo</th>
            <th class="right" style="width:3.5%;">IVA ac</th>
            <th class="right" style="width:4%;">Ct.cIVA</th>
            <th class="right" style="width:3.5%;">Venta</th>
            <th class="right" style="width:3.5%;">IVA pp</th>
            <th class="right" style="width:3.5%;">ISR</th>
            <th class="right" style="width:4%;">Mto.Vta</th>
            <th class="right" style="width:3.8%;">Gan.</th>
            <th class="center" style="width:3%;">Entr.</th>
            <th class="center" style="width:3%;">Pago</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lineas as $l)
        <tr>
            <td>{{ $l['oc'] ?? '—' }}</td>
            <td>{{ $l['factura'] }}</td>
            <td>{{ $l['fecha'] }}</td>
            <td>{{ Str::limit($l['cliente'], 22) }}</td>
            <td>{{ Str::limit($l['concepto'], 26) }}</td>
            <td class="right">${{ number_format($l['costo_unitario'] ?? 0, 4, '.', ',') }}</td>
            <td class="right">${{ number_format($l['ingreso_unitario'] ?? 0, 4, '.', ',') }}</td>
            <td class="right">{{ number_format($l['margen_pct'] ?? 0, 1) }}%</td>
            @php $uu = (float) ($l['utilidad_unitaria'] ?? 0); @endphp
            <td class="right" style="color: {{ $uu >= 0 ? '#15803d' : '#b91c1c' }};">${{ number_format($uu, 4, '.', ',') }}</td>
            <td class="right">{{ number_format($l['cantidad'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['costo'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['iva_acreditable'] ?? 0, 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['costo_con_iva'] ?? 0, 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['ingreso'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['iva_x_pagar'] ?? 0, 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['isr_reten'] ?? 0, 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['monto_total_venta'] ?? 0, 2, '.', ',') }}</td>
            @php $g = (float) ($l['ganancia'] ?? 0); @endphp
            <td class="right" style="color: {{ $g >= 0 ? '#15803d' : '#b91c1c' }};">${{ number_format($g, 2, '.', ',') }}</td>
            <td class="center">{{ $l['entregado_destino'] ?? 'No' }}</td>
            <td class="center">{{ $l['pagada'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="20" style="text-align:center;padding:14px;color:#6B7280;">Sin datos con los filtros aplicados.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="totals">
    <div><strong>Total facturado</strong> <span style="font-weight:normal;color:#6B7280;">(subtotal + IVA x pagar + ISR)</span><strong>:</strong> ${{ number_format($totalFacturado ?? ($totalIngreso + ($totalIvaXPagar ?? 0) + ($totalIsrReten ?? 0)), 2, '.', ',') }}</div>
    <div><strong>Subtotal:</strong> ${{ number_format($totalIngreso, 2, '.', ',') }}</div>
    <div><strong>Imp. IVA x pagar. (16%):</strong> ${{ number_format($totalIvaXPagar ?? 0, 2, '.', ',') }}</div>
    <div><strong>Total IVA (acred. 16%):</strong> ${{ number_format($totalIvaAcreditable ?? 0, 2, '.', ',') }}</div>
    <div><strong>Total costos:</strong> ${{ number_format($totalCosto, 2, '.', ',') }}</div>
    <div><strong>Total monto venta:</strong> ${{ number_format($totalMontoVenta ?? 0, 2, '.', ',') }}</div>
    <div><strong>Total Imp. Reten ISR 1,25%:</strong> ${{ number_format($totalIsrReten ?? 0, 2, '.', ',') }}</div>
    <div><strong>Total ganancia:</strong> ${{ number_format($totalGanancia ?? 0, 2, '.', ',') }}</div>
    <div><strong>Margen %:</strong> {{ number_format($margen, 2) }}%</div>
</div>

<p class="note">
    <strong>Venta:</strong> subtotal línea. <strong>IVA pp:</strong> 16% sobre venta. <strong>ISR:</strong> −1,25% sobre venta. <strong>Mto.Vta:</strong> venta + IVA + ISR. <strong>Ganancia:</strong> Mto.Vta − costo c/IVA.
    Costo desde producto; sin producto costo cero. <strong>Entreg. / Pago:</strong> mismos criterios que en el sistema.
</p>

</body>
</html>
