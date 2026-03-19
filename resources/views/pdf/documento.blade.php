<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">

<style>
/* =====================
   PÁGINA
===================== */
@page { margin: 8mm 12mm 8mm 12mm; size: letter; }

body {
    font-family: Arial, sans-serif;
    font-size: 7.5pt;
    color: #1F2937;
    margin: 0;
    padding: 0;
}

/* =====================
   HEADER
===================== */
.header {
    border-bottom: 3px solid #0B3C5D;
    padding-bottom: 4px;
    margin-bottom: 4px;
}

/* =====================
   INFO CLIENTE
===================== */
.section-title {
    font-size: 8pt;
    font-weight: bold;
    border-bottom: 2px solid #0B3C5D;
    margin-bottom: 2px;
    padding-bottom: 1px;
}

.info-box {
    border: 1px solid #E5E7EB;
    padding: 4px 8px;
    margin-bottom: 4px;
    font-size: 7.5pt;
    line-height: 1.25;
    vertical-align: top;
}

/* =====================
   TABLA PRODUCTOS
===================== */
.productos-table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 2px;
}

.productos-table thead {
    background: #0B3C5D;
    color: white;
}

.productos-table th,
.productos-table td {
    padding: 3px 8px;
    font-size: 7.5pt;
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
    margin-top: 4px;
    border-collapse: collapse;
}

.totales-table td {
    padding: 3px 8px;
    font-size: 7.5pt;
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
    margin-top: 6px;
    padding-top: 4px;
    border-top: 3px solid #0B3C5D;
    font-size: 7.5pt;
    page-break-inside: avoid;
}

/* Caja datos bancarios */
.banco-box {
    border: 1px solid #93C5FD;
    padding: 4px 8px;
    margin-bottom: 2px;
}
.banco-box-titulo {
    font-weight: bold;
    font-size: 8pt;
    margin-bottom: 2px;
}

/* Condiciones de pago */
.condiciones-box {
    margin-bottom: 3px;
    font-size: 7.5pt;
}

