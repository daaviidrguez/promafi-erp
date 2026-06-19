{{-- Entrada anticipada: proveedor, detalle recibido, totales --}}
@php
    $regimenClave = $doc->proveedor->regimen_fiscal ?? null;
    $regimenDesc = $regimenClave
        ? (\App\Models\RegimenFiscal::where('clave', $regimenClave)->value('descripcion') ?? null)
        : null;
@endphp

<table width="100%" cellpadding="0" cellspacing="0">
<tr>
<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">PROVEEDOR</div>
        <strong>{{ $doc->proveedor?->nombre ?? '—' }}</strong><br>
        RFC: {{ $doc->proveedor?->rfc ?? '—' }}<br>
        Régimen Fiscal:
        @if($regimenClave)
            {{ $regimenClave }}{{ $regimenDesc ? ' - ' . $regimenDesc : '' }}
        @else
            —
        @endif
        <br>
    </div>
</td>
<td width="4%"></td>
<td width="48%" valign="top">
    <div class="info-box">
        <div class="section-title">INFORMACIÓN</div>
        Fecha recepción: {{ $doc->fecha_recepcion?->format('d/m/Y') ?? '—' }}<br>
        Folio: {{ $doc->folio }}<br>
        @if($doc->ordenCompra)
        Orden de compra: {{ $doc->ordenCompra->folio }}<br>
        @endif
        Moneda: {{ $doc->moneda ?? 'MXN' }}<br>
        Estado: {{ $doc->etiquetaEstado() }}<br>
    </div>
</td>
</tr>
</table>

@if(!empty($doc->observaciones))
<table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 2px;">
<tr>
<td width="100%" valign="top">
    <div class="info-box" style="margin-bottom: 2px;">
        <div class="section-title">OBSERVACIONES</div>
        {!! nl2br(e($doc->observaciones)) !!}
    </div>
</td>
</tr>
</table>
@endif

<table class="productos-table">
<thead>
<tr>
<th>Código</th>
<th>Cód. proveedor</th>
<th>Descripción</th>
<th class="center">Cant. recibida</th>
<th class="right">Costo s/IVA</th>
<th class="center">IVA</th>
<th class="right">Total</th>
</tr>
</thead>
<tbody>
@foreach($doc->detalles ?? [] as $d)
<tr>
<td>{{ $d->producto?->codigo ?? '—' }}</td>
<td class="text-mono">{{ $d->codigo_proveedor ? $d->codigo_proveedor : '—' }}</td>
<td>{{ $d->descripcion }}</td>
<td class="center">{{ number_format($d->cantidad_recibida, 2) }}</td>
<td class="right">${{ number_format($d->precio_unitario_estimado ?? 0, 2) }}</td>
<td class="center">{{ $d->etiquetaTasaIva() }}</td>
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

<p style="font-size:7pt;color:#6B7280;margin-top:6px;line-height:1.35;">
    Documento de recepción anticipada. Los costos unitarios son sin IVA; el total incluye IVA por línea.
    El inventario se registró al confirmar esta entrada.
</p>
