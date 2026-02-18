<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">

<style>
@page { margin: 15mm 20mm 35mm 20mm; size: letter; }

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color:#1F2937;
}

.header {
    border-bottom:3px solid #0B3C5D;
    padding-bottom:10px;
    margin-bottom:15px;
}

.section-title {
    font-weight:bold;
    border-bottom:2px solid #0B3C5D;
    margin-bottom:6px;
    padding-bottom:2px;
}

.info-box {
    border:1px solid #E5E7EB;
    padding:10px;
    margin-bottom:15px;
}

.productos-table {
    width:100%;
    border-collapse:collapse;
    margin-top:10px;
}

.productos-table thead {
    background:#0B3C5D;
    color:white;
}

.productos-table th,
.productos-table td {
    padding:6px;
    font-size:8pt;
    border-bottom:1px solid #E5E7EB;
}

.productos-table th {
    text-align:left;
}

.center { text-align:center; }
.right { text-align:right; }

.totales-table {
    width:40%;
    margin-left:auto;
    margin-top:15px;
    border-collapse:collapse;
}

.totales-table td {
    padding:6px 10px;
}

.totales-table td:first-child {
    text-align:right;
    font-weight:bold;
}

.totales-table td:last-child {
    text-align:right;
}

.total-final {
    background:#0B3C5D;
    color:white;
    font-weight:bold;
}

.footer {
    position:fixed;
    bottom:10mm;
    left:20mm;
    right:20mm;
    border-top:2px solid #0B3C5D;
    padding-top:6px;
    font-size:8pt;
    text-align:center;
}
</style>
</head>

<body>

{{-- ================= HEADER ================= --}}
<div class="header">
<table width="100%">
<tr>

<td width="20%">
    @php
    $logoPath = $empresa->logo_path
        ? public_path('storage/'.$empresa->logo_path)
        : null;
    @endphp

    @if($logoPath && file_exists($logoPath))
        <img src="{{ $logoPath }}" style="max-height:70px;">
    @endif
</td>

<td width="55%">
    <strong style="font-size:13pt;">
        {{ strtoupper($empresa->nombre_comercial ?? $empresa->razon_social) }}
    </strong><br>
    RFC: {{ $empresa->rfc }}<br>
    Régimen Fiscal: {{ $empresa->regimen_fiscal }}<br>
    {{ $empresa->calle }} {{ $empresa->numero_exterior }}<br>
    {{ $empresa->colonia }}, {{ $empresa->municipio }}<br>
    Tel: {{ $empresa->telefono }} |
    Email: {{ $empresa->email }}
</td>

<td width="25%" align="right">
    <strong style="font-size:14pt;">
        {{ strtoupper($tipo) }}
    </strong><br>
    <strong>{{ $doc->folio }}</strong><br>

    @if($esFactura && $doc->uuid)
        <span style="color:green;font-weight:bold;">
            ✓ CFDI TIMBRADO
        </span>
    @endif
</td>

</tr>
</table>
</div>

{{-- ================= CLIENTE / INFO ================= --}}
<table width="100%" cellpadding="0" cellspacing="0">
<tr>

<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">DATOS DEL CLIENTE</div>
        {{ $doc->cliente_nombre ?? $doc->nombre_receptor }}<br>
        RFC: {{ $doc->cliente_rfc ?? $doc->rfc_receptor }}
    </div>
</td>

<td width="4%"></td>

<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">INFORMACIÓN</div>

        Fecha:
        {{ \Carbon\Carbon::parse($doc->fecha ?? $doc->fecha_emision)->format('d/m/Y') }}<br>

        @if($esCotizacion)
            Válida:
            {{ \Carbon\Carbon::parse($doc->fecha_vencimiento)->format('d/m/Y') }}<br>
        @endif

        @if($esFactura)
            UUID: {{ $doc->uuid ?? 'Pendiente' }}
        @endif
    </div>
</td>

</tr>
</table>

{{-- ================= PRODUCTOS ================= --}}
<table class="productos-table">
<thead>
<tr>
<th>Código</th>
<th>Descripción</th>
<th class="center">Cant</th>
<th class="right">Precio</th>
<th class="center">IVA</th>
<th class="right">Importe</th>
</tr>
</thead>
<tbody>
@foreach($doc->detalles as $d)
<tr>
<td>{{ $d->codigo ?? '-' }}</td>
<td>{{ $d->descripcion }}</td>
<td class="center">{{ number_format($d->cantidad,2) }}</td>
<td class="right">${{ number_format($d->precio_unitario ?? $d->valor_unitario,2) }}</td>
<td class="center">
@if($d->tasa_iva === null)
Exento
@else
{{ number_format($d->tasa_iva * 100,0) }}%
@endif
</td>
<td class="right">${{ number_format($d->total ?? $d->importe,2) }}</td>
</tr>
@endforeach
</tbody>
</table>

{{-- ================= TOTALES ================= --}}
<table class="totales-table">
<tr>
<td>Subtotal:</td>
<td>${{ number_format($doc->subtotal,2) }}</td>
</tr>
<tr>
<td>IVA:</td>
<td>${{ number_format($doc->iva,2) }}</td>
</tr>
<tr class="total-final">
<td>TOTAL:</td>
<td>${{ number_format($doc->total,2) }} MXN</td>
</tr>
</table>

{{-- ================= TIMBRADO ================= --}}
@if($esFactura)
    @include('pdf.partials.timbrado')
@endif

{{-- ================= DATOS BANCARIOS ================= --}}
@if($empresa->banco)
<hr style="margin-top:40px;border:0;border-top:2px solid #0B3C5D;">
<div style="border:1px solid #93C5FD;padding:10px;margin-top:10px;font-size:8pt;">
<strong>DATOS PARA TRANSFERENCIA BANCARIA</strong><br><br>
Banco: {{ $empresa->banco }}<br>
Cuenta: {{ $empresa->numero_cuenta }}<br>
CLABE: {{ $empresa->clabe }}
</div>
@endif

{{-- ================= CONDICIONES ================= --}}
@if($doc->condiciones_pago)
<div style="margin-top:15px;font-size:8pt;">
<strong>Condiciones:</strong><br>
{!! nl2br(e($doc->condiciones_pago)) !!}
</div>
@endif

{{-- ================= FOOTER ================= --}}
<div class="footer">
Gracias por su preferencia<br>
{{ $empresa->razon_social }}
</div>

<script type="text/php">
if (isset($pdf)) {
    $pdf->page_text(520, 770, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 8, [0,0,0]);
}
</script>

</body>
</html>