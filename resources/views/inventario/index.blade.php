@extends('layouts.app')
@section('title', 'Inventario')
@section('page-title', '📦 Inventario')
@section('page-subtitle', 'Stock por producto y control de entradas/salidas')
@section('page-actions')
    <a href="{{ route('inventario.create-movimiento') }}" class="btn btn-primary">➕ Entrada / Salida manual</a>
    <a href="{{ route('inventario.movimientos') }}" class="btn btn-outline">📋 Movimientos</a>
@endsection

@php
$breadcrumbs = [['title' => 'Inventario']];
@endphp

@section('content')

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('inventario.index') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar producto..." class="form-control" style="min-width:220px;">
            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;">
                <input type="checkbox" name="bajo_stock" value="1" {{ $bajoStock ?? false ? 'checked' : '' }}> Solo bajo stock
            </label>
            <button type="submit" class="btn btn-primary">🔍 Buscar</button>
            @if($search ?? $bajoStock ?? false)
            <a href="{{ route('inventario.index') }}" class="btn btn-light">✕ Limpiar</a>
            @endif
        </form>
    </div>
</div>

<div class="table-container">
    @if($productos->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Producto</th>
                <th class="td-center">Stock actual</th>
                <th class="td-center">Mínimo</th>
                <th class="td-center">Máximo</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($productos as $p)
            <tr>
                <td>
                    <div class="fw-600 text-primary">{{ $p->nombre }}</div>
                    <span class="text-muted" style="font-size:12px;">{{ $p->codigo }}</span>
                </td>
                <td class="td-center text-mono fw-600">{{ number_format($p->stock, 2) }}</td>
                <td class="td-center text-mono">{{ number_format($p->stock_minimo, 2) }}</td>
                <td class="td-center text-mono">{{ $p->stock_maximo !== null ? number_format($p->stock_maximo, 2) : '—' }}</td>
                <td class="td-center">
                    @if($p->bajoEnStock())
                        <span class="badge badge-danger">Bajo stock</span>
                    @elseif($p->stock_maximo && (float)$p->stock >= (float)$p->stock_maximo)
                        <span class="badge badge-info">En máximo</span>
                    @else
                        <span class="badge badge-success">OK</span>
                    @endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('inventario.show-producto', $p->id) }}" class="btn btn-info btn-sm btn-icon" title="Ver movimientos">👁️</a>
                    <a href="{{ route('productos.edit', $p->id) }}" class="btn btn-warning btn-sm btn-icon" title="Editar producto">✏️</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">{{ $productos->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <div class="empty-state-title">No hay productos con inventario</div>
        <div class="empty-state-text">Los productos con "Controlar inventario" activado aparecen aquí. Crea productos desde Catálogos → Productos.</div>
        <a href="{{ route('productos.index') }}" class="btn btn-primary" style="margin-top:16px;">Ir a Productos</a>
    </div>
    @endif
</div>

@endsection
