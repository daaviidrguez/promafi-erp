@extends('layouts.app')
@section('title', $entradaAnticipada ? 'CFDI — '.$entradaAnticipada->folio : 'Leer CFDI')
@section('page-title', $entradaAnticipada ? '📄 Subir CFDI' : '📄 Leer CFDI')
@section('page-subtitle', $entradaAnticipada ? 'Facturar '.$entradaAnticipada->folio : 'Sube el XML de la factura de compra para cargar los datos')

@php
$breadcrumbs = $entradaAnticipada
    ? [
        ['title' => 'Entradas anticipadas', 'url' => route('entradas-anticipadas.index')],
        ['title' => $entradaAnticipada->folio, 'url' => route('entradas-anticipadas.show', $entradaAnticipada->id)],
        ['title' => 'Subir CFDI'],
    ]
    : [
        ['title' => 'Compras', 'url' => route('compras.index')],
        ['title' => 'Leer CFDI'],
    ];
@endphp

@section('content')

@if(!empty($entradaAnticipada))
<div class="card mb-3" style="max-width:600px;border-left:4px solid var(--color-info);">
    <div class="card-body" style="font-size:14px;">
        <strong>Entrada anticipada {{ $entradaAnticipada->folio }}</strong> — La mercancía ya está en inventario.
        Al guardar se crea la compra vinculada; el total del CFDI debe coincidir con el de la entrada (incluye IVA).
        @if($entradaAnticipada->ordenCompra)
        <span class="text-muted"> · OC {{ $entradaAnticipada->ordenCompra->folio }}</span>
        @endif
        <div class="totales-panel" style="max-width:320px;margin-top:12px;">
            <div class="totales-row"><span>Subtotal EA</span><span class="monto text-mono">${{ number_format($entradaAnticipada->subtotal, 2) }}</span></div>
            @if($entradaAnticipada->descuento > 0)<div class="totales-row descuento"><span>Descuento</span><span class="monto">−${{ number_format($entradaAnticipada->descuento, 2) }}</span></div>@endif
            <div class="totales-row"><span>IVA EA</span><span class="monto text-mono">${{ number_format($entradaAnticipada->iva, 2) }}</span></div>
            <div class="totales-row grand"><span>Total EA</span><span class="monto">${{ number_format($entradaAnticipada->total, 2) }}</span></div>
        </div>
        <p class="text-muted" style="margin:12px 0 0;font-size:13px;">Proveedor: <strong>{{ $entradaAnticipada->proveedor?->nombre }}</strong></p>
    </div>
</div>
@elseif(!empty($ordenOrigenConversion))
<div class="card mb-3" style="max-width: 600px;border-left:4px solid var(--color-info);">
    <div class="card-body" style="font-size:14px;">
        <strong>Orden de compra {{ $ordenOrigenConversion->folio }}</strong> — Al guardar la compra desde este CFDI, la orden quedará vinculada y marcada como convertida (el total del XML debe coincidir con el de la orden).
    </div>
</div>
@endif

<div class="card" style="max-width: 600px;">
    <div class="card-header">
        <div class="card-title">Subir archivo XML</div>
    </div>
    <div class="card-body">
        <p class="text-muted mb-3">
            Sube el archivo XML del CFDI de la factura de compra emitida por tu proveedor.
            El sistema leerá los datos y abrirá un formulario para que vincule cada línea del detalle a un producto (lupa en Código).
            @if($entradaAnticipada)
            La mercancía ya está en inventario; no se volverá a recibir.
            @else
            En la ficha de la compra podrá usar <strong>Recibir mercancía</strong> para registrar la entrada en inventario.
            @endif
        </p>
        <form method="POST" action="{{ route('compras.upload-cfdi') }}" enctype="multipart/form-data">
            @csrf
            <div class="form-group">
                <label class="form-label">Archivo XML <span class="req">*</span></label>
                <input type="file" name="xml_file" accept=".xml" required class="form-control">
                <span class="form-hint">Formato CFDI 4.0 o 3.3. Tamaño máximo: 5 MB</span>
            </div>
            @error('xml_file')
            <p class="text-danger small mt-1">{{ $message }}</p>
            @enderror
            @if($entradaAnticipada)
            <div class="form-group mt-3">
                <label class="form-label">PDF del proveedor</label>
                <input type="file" name="pdf_file" accept=".pdf" class="form-control">
                <span class="form-hint">Recomendado — opcional</span>
            </div>
            @endif
            <div class="form-group mt-3">
                <button type="submit" class="btn btn-primary">📤 Procesar CFDI</button>
                @if($entradaAnticipada)
                <a href="{{ route('compras.descartar-vinculo-entrada-anticipada') }}" class="btn btn-light">Cancelar</a>
                @else
                <a href="{{ route('compras.descartar-vinculo-orden-oc') }}" class="btn btn-light">Cancelar</a>
                @endif
            </div>
        </form>
    </div>
</div>

@if(!$entradaAnticipada)
<div class="card mt-3" style="max-width: 600px;">
    <div class="card-body">
        <strong>Requisitos:</strong>
        <ul class="mb-0" style="padding-left:1.2em;">
            <li>El RFC receptor del CFDI debe coincidir con el RFC de tu empresa</li>
            <li>Si el proveedor está en tu catálogo (mismo RFC), se vinculará automáticamente</li>
            <li>Si es PPD y el proveedor tiene días de crédito, se creará la cuenta por pagar</li>
        </ul>
    </div>
</div>
@endif

@endsection
