{{-- Complemento de Pago: receptor, pagos recibidos, documentos relacionados (facturas pagadas) - SAT 2026 --}}
@php
    $c = $doc;
    $e = $empresa ?? null;
    $fechaEmision = $c->fecha_emision ? \Carbon\Carbon::parse($c->fecha_emision) : null;
    $fechaTimbrado = $c->fecha_timbrado ? \Carbon\Carbon::parse($c->fecha_timbrado) : null;
    $primerPago = ($c->pagosRecibidos ?? collect())->first();
    $formaPagoEtiqueta = $primerPago ? (\App\Models\FormaPago::where('clave', $primerPago->forma_pago)->first()?->etiqueta ?? $primerPago->forma_pago) : '-';
    $regimenReceptorEtiqueta = '-';
    if ($c->cliente && $c->cliente->regimen_fiscal) {
        $regimenReceptorEtiqueta = \App\Models\RegimenFiscal::where('clave', $c->cliente->regimen_fiscal)->first()?->etiqueta ?? $c->cliente->regimen_fiscal;
    }
    $qrVerificacionDataUri = null;
    if ($c->uuid && $c->rfc_emisor && $c->rfc_receptor) {
        $totalComplemento = (float) ($c->monto_total ?? 0);
        $urlVerificacion = urlVerificacionSat($c->uuid, $c->rfc_emisor, $c->rfc_receptor, $totalComplemento, $c->sello_cfdi ?? null);
        $qrVerificacionDataUri = qrCodeDataUri($urlVerificacion, 110);
    }
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
<tr>
    <td width="50%" valign="top" style="padding-right:8px;">
        <div class="info-box">
            <div class="section-title">RECEPTOR</div>
            <strong>RFC:</strong> {{ $c->rfc_receptor }}<br>
            <strong>Nombre:</strong> {{ $c->nombre_receptor }}<br>
            <strong>Uso CFDI:</strong> CP01 - Pagos<br>
            <strong>Régimen fiscal:</strong> {{ $regimenReceptorEtiqueta }}<br>
            <strong>Domicilio fiscal:</strong> C.P. {{ optional($c->cliente)->codigo_postal ?? '-' }}
        </div>
    </td>
    <td width="50%" valign="top">
        <div class="info-box">
            <div class="section-title">DATOS DEL COMPROBANTE</div>
            <strong>Serie / Folio:</strong> {{ $c->serie ?? '' }} {{ $c->folio }}<br>
            <strong>Fecha y hora de expedición:</strong> {{ $fechaEmision ? $fechaEmision->format('d/m/Y H:i:s') : '-' }}<br>
            <strong>Lugar de expedición:</strong> {{ $c->lugar_expedicion ?? ($e->codigo_postal ?? '-') }}<br>
            <strong>Forma de pago:</strong> {{ $formaPagoEtiqueta }}<br>
            <strong>Moneda:</strong> MXN<br>
            <strong>Tipo de comprobante:</strong> Pago (P)<br>
            <strong>Versión:</strong> CFDI 4.0 / Complemento de pago 2.0<br>
            @if($c->uuid)
            <strong>Folio fiscal (UUID):</strong><br>
            <span class="timbrado-value" style="font-size:6.5pt; word-break:break-all;">{{ $c->uuid }}</span><br>
            @endif
            @if($e && ($e->no_certificado ?? null))
            <strong>No. de serie del certificado del emisor:</strong> {{ $e->no_certificado }}<br>
            @endif
            @if($c->no_certificado_sat ?? null)
            <strong>No. de serie del certificado del SAT:</strong> {{ $c->no_certificado_sat }}<br>
            @endif
            @if($fechaTimbrado)
            <strong>Fecha y hora de certificación:</strong> {{ $fechaTimbrado->format('d/m/Y H:i:s') }}<br>
            @endif
        </div>
    </td>
</tr>
</table>

{{-- Pagos recibidos --}}
<table class="productos-table">
<thead>
<tr>
    <th>Fecha</th>
    <th>Forma de pago</th>
    <th class="center">Moneda</th>
    <th class="right">Monto</th>
</tr>
</thead>
<tbody>
@foreach($c->pagosRecibidos ?? [] as $pago)
@php
    $formaEtiqueta = \App\Models\FormaPago::where('clave', $pago->forma_pago)->first()?->etiqueta ?? $pago->forma_pago;
