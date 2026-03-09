{{-- Complemento de Pago: receptor, pagos recibidos, documentos relacionados (facturas pagadas) --}}
@php
    $c = $doc;
@endphp

<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:12px;">
<tr>
    <td width="50%" valign="top" style="padding-right:8px;">
        <div class="info-box">
            <div class="section-title">RECEPTOR</div>
            <strong>RFC:</strong> {{ $c->rfc_receptor }}<br>
            <strong>Nombre:</strong> {{ $c->nombre_receptor }}
        </div>
    </td>
    <td width="50%" valign="top">
        <div class="info-box">
            <div class="section-title">DATOS DEL COMPROBANTE</div>
            <strong>Fecha de pago (emisión):</strong> {{ $c->fecha_emision ? \Carbon\Carbon::parse($c->fecha_emision)->format('d/m/Y H:i') : '-' }}<br>
            <strong>Lugar de expedición:</strong> {{ $c->lugar_expedicion ?? '-' }}
            @if($c->uuid)
            <div style="margin-top:8px;"><strong>Folio fiscal (UUID):</strong><br>
                <span class="timbrado-value" style="font-size:6.5pt; word-break:break-all;">{{ $c->uuid }}</span>
            </div>
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
<tr>
    <td>{{ $pago->fecha_pago ? \Carbon\Carbon::parse($pago->fecha_pago)->format('d/m/Y') : '-' }}</td>
    <td>{{ $pago->forma_pago }}</td>
    <td class="center">{{ $pago->moneda ?? 'MXN' }}</td>
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
</tr>
</thead>
<tbody>
@foreach($c->pagosRecibidos ?? [] as $pago)
    @foreach($pago->documentosRelacionados ?? [] as $doc)
    @php
        $cuenta = $doc->factura->cuentaPorCobrar ?? null;
        $saldoActual = $cuenta ? (float) $cuenta->saldo_pendiente_real : 0;
        $saldoAnterior = $saldoActual + (float) $doc->monto_pagado;
        $saldoInsoluto = $saldoActual;
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

<div style="margin-top:10px; font-size:8pt; text-align:center; color:#374151;">
    <strong>Este documento es una representación impresa de un Comprobante Fiscal Digital por Internet (Complemento de Pago).</strong>
</div>
