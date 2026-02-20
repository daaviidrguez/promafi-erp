<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">

<style>
/* =====================
   PÁGINA
===================== */
@page { margin: 15mm 20mm 15mm 20mm; size: letter; }

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 9pt;
    color: #1F2937;
}

/* =====================
   HEADER
===================== */
.header {
    border-bottom: 3px solid #0B3C5D;
    padding-bottom: 10px;
    margin-bottom: 15px;
}

/* =====================
   INFO CLIENTE
===================== */
.section-title {
    font-weight: bold;
    border-bottom: 2px solid #0B3C5D;
    margin-bottom: 6px;
    padding-bottom: 2px;
}

.info-box {
    border: 1px solid #E5E7EB;
    padding: 10px;
    margin-bottom: 15px;
    font-size: 8pt;
    line-height: 1.35;
}

/* =====================
   TABLA PRODUCTOS
===================== */
.productos-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 10px;
}

.productos-table thead {
    background: #0B3C5D;
    color: white;
}

.productos-table th,
.productos-table td {
    padding: 6px 10px;
    font-size: 8pt;
    border-bottom: 1px solid #E5E7EB;
}

.productos-table th {
    text-align: left;
}

/* Alineaciones específicas */
.productos-table th.center,
.productos-table td.center {
    text-align: center;
}

.productos-table th.right,
.productos-table td.right {
    text-align: right;
}

/* Alineación numérica contable */
.productos-table td.right {
    font-variant-numeric: tabular-nums;
}

/* =====================
   TOTALES
===================== */
.totales-table {
    width: 40%;
    margin-left: auto;
    margin-top: 15px;
    border-collapse: collapse;
}

.totales-table td {
    padding: 6px 10px;
}

.totales-table td:first-child {
    text-align: right;
    font-weight: bold;
}

.totales-table td:last-child {
    text-align: right;
}

.total-final {
    background: #0B3C5D;
    color: white;
    font-weight: bold;
}

/* =====================
   FOOTER — fijo en todas las páginas
===================== */
.footer {
    margin-top: 20px;
    padding-top: 10px;
    border-top: 3px solid #0B3C5D;
    font-size: 8pt;
    page-break-inside: avoid;
}

/* Caja datos bancarios */
.banco-box {
    border: 1px solid #93C5FD;
    padding: 7px 10px;
    margin-bottom: 6px;
}
.banco-box-titulo {
    font-weight: bold;
    font-size: 8pt;
    margin-bottom: 4px;
}

/* Condiciones de pago */
.condiciones-box {
    margin-bottom: 6px;
    font-size: 8pt;
}

/* Pie de agradecimiento */
.footer-gracias {
    text-align: center;
    font-size: 8pt;
}
.footer-gracias strong {
    font-size: 9pt;
}

