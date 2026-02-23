<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>{{ $es_reporte_cobranza ? 'Reporte de Cobranza' : 'Estado de Cuenta' }} - {{ $cliente->nombre }}</title>
<style>
@page { margin: 15mm 20mm 15mm 20mm; size: letter; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #1F2937; }
.header { border-bottom: 3px solid #0B3C5D; padding-bottom: 10px; margin-bottom: 15px; }
.section-title { font-weight: bold; border-bottom: 2px solid #0B3C5D; margin-bottom: 6px; padding-bottom: 2px; }
.info-box { border: 1px solid #E5E7EB; padding: 10px; margin-bottom: 12px; font-size: 8pt; }
.tbl { width: 100%; border-collapse: collapse; margin-top: 10px; }
.tbl thead { background: #0B3C5D; color: white; }
.tbl th, .tbl td { padding: 6px 10px; font-size: 8pt; border-bottom: 1px solid #E5E7EB; }
.tbl th.right, .tbl td.right { text-align: right; }
.tbl td.right { font-variant-numeric: tabular-nums; }
.totales { margin-top: 15px; padding-top: 12px; border-top: 2px solid #0B3C5D; text-align: right; font-weight: bold; }
.footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid #E5E7EB; font-size: 8pt; color: #6B7280; }
</style>
</head>
<body>

<div class="header">
    <strong>{{ $empresa->razon_social ?? 'Empresa' }}</strong>
    @if($empresa->rfc ?? null)
        <br><span style="font-size: 8pt;">RFC: {{ $empresa->rfc }}</span>
    @endif
    <div style="margin-top: 8px; font-size: 10pt;">
        {{ $es_reporte_cobranza ? 'Reporte de Cobranza' : 'Estado de Cuenta' }}
    </div>
</div>

<div class="section-title">Cliente</div>
<div class="info-box">
    <strong>{{ $cliente->nombre }}</strong>
    @if($cliente->rfc)
        <br>RFC: {{ $cliente->rfc }}
    @endif
    @if($fecha_desde || $fecha_hasta)
        <br>Período:
        @if($fecha_desde && $fecha_hasta)
            {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}
        @elseif($fecha_desde)
            Desde {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }}
        @else
            Hasta {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}
        @endif
    @endif
    <br>Fecha de impresión: {{ now()->format('d/m/Y H:i') }}
</div>

@if(count($movimientos) > 0)
<table class="tbl">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Referencia</th>
            <th class="right">Cargo</th>
            <th class="right">Abono</th>
            <th class="right">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($movimientos as $m)
        <tr>
            <td>{{ $m['fecha']->format('d/m/Y') }}</td>
            <td>{{ $m['tipo'] }}</td>
            <td>{{ $m['referencia'] }}</td>
            <td class="right">{{ $m['cargo'] > 0 ? '$' . number_format($m['cargo'], 2, '.', ',') : '—' }}</td>
            <td class="right">{{ $m['abono'] > 0 ? '$' . number_format($m['abono'], 2, '.', ',') : '—' }}</td>
            <td class="right">${{ number_format($m['saldo'], 2, '.', ',') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
<div class="totales">
    Total cargos: ${{ number_format($total_cargos, 2, '.', ',') }} &nbsp;&nbsp;
    Total abonos: ${{ number_format($total_abonos, 2, '.', ',') }} &nbsp;&nbsp;
    <strong>Saldo final: ${{ number_format($saldo_final, 2, '.', ',') }}</strong>
</div>
@else
<p>Sin movimientos en el período seleccionado.</p>
@endif

<div class="footer">
    Documento generado por {{ $empresa->nombre_comercial ?? $empresa->razon_social ?? 'Sistema' }}. Este reporte no sustituye la documentación fiscal oficial.
</div>

</body>
</html>
