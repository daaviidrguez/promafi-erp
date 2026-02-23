{{-- Contenido CFDI 4.0 para factura: emisor, receptor, datos comprobante, conceptos, totales, timbre --}}
@php
    $f = $doc;
    $e = $empresa;
    $totalIva = 0;
    $totalRetenciones = 0;
    foreach ($f->detalles ?? [] as $d) {
        foreach ($d->impuestos ?? [] as $imp) {
            if ($imp->tipo === 'traslado') {
                $totalIva += (float) $imp->importe;
            } else {
                $totalRetenciones += (float) $imp->importe;
            }
        }
    }
    $fechaEmision = $f->fecha_emision ? \Carbon\Carbon::parse($f->fecha_emision) : null;
    $fechaTimbrado = $f->fecha_timbrado ? \Carbon\Carbon::parse($f->fecha_timbrado) : null;
@endphp

{{-- RECEPTOR (izq) y DATOS DEL COMPROBANTE (der). Emisor ya va en el header. --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
<tr>
    <td width="50%" valign="top" style="padding-right:8px;">
        <div class="info-box">
            <div class="section-title">RECEPTOR</div>
            <strong>RFC:</strong> {{ $f->rfc_receptor }}<br>
            <strong>Nombre:</strong> {{ $f->nombre_receptor }}<br>
            <strong>Uso CFDI:</strong> {{ $f->uso_cfdi }}<br>
            <strong>Régimen fiscal:</strong> {{ $f->regimen_fiscal_receptor ?? '-' }}<br>
            <strong>Domicilio fiscal:</strong>
            @if($f->cliente && ($f->cliente->calle || $f->cliente->codigo_postal))
                {{ $f->cliente->calle ?? '' }} {{ $f->cliente->numero_exterior ?? '' }}{{ $f->cliente->numero_interior ? ' Int. ' . $f->cliente->numero_interior : '' }}{{ ($f->cliente->colonia ?? '') ? ', ' . $f->cliente->colonia : '' }}{{ ($f->cliente->municipio ?? '') ? ', ' . $f->cliente->municipio : '' }}{{ ($f->cliente->estado ?? '') ? ', ' . $f->cliente->estado : '' }}{{ ($f->cliente->codigo_postal ?? $f->domicilio_fiscal_receptor ?? '') ? ', C.P. ' . ($f->cliente->codigo_postal ?? $f->domicilio_fiscal_receptor) : '' }}
            @else
                C.P. {{ $f->domicilio_fiscal_receptor ?? '-' }}
            @endif
        </div>
    </td>
    <td width="50%" valign="top">
        <div class="info-box">
            <div class="section-title">DATOS DEL COMPROBANTE</div>
            <table width="100%" cellpadding="0" cellspacing="0" style="font-size:8pt;">
            <tr>
                <td width="50%"><strong>Serie / Folio:</strong> {{ $f->serie ?? '' }} {{ $f->folio }}</td>
                <td width="50%"><strong>Fecha y hora de expedición:</strong> {{ $fechaEmision ? $fechaEmision->format('d/m/Y H:i:s') : '-' }}</td>
            </tr>
            <tr>
                <td><strong>Lugar de expedición:</strong> {{ $f->lugar_expedicion ?? $e->codigo_postal ?? '-' }}</td>
                <td><strong>Forma de pago:</strong> {{ $f->forma_pago ?? '-' }}</td>
            </tr>
            <tr>
                <td><strong>Método de pago:</strong> {{ $f->metodo_pago ?? '-' }}</td>
                <td><strong>Moneda:</strong> {{ $f->moneda ?? 'MXN' }}{{ $f->moneda !== 'MXN' ? ' (T.C. ' . number_format((float)($f->tipo_cambio ?? 1), 4) . ')' : '' }}</td>
            </tr>
            <tr>
                <td><strong>Tipo de comprobante:</strong> {{ $f->tipo_comprobante === 'I' ? 'Ingreso' : ($f->tipo_comprobante === 'E' ? 'Egreso' : $f->tipo_comprobante) }}</td>
                <td></td>
            </tr>
            @if(!empty($f->uuid_referencia))
            <tr>
                <td colspan="2"><strong>Factura que se acredita (UUID):</strong> {{ $f->uuid_referencia }}</td>
            </tr>
            @endif
            </table>
        </div>
    </td>
</tr>
</table>

{{-- CONCEPTOS (tabla con impuestos) --}}
<table class="productos-table">
<thead>
<tr>
    <th>Clave</th>
    <th>Cant.</th>
    <th>Unidad</th>
    <th>Descripción</th>
    <th class="right">Valor unit.</th>
    <th class="right">Importe</th>
    <th class="right">Descuento</th>
    <th class="right">Impuestos</th>
    <th class="right">Total</th>
</tr>
</thead>
<tbody>
@foreach($f->detalles ?? [] as $d)
@php
    $impuestosLinea = 0;
    foreach ($d->impuestos ?? [] as $imp) {
        $impuestosLinea += (float) $imp->importe;
    }
    $totalLinea = (float) $d->importe - (float) ($d->descuento ?? 0) + $impuestosLinea;
@endphp
<tr>
    <td>{{ $d->clave_prod_serv ?? '-' }}</td>
    <td class="center">{{ number_format($d->cantidad, 4) }}</td>
    <td>{{ $d->unidad ?? $d->clave_unidad ?? 'Pieza' }}</td>
    <td>{{ $d->descripcion }}</td>
    <td class="right">${{ number_format($d->valor_unitario, 6) }}</td>
    <td class="right">${{ number_format($d->importe, 2) }}</td>
    <td class="right">${{ number_format($d->descuento ?? 0, 2) }}</td>
    <td class="right">${{ number_format($impuestosLinea, 2) }}</td>
    <td class="right">${{ number_format($totalLinea, 2) }}</td>
</tr>
@endforeach
</tbody>
</table>

{{-- TOTALES CFDI --}}
<table class="totales-table">
<tr><td>Subtotal:</td><td>${{ number_format($f->subtotal, 2) }}</td></tr>
@if((float)($f->descuento ?? 0) > 0)
<tr><td>Descuento:</td><td style="color:#DC2626;">-${{ number_format($f->descuento, 2) }}</td></tr>
@endif
@if($totalIva > 0)
<tr><td>IVA (traslados):</td><td>${{ number_format($totalIva, 2) }}</td></tr>
@endif
@if($totalRetenciones != 0)
<tr><td>Retenciones:</td><td>-${{ number_format($totalRetenciones, 2) }}</td></tr>
@endif
<tr class="total-final">
    <td>Total:</td>
    <td>${{ number_format($f->total, 2) }} {{ $f->moneda ?? 'MXN' }}</td>
</tr>
</table>

{{-- Aviso de cancelación --}}
@if(($f->estado ?? '') === 'cancelada')
<div style="margin-top:12px; padding:10px; border:2px solid #DC2626; background:#FEE2E2; text-align:center;">
    <strong style="color:#DC2626; font-size:11pt;">ESTE COMPROBANTE HA SIDO CANCELADO</strong>
    @if($f->fecha_cancelacion)
    <br><span style="font-size:8pt;">Fecha de cancelación: {{ \Carbon\Carbon::parse($f->fecha_cancelacion)->format('d/m/Y H:i') }}</span>
    @endif
</div>
@endif

{{-- TIMBRE FISCAL DIGITAL (CFDI 4.0) --}}
<div class="timbrado-section" style="margin-top:14px;">
    <div class="section-title">TIMBRE FISCAL DIGITAL</div>
    @if($f->uuid)
        <div style="margin-bottom:6px;"><strong>Folio fiscal (UUID):</strong><br><span class="timbrado-value">{{ $f->uuid }}</span></div>
        @if($fechaTimbrado)
        <div style="margin-bottom:6px;"><strong>Fecha y hora de certificación:</strong> {{ $fechaTimbrado->format('d/m/Y H:i:s') }}</div>
        @endif
        @if($f->no_certificado_sat)
        <div style="margin-bottom:6px;"><strong>No. de serie del certificado del SAT:</strong> {{ $f->no_certificado_sat }}</div>
        @endif
        @if($f->sello_cfdi)
        <div class="timbrado-label">Sello digital del CFDI:</div>
        <div class="timbrado-value">{{ \Illuminate\Support\Str::limit($f->sello_cfdi, 80) }}</div>
        @endif
        @if($f->sello_sat)
        <div class="timbrado-label" style="margin-top:4px;">Sello del SAT:</div>
        <div class="timbrado-value">{{ \Illuminate\Support\Str::limit($f->sello_sat, 80) }}</div>
        @endif
        @if($f->cadena_original)
        <div class="timbrado-label" style="margin-top:4px;">Cadena original del complemento de certificación:</div>
        <div class="timbrado-value">{{ \Illuminate\Support\Str::limit($f->cadena_original, 120) }}</div>
        @endif
    @else
        <div style="color:#B45309; font-weight:bold;">DOCUMENTO EN BORRADOR — NO TIMBRADO</div>
    @endif
</div>

{{-- Leyenda obligatoria SAT --}}
<div style="margin-top:10px; font-size:8pt; text-align:center; color:#374151;">
    @if(($f->estado ?? '') === 'cancelada')
        <strong style="color:#DC2626;">Este comprobante fue cancelado y no tiene efectos fiscales.</strong>
    @elseif($f->uuid)
        <strong>Este documento es una representación impresa de un Comprobante Fiscal Digital por Internet.</strong>
    @else
        <strong>Documento en borrador. No es válido como comprobante fiscal hasta su timbrado.</strong>
    @endif
</div>