/* =====================
   TIMBRADO (factura)
===================== */
.timbrado-section { border: 1px solid #E5E7EB; padding: 8px; margin-bottom: 6px; }
.timbrado-label   { font-weight: bold; font-size: 7pt; margin-bottom: 2px; }
.timbrado-value   { font-size: 6pt; font-family: monospace; word-break: break-all; line-height: 1.3; }
.qr-placeholder   { width: 80px; height: 80px; border: 2px dashed #D1D5DB; }

</style>
</head>

<body>

{{-- =====================
     HEADER
===================== --}}
@php
    $logoDataUri = null;
    if ($empresa->logo_path ?? null) {
        $logoPath = storage_path('app/public/' . $empresa->logo_path);
        if (!file_exists($logoPath)) { 
            $logoPath = public_path('storage/' . $empresa->logo_path); 
        }
        if ($logoPath && file_exists($logoPath)) {
            $logoDataUri = 'data:' . mime_content_type($logoPath) . ';base64,' . base64_encode(file_get_contents($logoPath));
        }
    }

    $qrDataUri = null;
    if ($empresa->qr_sat_path ?? null) {
        $qrPath = storage_path('app/public/' . $empresa->qr_sat_path);
        if (!file_exists($qrPath)) { 
            $qrPath = public_path('storage/' . $empresa->qr_sat_path); 
        }
        if ($qrPath && file_exists($qrPath)) {
            $qrDataUri = 'data:' . mime_content_type($qrPath) . ';base64,' . base64_encode(file_get_contents($qrPath));
        }
    }

    $tieneQr   = !empty($qrDataUri);
    $tieneLogo = !empty($logoDataUri);
@endphp

<div class="header">
<table width="100%" cellpadding="0" cellspacing="0">
<tr>

    {{-- COLUMNA IZQUIERDA (58%) --}}
    <td width="58%" valign="top" style="padding-right:12px;">

        {{-- Nombre comercial --}}
        <div style="font-size:12pt; font-weight:bold; color:#0B3C5D; margin-bottom:6px;">
            {{ strtoupper($empresa->nombre_comercial ?? $empresa->razon_social) }}
        </div>

        {{-- QR + Datos fiscales (layout estable Dompdf) --}}
        <div style="display:table; width:100%;">
            
            @if($tieneQr)
            <div style="display:table-cell; width:80px; vertical-align:top;">
                <img src="{{ $qrDataUri }}" style="width:70px; display:block;">
            </div>
            @endif

            <div style="display:table-cell; vertical-align:top; font-size:8pt; line-height:1.4;">
                <strong>RFC:</strong> {{ $empresa->rfc }}<br>
                <strong>Regimen Fiscal:</strong> {{ $empresa->regimen_fiscal_etiqueta ?? $empresa->regimen_fiscal ?? '' }}<br>
                {{ $empresa->calle ?? '' }} {{ $empresa->numero_exterior ?? '' }}<br>
                {{ $empresa->colonia ?? '' }}
                {{ ($empresa->municipio ?? $empresa->ciudad ?? '') 
                    ? ', ' . ($empresa->municipio ?? $empresa->ciudad) 
                    : '' }}<br>
                Tel: {{ $empresa->telefono ?? '' }} 
                | Email: {{ $empresa->email ?? '' }}
            </div>

        </div>

    </td>

    {{-- COLUMNA DERECHA (42%) --}}
    <td width="42%" valign="top" style="text-align:center;">

        <div style="display:block;">

            @if($tieneLogo)
            <div style="margin-bottom:6px;">
                <img src="{{ $logoDataUri }}" 
                    style="max-height:45px; display:block; margin:0 auto;">
            </div>
            @endif

            <div style="font-size:12pt; font-weight:bold; color:#0B3C5D;">
                {{ strtoupper($tipo) }}
            </div>

            <div style="font-size:12pt; font-weight:bold; margin-top:2px;">
                {{ $doc->folio }}
            </div>

            @if($esFactura && ($doc->uuid ?? null))
            <div style="color:#10B981; font-weight:bold; margin-top:4px;">
                &#10003; CFDI TIMBRADO
            </div>
            @endif

        </div>

    </td>

</tr>
</table>
</div>
{{-- /HEADER --}}

{{-- =====================
     CLIENTE / INFO
===================== --}}
<table width="100%" cellpadding="0" cellspacing="0">
<tr>

<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">DATOS DEL CLIENTE</div>
        {{ $doc->cliente_nombre ?? $doc->nombre_receptor }}<br>
        RFC: {{ $doc->cliente_rfc ?? $doc->rfc_receptor }}<br>
        @if($esCotizacion)
        @php
            $dir = trim(
                ($doc->cliente_calle ?? '') . ' ' .
                ($doc->cliente_numero_exterior ?? '') .
                ($doc->cliente_numero_interior ? ' Int. ' . $doc->cliente_numero_interior : '') .
                ', ' . ($doc->cliente_colonia ?? '') .
                ', ' . ($doc->cliente_municipio ?? '') .
                ', ' . ($doc->cliente_estado ?? '') .
                ' CP ' . ($doc->cliente_codigo_postal ?? '')
            );
        @endphp
        @if($dir !== '' && $dir !== ',  CP ')
        Direccion: {{ trim($dir, ' ,') }}<br>
        @endif
        @if(!empty($doc->cliente_telefono))
        Telefono: {{ $doc->cliente_telefono }}<br>
        @endif
        @if(!empty($doc->cliente_email))
        Email: {{ $doc->cliente_email }}
        @endif
        @endif
    </div>
</td>

<td width="4%"></td>

<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">{{ $esCotizacion ? 'INFORMACION FISCAL' : 'INFORMACION' }}</div>

        Fecha Emision: {{ \Carbon\Carbon::parse($doc->fecha ?? $doc->fecha_emision)->format('d/m/Y') }}<br>

        @if($esCotizacion)
            {{-- Condicion de Compra con color --}}
            @if($doc->tipo_venta === 'credito' && ($doc->dias_credito_aplicados ?? 0) > 0)
            <strong>Condicion de Compra:</strong>
            <span style="color:#F59E0B; font-weight:bold;">CREDITO {{ $doc->dias_credito_aplicados }} DIAS</span><br>
            @else
            <strong>Condicion de Compra:</strong>
            <span style="color:#10B981; font-weight:bold;">CONTADO</span><br>
            @endif
            Valida: {{ $doc->fecha_vencimiento ? \Carbon\Carbon::parse($doc->fecha_vencimiento)->format('d/m/Y') : '-' }}<br>
            Moneda: MXN<br>
            @if($doc->usuario)
            Elaboro: {{ $doc->usuario->name ?? $doc->usuario->email }}
            @endif
        @endif

        @if($esFactura)
            UUID: {{ $doc->uuid ?? 'Pendiente' }}
        @endif
    </div>
</td>

</tr>
</table>


{{-- =====================
     PRODUCTOS
===================== --}}
<table class="productos-table">
<thead>
<tr>
<th>Codigo</th>
<th>Descripcion</th>
<th class="center">Cant</th>
@if($esCotizacion)<th class="center">Unidad</th>@endif
<th class="right">{{ $esCotizacion ? 'Precio Unit.' : 'Precio' }}</th>
<th class="center">IVA</th>
@if($esCotizacion)<th class="center">Desc %</th>@endif
<th class="right">Importe</th>
</tr>
</thead>
<tbody>
@foreach($doc->detalles as $d)
<tr>
<td>{{ ($d->codigo === 'MANUAL' || $d->codigo === null) ? '-' : $d->codigo }}</td>
<td>{{ $d->descripcion }}</td>
<td class="center">{{ number_format($d->cantidad, 2) }}</td>
@if($esCotizacion)
<td class="center">{{ $d->unidad ?? $d->producto->unidad ?? 'PZA' }}</td>
@endif
<td class="right">${{ number_format($d->precio_unitario ?? $d->valor_unitario, 2) }}</td>
<td class="center">
    @if(isset($d->tasa_iva) && $d->tasa_iva === null)
        Exento
    @elseif(isset($d->tasa_iva))
        {{ number_format($d->tasa_iva * 100, 0) }}%
    @else
        -
    @endif
</td>
@if($esCotizacion)
<td class="center">
    @if(($d->descuento_porcentaje ?? 0) > 0)
        {{ number_format($d->descuento_porcentaje ?? 0, 1) }}%
    @else
        -
    @endif
</td>
@endif
<td class="right">${{ number_format($esCotizacion ? ($d->base_imponible ?? $d->subtotal ?? $d->total) : ($d->total ?? $d->importe), 2) }}</td>
</tr>
@endforeach
</tbody>
</table>


{{-- =====================
     TOTALES
===================== --}}
<table class="totales-table">
<tr>
    <td>Subtotal:</td>
    <td>${{ number_format($doc->subtotal, 2) }}</td>
</tr>
@if($esCotizacion && ($doc->descuento ?? 0) > 0)
<tr>
    <td>Descuento:</td>
    <td style="color:#EF4444;">-${{ number_format($doc->descuento, 2) }}</td>
</tr>
@endif
<tr>
    <td>IVA:</td>
    <td>${{ number_format($doc->iva, 2) }}</td>
</tr>
<tr class="total-final">
    <td>TOTAL:</td>
    <td>${{ number_format($doc->total, 2) }} MXN</td>
</tr>
</table>


{{-- =====================
     TIMBRADO (solo factura)
===================== --}}
@if($esFactura)
    @include('pdf.partials.timbrado')
@endif


{{-- =====================
     FOOTER FIJO
===================== --}}
<div class="footer">

    {{-- Datos bancarios --}}
    @if($empresa->banco ?? $empresa->numero_cuenta ?? $empresa->clabe ?? false)
    <div class="banco-box">
        <div class="banco-box-titulo">DATOS PARA TRANSFERENCIA BANCARIA</div>
        <table width="100%" cellpadding="0" cellspacing="0">
        <tr>
            <td width="33%"><strong>BANCO:</strong> {{ $empresa->banco ?? '-' }}</td>
            <td width="33%"><strong>CUENTA:</strong> {{ $empresa->numero_cuenta ?? '-' }}</td>
            <td width="34%"><strong>CLABE:</strong> {{ $empresa->clabe ?? '-' }}</td>
        </tr>
        </table>
    </div>
    @endif

    {{-- Condiciones de pago --}}
    @if($esCotizacion)
    <div class="condiciones-box">
        <strong>CONDICIONES DE PAGO</strong><br>
        <strong>CONDICION:</strong>
        @if($doc->tipo_venta === 'credito' && ($doc->dias_credito_aplicados ?? 0) > 0)
            <span style="color:#F59E0B; font-weight:bold;">
                CREDITO {{ $doc->dias_credito_aplicados }} DIAS
            </span>
        @else
            <span style="color:#10B981; font-weight:bold;">
                CONTADO
            </span>
        @endif

        @if($doc->condiciones_pago)
            <br>
            {!! nl2br(e($doc->condiciones_pago)) !!}
        @endif
    </div>
    @else
        @if($doc->condiciones_pago ?? false)
        <div class="condiciones-box">
            <strong>Condiciones:</strong><br>
            {!! nl2br(e($doc->condiciones_pago)) !!}
        </div>
        @endif
    @endif

    {{-- Nota CFDI --}}
    @if($esFactura)
    <div style="margin-bottom:4px; font-size:7pt; color:#6B7280; text-align:center;">
        @if($doc->uuid ?? null)
            Este documento es una representacion impresa de un CFDI
        @else
            DOCUMENTO BORRADOR - NO VALIDO COMO COMPROBANTE FISCAL
        @endif
    </div>
    @endif

    {{-- Agradecimiento --}}
    <div class="footer-gracias">
        <strong>Gracias por su preferencia</strong><br>
        {{ $empresa->razon_social }}<br>
        RFC: {{ $empresa->rfc }} | {{ $empresa->regimen_fiscal_etiqueta ?? $empresa->regimen_fiscal ?? '-' }}
    </div>

</div>
{{-- /FOOTER --}}


<script type="text/php">
if (isset($pdf)) {
    $pdf->page_text(520, 770, "Pagina {PAGE_NUM} de {PAGE_COUNT}", null, 8, [0,0,0]);
}
</script>

</body>
</html>