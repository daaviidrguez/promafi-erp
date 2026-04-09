<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Reporte de utilidad</title>
<style>
@page { margin: 10mm 12mm; size: letter landscape; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 8pt; color: #1F2937; }
h1 { font-size: 12pt; color: #0B3C5D; margin: 0 0 6px 0; }
.meta { font-size: 8pt; color: #4B5563; margin-bottom: 10px; line-height: 1.35; }
.tbl { width: 100%; border-collapse: collapse; margin-top: 8px; }
.tbl thead { background: #0B3C5D; color: #fff; }
.tbl th, .tbl td { padding: 4px 6px; border-bottom: 1px solid #E5E7EB; text-align: left; }
.tbl th.right, .tbl td.right { text-align: right; }
.tbl th.center, .tbl td.center { text-align: center; }
.tbl td { font-size: 7.5pt; }
.totals { margin-top: 12px; font-size: 9pt; text-align: right; }
.totals strong { color: #0B3C5D; }
.note { margin-top: 14px; font-size: 7pt; color: #6B7280; }
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
            <th>Factura</th>
            <th>OC</th>
            <th>Fecha</th>
            <th>Cliente</th>
            <th class="right">Cant.</th>
            <th class="right">Ingreso</th>
            <th class="right">Costo</th>
            <th class="right">Utilidad</th>
            <th class="center">Entregado</th>
            <th class="center">Pagada</th>
        </tr>
    </thead>
    <tbody>
        @forelse($lineas as $l)
        <tr>
            <td>{{ $l['factura'] }}</td>
            <td>{{ $l['oc'] ?? '—' }}</td>
            <td>{{ $l['fecha'] }}</td>
            <td>{{ $l['cliente'] }}</td>
            <td class="right">{{ number_format($l['cantidad'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['ingreso'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['costo'], 2, '.', ',') }}</td>
            <td class="right">${{ number_format($l['utilidad'], 2, '.', ',') }}</td>
            <td class="center">{{ $l['entregado_destino'] ?? 'No' }}</td>
            <td class="center">{{ $l['pagada'] }}</td>
        </tr>
        @empty
        <tr>
            <td colspan="10" style="text-align:center;padding:16px;color:#6B7280;">Sin datos con los filtros aplicados.</td>
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
    <strong>OC:</strong> orden de compra capturada en la factura (campo libre). <strong>Entregado:</strong> mismo criterio que logística: cantidad entregada en destino para la línea de factura igual o mayor a la facturada (Sí); si aún hay pendiente por entregar en destino (No).
    <strong>Estado de pago:</strong> columna <em>Pagada</em>: PUE (contado) figura como Pagada; PPD según saldo de la cuenta por cobrar (complementos de pago y notas de crédito). Si aún hay saldo, figura como Pendiente.
</p>

</body>
</html>
