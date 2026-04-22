@extends('layouts.app')
@section('title', 'Leer CFDI')
@section('page-title', '📄 Leer CFDI')
@section('page-subtitle', 'Sube el XML de la factura de compra para cargar los datos')

@php
$breadcrumbs = [
    ['title' => 'Compras', 'url' => route('compras.index')],
    ['title' => 'Leer CFDI'],
];
@endphp

@section('content')

@if(!empty($ordenOrigenConversion))
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
            El sistema leerá los datos y abrirá un formulario para que vincule cada línea del detalle a un producto (lupa en Código). En la ficha de la compra podrá usar <strong>Recibir mercancía</strong> para registrar la entrada en inventario.
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
            <div class="form-group mt-3">
                <button type="submit" class="btn btn-primary">📤 Procesar CFDI</button>
                <a href="{{ route('compras.descartar-vinculo-orden-oc') }}" class="btn btn-light">Cancelar</a>
            </div>
        </form>
    </div>
</div>

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

@endsection
