@extends('layouts.app')
@section('title', 'Proveedores')
@section('page-title', '🏭 Proveedores')
@section('page-subtitle', 'Gestión de proveedores (Compras)')
@section('page-actions')
    <a href="{{ route('proveedores.create') }}" class="btn btn-primary">➕ Nuevo Proveedor</a>
@endsection

@php
$breadcrumbs = [['title' => 'Proveedores']];
@endphp

@section('content')

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('proveedores.index') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar por nombre, código, RFC..." class="form-control" style="min-width:240px;">
            <button type="submit" class="btn btn-primary">🔍 Buscar</button>
            @if($search ?? false)
            <a href="{{ route('proveedores.index') }}" class="btn btn-light">✕ Limpiar</a>
            @endif
        </form>
    </div>
</div>

<div class="table-container">
    @if($proveedores->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Proveedor</th>
                <th>RFC</th>
                <th>Contacto</th>
                <th class="td-center">Crédito</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($proveedores as $p)
            <tr>
                <td>
                    @if($p->codigo)
                        <div class="codigo-interno-erp text-mono">{{ $p->codigo }}</div>
                    @endif
                    <div class="fw-600 text-primary">{{ $p->nombre }}</div>
                </td>
                <td class="text-mono">{{ $p->rfc ?? '—' }}</td>
                <td>
                    @if($p->email)<div style="font-size:13px;">📧 {{ $p->email }}</div>@endif
                    @if($p->telefono)<div style="font-size:13px;">📱 {{ $p->telefono }}</div>@endif
                </td>
                <td class="td-center">{{ $p->dias_credito ? $p->dias_credito . ' días' : 'Contado' }}</td>
                <td class="td-center">
                    @if($p->activo)<span class="badge badge-success">Activo</span>@else<span class="badge badge-danger">Inactivo</span>@endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('proveedores.show', $p->id) }}" class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                    <a href="{{ route('proveedores.edit', $p->id) }}" class="btn btn-warning btn-sm btn-icon" title="Editar">✏️</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">{{ $proveedores->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🏭</div>
        <div class="empty-state-title">No hay proveedores</div>
        <div class="empty-state-text">Agrega proveedores para cotizaciones y órdenes de compra</div>
        <a href="{{ route('proveedores.create') }}" class="btn btn-primary" style="margin-top:16px;">➕ Nuevo Proveedor</a>
    </div>
    @endif
</div>

@push('styles')
@include('partials.codigo-interno-entity-styles')
@endpush

@endsection
