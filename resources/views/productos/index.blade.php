@extends('layouts.app')

@section('title', 'Productos')
@section('page-title', '📦 Productos')
@section('page-subtitle', 'Catálogo de productos y servicios')

@php
$breadcrumbs = [
    ['title' => 'Productos']
];
@endphp

@section('content')

{{-- Búsqueda + Acción --}}
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('productos.index') }}"
              style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; gap: 12px; flex: 1; flex-wrap: wrap;">
                <input type="text" name="search" value="{{ $search ?? '' }}"
                       placeholder="Buscar producto..." class="form-control"
                       style="flex: 1; min-width: 200px;">
                <select name="categoria_id" class="form-control" style="min-width: 180px;">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ ($categoria_id ?? '') == $cat->id ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
                <button type="submit"
                        style="padding: 9px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">
                    🔍 Buscar
                </button>
                @if(($search ?? false) || ($categoria_id ?? false))
                <a href="{{ route('productos.index') }}"
                   style="padding: 9px 16px; border: 1.5px solid var(--color-gray-300); border-radius: var(--radius-md); color: var(--color-gray-600); font-weight: 600;">
                    ✕ Limpiar
                </a>
                @endif
            </div>
            <a href="{{ route('productos.create') }}" class="btn btn-primary">➕ Nuevo Producto</a>
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="table-container">
    @if($productos->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>Categoría</th>
                <th class="td-right">Precio</th>
                <th class="td-center">Stock</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($productos as $producto)
            <tr>
                <td>
                    <span class="producto-row-code">{{ $producto->codigo }}</span>
                </td>
                <td>
                    <div class="fw-600" style="color: var(--color-primary);">{{ $producto->nombre }}</div>
                    @if($producto->descripcion)
                        <div class="text-muted" style="font-size: 12px;">
                            {{ \Str::limit($producto->descripcion, 55) }}
                        </div>
                    @endif
                </td>
                <td>
                    @if($producto->categoria)
                        <span class="badge" style="background: {{ $producto->categoria->color }}20; color: {{ $producto->categoria->color }};">
                            {{ $producto->categoria->icono }} {{ $producto->categoria->nombre }}
                        </span>
                    @else
                        <span class="text-muted">Sin categoría</span>
                    @endif
                </td>
                <td class="td-right text-mono fw-600">
                    ${{ number_format($producto->precio_venta, 2, '.', ',') }}
                </td>
                <td class="td-center">
                    @if($producto->controla_inventario)
                        <span class="fw-600"
                              style="color: {{ $producto->bajoEnStock() ? 'var(--color-danger)' : 'var(--color-success)' }};">
                            {{ number_format($producto->stock, 0) }}
                            @if($producto->bajoEnStock()) ⚠ @endif
                        </span>
                    @else
                        <span class="text-muted">N/A</span>
                    @endif
                </td>
                <td class="td-center">
                    @if($producto->activo)
                        <span class="badge badge-success">✓ Activo</span>
                    @else
                        <span class="badge badge-danger">✗ Inactivo</span>
                    @endif
                </td>
                <td class="td-actions">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <a href="{{ route('productos.show', $producto->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                        <a href="{{ route('productos.edit', $producto->id) }}"
                           class="btn btn-warning btn-sm btn-icon" title="Editar">✏️</a>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $productos->withQueryString()->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <div class="empty-state-title">No hay productos registrados</div>
        <div class="empty-state-text">Comienza agregando tu primer producto al catálogo</div>
        <div style="margin-top: 20px;">
            <a href="{{ route('productos.create') }}" class="btn btn-primary">➕ Crear Primer Producto</a>
        </div>
    </div>
    @endif
</div>

@endsection