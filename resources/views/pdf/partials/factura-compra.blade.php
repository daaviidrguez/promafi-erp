{{-- Factura de compra: proveedor (emisor), receptor (empresa), detalle, impuestos, totales --}}
@php
    $doc = $doc;
@endphp

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">PROVEEDOR (EMISOR)</div>
        <strong>{{ $doc->nombre_emisor ?? '—' }}</strong><br>
        RFC: {{ $doc->rfc_emisor ?? '—' }}
    </div>
</td>
<td width="4%"></td>
<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">RECEPTOR (EMPRESA)</div>
        <strong>{{ $doc->nombre_receptor ?? '—' }}</strong><br>
        RFC: {{ $doc->rfc_receptor ?? '—' }}<br>
        Fecha: {{ \Carbon\Carbon::parse($doc->fecha_emision)->format('d/m/Y') }}<br>
        Folio: {{ $doc->folio_completo ?? $doc->folio }}<br>
        @if($doc->uuid)
        UUID: {{ $doc->uuid }}
        @endif
    </div>
</td>
</tr>
</table>

<table class="productos-table">
<thead>
<tr>
<th>Código</th>
<th>Descripción</th>
<th class="center">Cant.</th>
<th class="right">Precio Unit.</th>
<th class="right">Importe</th>
</tr>
</thead>
<tbody>
@foreach($doc->detalles ?? [] as $d)
@php
    $impuestosLinea = 0;
    foreach ($d->impuestos ?? [] as $imp) {
        $impuestosLinea += (float) ($imp->importe ?? 0);
    }
@endphp
<tr>
<td>{{ ($d->no_identificacion ?? $d->clave_prod_serv ?? '-') }}</td>
<td>{{ $d->descripcion }}</td>
<td class="center">{{ number_format($d->cantidad, 2) }}</td>
<td class="right">${{ number_format($d->valor_unitario ?? 0, 2) }}</td>
<td class="right">${{ number_format(($d->importe ?? 0) + $impuestosLinea, 2) }}</td>
</tr>
@endforeach
</tbody>
</table>

<table class="totales-table">
<tr><td>Subtotal:</td><td>${{ number_format($doc->subtotal ?? 0, 2) }}</td></tr>
@if(($doc->descuento ?? 0) > 0)
<tr><td>Descuento:</td><td style="color:#EF4444;">-${{ number_format($doc->descuento, 2) }}</td></tr>
@endif
@php
    $ivaTotal = 0;
    foreach ($doc->detalles ?? [] as $d) {
        foreach ($d->impuestos ?? [] as $imp) {
            if (($imp->tipo ?? '') === 'traslado') {
                $ivaTotal += (float) ($imp->importe ?? 0);
            }
        }
    }
@endphp
@if($ivaTotal > 0)
<tr><td>IVA:</td><td>${{ number_format($ivaTotal, 2) }}</td></tr>
@endif
<tr class="total-final"><td>TOTAL:</td><td>${{ number_format($doc->total ?? 0, 2) }} {{ $doc->moneda ?? 'MXN' }}</td></tr>
</table>