.timbrado-section { border: 1px solid #E5E7EB; padding: 4px 6px; margin-bottom: 2px; }
.timbrado-label   { font-weight: bold; font-size: 7pt; margin-bottom: 1px; }
.timbrado-value   { font-size: 6.5pt; font-family: DejaVu Sans Mono, monospace; word-break: break-all; line-height: 1.2; }
.qr-placeholder   { width: 80px; height: 80px; border: 2px dashed #D1D5DB; }
.pagare-box       { font-size: 6.5pt; line-height: 1.3; margin-bottom: 4px; }
.sello-receptor-box { border: 2px solid #0B3C5D; padding: 8px; min-height: 70px; text-align: center; font-size: 6.5pt; color: #6B7280; }

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

        {{-- Nombre comercial (Header empresa: 10-11pt) --}}
        <div style="font-size:10.5pt; font-weight:bold; color:#0B3C5D; margin-bottom:3px;">
            {{ strtoupper($empresa->nombre_comercial ?? $empresa->razon_social) }}
        </div>

        {{-- QR + Datos fiscales (layout estable Dompdf) --}}
        <div style="display:table; width:100%;">
            
            @if($tieneQr)
            <div style="display:table-cell; width:80px; vertical-align:top;">
                <img src="{{ $qrDataUri }}" style="width:70px; display:block;">
            </div>
            @endif

            <div style="display:table-cell; vertical-align:top; font-size:6.5pt; line-height:1.2;">
                <strong>RFC:</strong> {{ $empresa->rfc }}<br>
                <strong>Regimen Fiscal:</strong> {{ $empresa->regimen_fiscal_etiqueta ?? $empresa->regimen_fiscal ?? '' }}<br>
                {{ $empresa->calle ?? '' }} {{ $empresa->numero_exterior ?? '' }}{{ $empresa->numero_interior ? ' Int. ' . $empresa->numero_interior : '' }}<br>
                {{ $empresa->colonia ?? '' }}{{ ($empresa->municipio ?? $empresa->ciudad ?? '') ? ', ' . ($empresa->municipio ?? $empresa->ciudad) : '' }}{{ ($empresa->estado ?? '') ? ', ' . $empresa->estado : '' }}{{ ($empresa->codigo_postal ?? '') ? ', C.P. ' . $empresa->codigo_postal : '' }}<br>
                Tel: {{ $empresa->telefono ?? '' }} | Email: {{ $empresa->email ?? '' }}
            </div>

        </div>

    </td>

    {{-- COLUMNA DERECHA (42%) --}}
    <td width="42%" valign="top" style="text-align:center;">

        <div style="display:block;">

            @if($tieneLogo)
            <div style="margin-bottom:3px;">
                <img src="{{ $logoDataUri }}" 
                    style="max-height:45px; display:block; margin:0 auto;">
            </div>
            @endif

            @if($esFactura)
            <div style="font-size:10.5pt; font-weight:bold;">CFDI 4.0 - Factura</div>
            @elseif($esNotaCredito)
            <div style="font-size:10.5pt; font-weight:bold;">CFDI 4.0 - Nota de Crédito</div>
            @elseif($esComplemento)
            <div style="font-size:10.5pt; font-weight:bold;">CFDI 4.0 - Complemento de Pago</div>
            @elseif($esCotizacionCompra ?? false)
            <div style="font-size:10.5pt; font-weight:bold; color:#0B3C5D;">
                COTIZACIÓN DE COMPRA
            </div>
            @elseif($esOrdenCompra ?? false)
            <div style="font-size:10.5pt; font-weight:bold; color:#0B3C5D;">
                ORDEN DE COMPRA
            </div>
            @elseif($esFacturaCompra ?? false)
            <div style="font-size:10.5pt; font-weight:bold; color:#0B3C5D;">
                FACTURA DE COMPRA
            </div>
            @else
            <div style="font-size:10.5pt; font-weight:bold; color:#0B3C5D;">
                {{ strtoupper($tipo) }}
            </div>
            @endif

            <div style="font-size:10.5pt; font-weight:bold; margin-top:2px;">
                {{ $doc->serie ?? '' }}{{ ($doc->serie ?? '') ? ' ' : '' }}{{ $doc->folio }}
            </div>

            @if(($esFactura || $esNotaCredito) && ($doc->uuid ?? null) && ($doc->estado ?? '') === 'cancelada')
            <div style="font-weight:bold; margin-top:4px;">
                <span style="color:#DC2626;">CANCELADA</span>
            </div>
            @endif

        </div>

    </td>

</tr>
</table>
</div>
{{-- /HEADER --}}

{{-- =====================
     FACTURA: contenido CFDI 4.0 completo
     COMPLEMENTO: receptor, pagos, documentos relacionados
     COTIZACIÓN/OTROS: cliente, info, productos, totales
===================== --}}
@if($esFactura || $esNotaCredito)
    @include('pdf.partials.factura-cfdi')
@elseif($esComplemento)
    @include('pdf.partials.complemento-pago')
@elseif($esCotizacionCompra ?? false)
    @include('pdf.partials.cotizacion-compra')
@elseif($esOrdenCompra ?? false)
    @include('pdf.partials.orden-compra')
@elseif($esFacturaCompra ?? false)
    @include('pdf.partials.factura-compra')
@else
{{-- CLIENTE / INFO (cotización, remisión, etc.) --}}
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
    </div>
</td>
</tr>
</table>

{{-- PRODUCTOS (cotización, remisión) --}}
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

<table class="totales-table">
<tr><td>Subtotal:</td><td>${{ number_format($doc->subtotal, 2) }}</td></tr>
@if($esCotizacion && ($doc->descuento ?? 0) > 0)
<tr><td>Descuento:</td><td style="color:#EF4444;">-${{ number_format($doc->descuento, 2) }}</td></tr>
@endif
<tr><td>IVA:</td><td>${{ number_format($doc->iva ?? 0, 2) }}</td></tr>
<tr class="total-final"><td>TOTAL:</td><td>${{ number_format($doc->total, 2) }} MXN</td></tr>
<tr><td colspan="2" style="font-size:7pt; padding-top:4px; font-style:italic;">{{ importeEnLetra((float)($doc->total ?? 0)) }}</td></tr>
</table>
@endif


{{-- =====================
     FOOTER FIJO
     (omitido en notas de crédito y complemento de pago)
===================== --}}
<div class="footer">
@if(!$esNotaCredito && !$esComplemento)

@if(($esOrdenCompra ?? false) || ($esFacturaCompra ?? false))
{{-- FOOTER ORDEN DE COMPRA / FACTURA COMPRA --}}
    <div style="text-align: center; margin-top: 8px; padding-top: 8px; font-size: 8pt; line-height: 1.5;">
        Documento generado por {{ $empresa->razon_social ?? '' }}<br>
        RFC: {{ $empresa->rfc ?? '' }}
    </div>
@elseif($esCotizacion)
{{-- FOOTER COTIZACIÓN: Datos bancarios ancho completo, sin PAGARÉ ni sello, cierre con Gracias por su preferencia --}}
    {{-- Datos bancarios (ancho completo) --}}
    @if($empresa->banco ?? $empresa->numero_cuenta ?? $empresa->clabe ?? false)
    <div class="banco-box" style="width: 100%;">
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

    {{-- Cierre cotización: Gracias por su preferencia (datos de empresa) --}}
    <div style="text-align: center; margin-top: 12px; padding-top: 8px; font-size: 8pt; line-height: 1.5;">
        Gracias por su preferencia<br>
        <strong>{{ $empresa->razon_social ?? '' }}</strong><br>
        RFC: {{ $empresa->rfc ?? '' }} | {{ $empresa->regimen_fiscal_etiqueta ?? $empresa->regimen_fiscal ?? '' }}
    </div>

@elseif($esCotizacionCompra ?? false)
{{-- FOOTER COTIZACIÓN DE COMPRA: sin datos bancarios ni sello --}}
    <div style="text-align: center; margin-top: 8px; padding-top: 8px; font-size: 8pt; line-height: 1.5;">
        Documento generado por {{ $empresa->razon_social ?? '' }}<br>
        RFC: {{ $empresa->rfc ?? '' }}
    </div>

@else
{{-- FOOTER FACTURA / REMISIÓN: layout original con PAGARÉ y sello --}}
@if($esFactura)
{{-- Aviso fijo: entre leyenda CFDI y bloque DATOS BANCARIOS / sello --}}
<div style="margin-top:10px; margin-bottom:10px; text-align:center; font-size:7pt; line-height:1.35;">
    <span style="color:#1E40AF;"><strong>TODO MATERIAL SE ENTREGA A PIE DE CALLE, NO INCLUYE DESCARGAS. NI MANIOBRAS</strong></span><span style="color:#DC2626;"> TODA CANCELACIÓN / DEVOLUCIÓN GENERA UN 20% DE PENALIZACIÓN Y SE TIENE UN PLAZO MAXIMO DE 5 DÍAS A PARTIR DE LA RECEPCIÓN DEL MATERIAL PARA SOLICITARLA Y QUEDA SUJETA A PREVIA AUTORIZACIÓN.</span>
</div>
@endif
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
    <td width="65%" valign="top" style="padding-right:12px;">

        {{-- Datos bancarios (solo para facturas; en remisión se omiten) --}}
        @if(($esFactura ?? false) && ($empresa->banco ?? $empresa->numero_cuenta ?? $empresa->clabe ?? false))
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

        @if($doc->condiciones_pago ?? false)
        <div class="condiciones-box">
            <strong>Condiciones:</strong><br>
            {!! nl2br(e($doc->condiciones_pago)) !!}
        </div>
        @endif

        {{-- PAGARÉ (solo factura, NO cotización) --}}
        @if($esFactura)
        @php
            $lugarPagare = trim(($empresa->municipio ?? $empresa->ciudad ?? 'Cárdenas') . ', ' . ($empresa->estado ?? 'Tabasco'));
            $beneficiarioPagare = strtoupper($empresa->razon_social) . ' (' . ($empresa->rfc ?? '') . ')';
            $montoPagare = formatMoney($doc->total ?? 0);
        @endphp
        <div class="pagare-box">
            <strong>PAGARÉ:</strong><br>
            Debo y pagaré incondicionalmente en {{ $lugarPagare }}, a la orden de {{ $beneficiarioPagare }}
            la cantidad establecida en la presente factura, por un monto de: {{ $montoPagare }} en el plazo estipulado en la misma, que iniciará a partir de
            esta fecha, por concepto de las mercancías que en este documento se detallan y que recibí a mi entera satisfacción, la firma en
            cualquier lugar de esta factura. Se entiende que se acepta el presente pagaré por la totalidad que se expresa.
        </div>
        @endif

    </td>

    <td width="35%" valign="top">
        <div class="sello-receptor-box">
            <strong>Sello de la empresa<br>que recibe el material</strong>
        </div>
    </td>
</tr>
</table>
@endif
@endif

    {{-- Nota CFDI (leyenda completa ya está en el bloque CFDI 4.0 de factura) --}}
    @if($esFactura && !($doc->uuid ?? null))
    <div style="margin-top:4px; margin-bottom:4px; font-size:7pt; color:#B45309; text-align:center;">
        Documento en borrador — no válido como comprobante fiscal.
    </div>
    @endif

</div>
{{-- /FOOTER --}}


<script type="text/php">
if (isset($pdf)) {
    $pdf->page_text(520, 770, "Pagina {PAGE_NUM} de {PAGE_COUNT}", null, 8, [0,0,0]);
}
</script>

</body>
</html>