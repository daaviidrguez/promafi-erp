@extends('layouts.app')
@section('title', $proveedor->nombre)
@section('page-title', $proveedor->nombre)
@section('page-subtitle', 'Proveedor')

@php $breadcrumbs = [['title' => 'Proveedores', 'url' => route('proveedores.index')], ['title' => $proveedor->nombre]]; @endphp

@section('content')
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Datos del Proveedor</div>
                <a href="{{ route('proveedores.edit', $proveedor->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Nombre</div><div class="info-value">{{ $proveedor->nombre }}</div></div>
                    <div class="info-row"><div class="info-label">Código</div><div class="info-value text-mono">{{ $proveedor->codigo ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $proveedor->rfc ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Días crédito</div><div class="info-value">{{ $proveedor->dias_credito ? $proveedor->dias_credito . ' días' : 'Contado' }}</div></div>
                    @if($proveedor->email)<div class="info-row"><div class="info-label">Email</div><div class="info-value">{{ $proveedor->email }}</div></div>@endif
                    @if($proveedor->telefono)<div class="info-row"><div class="info-label">Teléfono</div><div class="info-value">{{ $proveedor->telefono }}</div></div>@endif
                    <div class="info-row"><div class="info-label">Estado</div><div>@if($proveedor->activo)<span class="badge badge-success">Activo</span>@else<span class="badge badge-danger">Inactivo</span>@endif</div></div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <a href="{{ route('cotizaciones-compra.create') }}?proveedor_id={{ $proveedor->id }}" class="btn btn-primary w-full">📋 Nueva cotización de compra</a>
                <a href="{{ route('ordenes-compra.create') }}" class="btn btn-outline w-full">📦 Nueva orden de compra</a>
                <a href="{{ route('proveedores.edit', $proveedor->id) }}" class="btn btn-outline w-full">✏️ Editar</a>
            </div>
        </div>
    </div>
</div>
@endsection
