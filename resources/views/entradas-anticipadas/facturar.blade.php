@extends('layouts.app')
@section('title', 'Facturar '.$entrada->folio)
@section('page-title', '🧾 Registrar factura')
@section('page-subtitle', $entrada->folio.' — '.$entrada->proveedor?->nombre)

@php
$breadcrumbs = [
    ['title' => 'Entradas anticipadas', 'url' => route('entradas-anticipadas.index')],
    ['title' => $entrada->folio, 'url' => route('entradas-anticipadas.show', $entrada->id)],
    ['title' => 'Facturar'],
];
@endphp

@section('content')

<div class="card" style="max-width:520px;">
    <div class="card-header"><div class="card-title">Elija cómo registrar la factura</div></div>
    <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
        <p class="text-muted" style="margin:0;font-size:14px;">La mercancía ya está en inventario. Al facturar se crea la compra y, si aplica PPD, la cuenta por pagar. Si la EA tiene costos estimados en $0 y el CFDI trae precios reales, podrá confirmar el desfase de totales: se actualizarán los costos de producto y, si hay ventas timbradas con costo $0, también el reporte de utilidad.</p>
        <a href="{{ route('compras.upload-cfdi', ['entrada_anticipada_id' => $entrada->id]) }}" class="btn btn-success w-full" style="text-align:center;">A — Subir CFDI (XML + PDF)</a>
        <a href="{{ route('compras.create', ['entrada_anticipada_id' => $entrada->id]) }}" class="btn btn-outline w-full" style="text-align:center;">B — Registrar compra manual</a>
        <a href="{{ route('entradas-anticipadas.show', $entrada->id) }}" class="btn btn-light w-full" style="text-align:center;">← Volver</a>
    </div>
</div>

@endsection