@endphp
<tr>
    <td>{{ $pago->fecha_pago ? \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') : '-' }}</td>
    <td>{{ $formaEtiqueta }}</td>
    <td class="center">{{ $pago->moneda ?? 'MXN' }}{{ ($pago->moneda ?? 'MXN') !== 'MXN' && $pago->tipo_cambio ? ' (T.C. ' . number_format((float)$pago->tipo_cambio, 4, '.', ',') . ')' : '' }}</td>
    <td class="right">${{ number_format($pago->monto, 2) }}</td>
</tr>
@endforeach
</tbody>
</table>

{{-- Documentos relacionados (facturas pagadas) --}}
<div class="section-title" style="margin-top:14px;">FACTURAS PAGADAS (DOCUMENTOS RELACIONADOS)</div>
<table class="productos-table">
<thead>
<tr>
    <th>Factura</th>
    <th class="center">Parcialidad</th>
    <th class="right">Saldo anterior</th>
    <th class="right">Monto pagado</th>
    <th class="right">Saldo insoluto</th>
    <th class="right">Impuestos</th>
</tr>
</thead>
<tbody>
@foreach($c->pagosRecibidos ?? [] as $pago)
    @foreach($pago->documentosRelacionados ?? [] as $doc)
    @php
        $facturaDoc = $doc->factura ?? null;
        $cuenta = $facturaDoc ? $facturaDoc->cuentaPorCobrar : null;
        $saldoActual = $cuenta ? (float) $cuenta->saldo_pendiente_real : 0;
        $saldoAnterior = $saldoActual + (float) $doc->monto_pagado;
        $saldoInsoluto = $saldoActual;

        $impuestosTraslados = 0;
        $impuestosRetenciones = 0;
        if ($facturaDoc) {
            foreach ($facturaDoc->detalles ?? [] as $d) {
                foreach ($d->impuestos ?? [] as $imp) {
                    if (($imp->tipo ?? 'traslado') === 'retencion') {
                        $impuestosRetenciones += (float) ($imp->importe ?? 0);
                    } else {
                        $impuestosTraslados += (float) ($imp->importe ?? 0);
                    }
                }
            }
        }
        $impuestosTexto = '-';
        if ($impuestosTraslados > 0 || $impuestosRetenciones > 0) {
            $partes = [];
            if ($impuestosTraslados > 0) {
                $partes[] = '$' . number_format($impuestosTraslados, 2);
            }
            if ($impuestosRetenciones > 0) {
                $partes[] = '<span style="font-size:6.5pt; color:#6B7280;">Ret: -$' . number_format($impuestosRetenciones, 2) . '</span>';
            }
            $impuestosTexto = implode('<br>', $partes);
        }
    @endphp
    <tr>
        <td>
            {{ $doc->serie ?? '' }} {{ $doc->folio }}<br>
            <span style="font-size:6.5pt; color:#6B7280;">{{ $doc->factura_uuid ?? '' }}</span>
        </td>
        <td class="center">{{ $doc->parcialidad }}</td>
        <td class="right">${{ number_format($saldoAnterior, 2) }}</td>
        <td class="right">${{ number_format($doc->monto_pagado, 2) }}</td>
        <td class="right">${{ number_format($saldoInsoluto, 2) }}</td>
        <td class="right">{!! $impuestosTexto !!}</td>
    </tr>
    @endforeach
@endforeach
</tbody>
</table>

{{-- Total --}}
<table class="totales-table">
<tr class="total-final">
    <td>Monto total del complemento:</td>
    <td>${{ number_format($c->monto_total, 2) }} MXN</td>
</tr>
</table>

{{-- Sellos, cadena original y QR (mismo diseño que factura: 85% textos, 15% QR) --}}
<div class="timbrado-section" style="margin-top:4px;">
    @if($c->uuid)
        <table width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;">
        <tr>
            @if($qrVerificacionDataUri)
            <td width="15%" valign="top" style="padding-right:8px; text-align:center;">
                <img src="{{ $qrVerificacionDataUri }}"
                    style="width:110px; height:110px; display:block; margin:0 auto;"
                    alt="QR Verificación SAT">
            </td>
            @endif
            <td width="{{ $qrVerificacionDataUri ? '85%' : '100%' }}" valign="top"
                style="overflow:hidden; word-break:break-all; overflow-wrap:break-word;">
                <div class="timbrado-label">Sello digital del CFDI:</div>
                <div class="timbrado-value"
                     style="font-size:5pt; word-break:break-all; overflow-wrap:break-word; white-space:normal;">
                    {{ $c->sello_cfdi ?: '—' }}
                </div>
                <div class="timbrado-label" style="margin-top:2px;">Sello del SAT:</div>
                <div class="timbrado-value"
                     style="font-size:5pt; word-break:break-all; overflow-wrap:break-word; white-space:normal;">
                    {{ $c->sello_sat ?: '—' }}
                </div>
                <div class="timbrado-label" style="margin-top:2px;">
                    Cadena original del complemento de certificación digital del SAT:
                </div>
                <div class="timbrado-value"
                     style="font-size:5pt; word-break:break-all; overflow-wrap:break-word; white-space:normal;">
                    {{ $c->cadena_original ?: '—' }}
                </div>
            </td>
        </tr>
        </table>
    @else
        <div style="color:#B45309; font-weight:bold;">DOCUMENTO EN BORRADOR</div>
    @endif
</div>

<div style="margin-top:10px; font-size:8pt; text-align:center; color:#374151;">
    <strong>Este documento es una representación impresa de un Comprobante Fiscal Digital por Internet (Complemento de Pago).</strong>
</div>
