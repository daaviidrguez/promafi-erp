<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Ventas mensuales</title>
<style>
@page { margin: 10mm 12mm; size: letter; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #1F2937; }
h1 { font-size: 12pt; color: #0B3C5D; margin: 0 0 6px 0; }
.meta { font-size: 8pt; color: #4B5563; margin-bottom: 10px; line-height: 1.35; }
.tbl { width: 100%; border-collapse: collapse; margin-top: 8px; }
.tbl thead { background: #0B3C5D; color: #fff; }
.tbl th, .tbl td { padding: 4px 6px; border-bottom: 1px solid #E5E7EB; text-align: left; }
.tbl th.right, .tbl td.right { text-align: right; }
.tbl td { font-size: 7.5pt; }
.resumen { margin-top: 14px; max-width: 420px; margin-left: auto; }
.resumen table { width: 100%; border-collapse: collapse; font-size: 9pt; }
.resumen td { padding: 4px 0; border-bottom: 1px solid #E5E7EB; }
.resumen td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.resumen tr.grand td { font-weight: bold; border-bottom: 2px solid #0B3C5D; padding-top: 8px; }
.note { margin-top: 14px; font-size: 7pt; color: #6B7280; }
</style>
</head>
<body>

<h1>Ventas mensuales</h1>
<div class="meta">
    @if($empresa)
        <strong>{{ $empresa->nombre_comercial ?? $empresa->razon_social }}</strong><br>
    @endif
    Período: {{ $mesNombre }} {{ $año }}<br>
    @if(!empty($clienteNombreFiltro))
        Cliente: {{ $clienteNombreFiltro }}<br>
    @endif
    Generado: {{ now()->format('d/m/Y H:i')}}
</div>

<table class="tbl">
    <thead>
        <tr>
            <th>Serie / Folio</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th class="right">Subtotal</th>
            <th class="right">IVA</th>
            <th class="right">ISR retenido</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lineas as $l)
        <tr>
            <td>{{ $l['factura'] }}</td>
            <td>{{ $l['fecha'] }}</td>
            <td>{{ $l['cliente'] }}</td>
            <td class="right">${{ number_format($l['subtotal'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['iva'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['isr_retenido'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['total'], 2, '.', ',') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="7" style="text-align:center;padding:16px;color:#6B7280;">No hay facturas en este período.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="resumen">
    <table>
        <tr><td>Facturas</td><td>{{ $numFacturas }}</td></tr>
        <tr><td>Subtotal</td><td>${{ number_format($subtotalVentas, 2, '.', ',') }}</td></tr>
        <tr><td>IVA</td><td>${{ number_format($ivaVentas, 2, '.', ',') }}</td></tr>
        <tr><td>ISR retenido</td><td>${{ number_format($isrRetenidoVentas, 2, '.', ',') }}</td></tr>
        <tr class="grand"><td>Total ventas</td><td>${{ number_format($totalVentas, 2, '.', ',') }}</td></tr>
    </table>
</div>

<p class="note">
    Facturas timbradas del mes seleccionado. IVA: traslado clave 002. ISR retenido: total de retenciones por factura (mismo criterio que el desglose CFDI).
</p>

</body>
</html>
