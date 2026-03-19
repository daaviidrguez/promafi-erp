{{-- Cotización de Compra: proveedor, detalle productos, totales --}}
<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">PROVEEDOR</div>
        <strong>{{ $doc->proveedor_nombre ?? '—' }}</strong><br>
        RFC: {{ $doc->proveedor_rfc ?? '—' }}<br>
        @if($doc->proveedor_email) Email: {{ $doc->proveedor_email }}<br>@endif
        @if($doc->proveedor_telefono) Tel: {{ $doc->proveedor_telefono }}<br>@endif
    </div>
</td>
<td width="4%"></td>
<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">INFORMACIÓN</div>
        Fecha: {{ \Carbon\Carbon::parse($doc->fecha)->format('d/m/Y') }}<br>
        Folio: {{ $doc->folio }}<br>
        @if($doc->fecha_vencimiento)
        Válida hasta: {{ \Carbon\Carbon::parse($doc->fecha_vencimiento)->format('d/m/Y') }}<br>
        @endif
        Moneda: {{ $doc->moneda ?? 'MXN' }}<br>
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
<th class="right">Costo Unit.</th>
<th class="right">Total</th>
</tr>
</thead>
<tbody>
@foreach($doc->detalles ?? [] as $d)
<tr>
<td>{{ ($d->codigo === 'MANUAL' || $d->codigo === null) ? '—' : $d->codigo }}</td>
<td>{{ $d->descripcion }}</td>
<td class="center">{{ number_format($d->cantidad, 2) }}</td>
<td class="right">${{ number_format($d->precio_unitario ?? 0, 2) }}</td>
<td class="right">${{ number_format($d->total ?? 0, 2) }}</td>
</tr>
@endforeach
</tbody>
</table>

<table class="totales-table">
<tr><td>Subtotal:</td><td>${{ number_format($doc->subtotal ?? 0, 2) }}</td></tr>
@if(($doc->descuento ?? 0) > 0)
<tr><td>Descuento:</td><td style="color:#EF4444;">-${{ number_format($doc->descuento, 2) }}</td></tr>
@endif
<tr><td>IVA:</td><td>${{ number_format($doc->iva ?? 0, 2) }}</td></tr>
<tr class="total-final"><td>TOTAL:</td><td>${{ number_format($doc->total ?? 0, 2) }} MXN</td></tr>
</table>

