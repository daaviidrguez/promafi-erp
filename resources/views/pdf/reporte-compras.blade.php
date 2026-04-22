<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Compras</title>
<style>
@page { margin: 10mm 12mm; size: letter landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #1F2937; }
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

@include('pdf.partials.header-empresa-logo', [
    'empresa' => $empresa ?? null,
    'titulo' => 'Compras (facturas de compra)',
])
<div class="meta">
    Período: {{ $mesNombre }} {{ $año }}<br>
    Generado: {{ now()->format('d/m/Y H:i')}}
</div>

<table class="tbl">
    <thead>
        <tr>
            <th>Folio / referencias</th>
            <th>Fecha</th>
            <th>Proveedor</th>
            <th class="right">Subtotal</th>
            <th class="right">IVA acreditable</th>
            <th class="right">Total</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lineas as $l)
        <tr>
            <td>{{ $l['folio'] }}</td>
            <td>{{ $l['fecha'] }}</td>
            <td>{{ $l['proveedor'] }}</td>
            <td class="right">${{ number_format($l['subtotal'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['iva'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['total'], 2, '.', ',') }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="6" style="text-align:center;padding:16px;color:#6B7280;">No hay facturas de compra en este período.</td>
        </tr>
        @endforelse
    </tbody>
</table>

<div class="resumen">
    <table>
        <tr><td>Facturas de compra</td><td>{{ $numFacturas }}</td></tr>
        <tr><td>Subtotal</td><td>${{ number_format($subtotalCompras, 2, '.', ',') }}</td></tr>
        <tr><td>IVA acreditable</td><td>${{ number_format($ivaCompras, 2, '.', ',') }}</td></tr>
        <tr class="grand"><td>Total compras</td><td>${{ number_format($totalCompras, 2, '.', ',') }}</td></tr>
    </table>
</div>

<p class="note">
    Solo facturas de compra registradas en el mes (manuales o desde CFDI). IVA acreditable: traslado clave 002 por línea.
</p>

</body>
</html>
