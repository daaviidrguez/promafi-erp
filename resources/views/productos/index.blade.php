@extends('layouts.app')

@section('title', 'Productos')
@section('page-title', '📦 Productos')
@section('page-subtitle', 'Catálogo de productos y servicios')

@php
$breadcrumbs = [
    ['title' => 'Productos']
];
$qsBase = request()->except('page');
$sortLink = function (string $col, string $d) use ($qsBase) {
    return route('productos.index', array_merge($qsBase, ['sort' => $col, 'dir' => $d]));
};
$isSorted = fn (string $col) => ($sort ?? 'nombre') === $col;
$dirAsc = ($dir ?? 'asc') === 'asc';
@endphp

@section('content')

<form method="GET" action="{{ route('productos.index') }}" id="form-productos-filtros">
    <input type="hidden" name="sort" value="{{ $sort ?? 'nombre' }}">
    <input type="hidden" name="dir" value="{{ $dir ?? 'asc' }}">

{{-- Búsqueda + Acción --}}
<div class="card">
    <div class="card-body">
        <div style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
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
                @if($hayFiltros ?? false)
                <a href="{{ route('productos.index') }}"
                   style="padding: 9px 16px; border: 1.5px solid var(--color-gray-300); border-radius: var(--radius-md); color: var(--color-gray-600); font-weight: 600;">
                    ✕ Limpiar todo
                </a>
                @endif
            </div>
            <a href="{{ route('productos.create') }}" class="btn btn-primary">➕ Nuevo Producto</a>
        </div>
    </div>
</div>

@if($mostrarTablaFiltros ?? true)
{{-- Tabla con orden y filtros por columna --}}
<div class="table-container">
    <table class="table-productos-filtros">
        <thead>
            <tr>
                <th>
                    <div class="th-sort-title">Código</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('codigo', 'asc') }}" class="{{ $isSorted('codigo') && $dirAsc ? 'active' : '' }}" title="A → Z">A→Z</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('codigo', 'desc') }}" class="{{ $isSorted('codigo') && !$dirAsc ? 'active' : '' }}" title="Z → A">Z→A</a>
                    </div>
                </th>
                <th>
                    <div class="th-sort-title">Producto</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('nombre', 'asc') }}" class="{{ $isSorted('nombre') && $dirAsc ? 'active' : '' }}">A→Z</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('nombre', 'desc') }}" class="{{ $isSorted('nombre') && !$dirAsc ? 'active' : '' }}">Z→A</a>
                    </div>
                </th>
                <th>
                    <div class="th-sort-title">Categoría</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('categoria', 'asc') }}" class="{{ $isSorted('categoria') && $dirAsc ? 'active' : '' }}">A→Z</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('categoria', 'desc') }}" class="{{ $isSorted('categoria') && !$dirAsc ? 'active' : '' }}">Z→A</a>
                    </div>
                </th>
                <th class="td-right">
                    <div class="th-sort-title">Precio</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('precio_venta', 'asc') }}" class="{{ $isSorted('precio_venta') && $dirAsc ? 'active' : '' }}">↑ Menor</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('precio_venta', 'desc') }}" class="{{ $isSorted('precio_venta') && !$dirAsc ? 'active' : '' }}">↓ Mayor</a>
                    </div>
                </th>
                <th class="td-center">
                    <div class="th-sort-title">Stock</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('stock', 'asc') }}" class="{{ $isSorted('stock') && $dirAsc ? 'active' : '' }}">↑ Menor</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('stock', 'desc') }}" class="{{ $isSorted('stock') && !$dirAsc ? 'active' : '' }}">↓ Mayor</a>
                    </div>
                </th>
                <th class="td-center">
                    <div class="th-sort-title">Estado</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('activo', 'desc') }}" class="{{ $isSorted('activo') && !$dirAsc ? 'active' : '' }}">Activos primero</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('activo', 'asc') }}" class="{{ $isSorted('activo') && $dirAsc ? 'active' : '' }}">Inactivos primero</a>
                    </div>
                </th>
                <th class="td-actions">
                    <div class="th-sort-title">Acciones</div>
                    <button type="submit" class="btn-col-filter" title="Aplicar filtros de columnas">✓ Filtros</button>
                </th>
            </tr>
            <tr class="tr-filtros-columna">
                <th>
                    <input type="text" name="f_codigo" value="{{ $fCodigo ?? '' }}" class="form-control input-col-filter" placeholder="Contiene…">
                </th>
                <th>
                    <input type="text" name="f_nombre" value="{{ $fNombre ?? '' }}" class="form-control input-col-filter" placeholder="Nombre o desc.">
                </th>
                <th>
                    <select name="f_categoria_col" class="form-control input-col-filter">
                        <option value="">Todas</option>
                        <option value="sin" {{ ($fCategoriaCol ?? '') === 'sin' ? 'selected' : '' }}>Sin categoría</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}" {{ (string)($fCategoriaCol ?? '') === (string)$cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                        @endforeach
                    </select>
                </th>
                <th class="td-right">
                    <input type="number" name="f_precio_min" value="{{ $fPrecioMin ?? '' }}" class="form-control input-col-filter" placeholder="Mín" step="0.01" min="0" style="margin-bottom: 4px;">
                    <input type="number" name="f_precio_max" value="{{ $fPrecioMax ?? '' }}" class="form-control input-col-filter" placeholder="Máx" step="0.01" min="0">
                </th>
                <th class="td-center">
                    <select name="f_stock" class="form-control input-col-filter">
                        <option value="">Todos</option>
                        <option value="na" {{ ($fStock ?? '') === 'na' ? 'selected' : '' }}>N/A (sin inv.)</option>
                        <option value="inventario" {{ ($fStock ?? '') === 'inventario' ? 'selected' : '' }}>Con inventario</option>
                        <option value="bajo" {{ ($fStock ?? '') === 'bajo' ? 'selected' : '' }}>Bajo mínimo ⚠</option>
                    </select>
                </th>
                <th class="td-center">
                    <select name="f_activo" class="form-control input-col-filter">
                        <option value="">Todos</option>
                        <option value="1" {{ ($fActivo ?? '') === '1' ? 'selected' : '' }}>Solo activos</option>
                        <option value="0" {{ ($fActivo ?? '') === '0' ? 'selected' : '' }}>Solo inactivos</option>
                    </select>
                </th>
                <th class="td-actions"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($productos as $producto)
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
            @empty
            <tr>
                <td colspan="7" style="padding: 48px 24px; text-align: center;">
                    <div class="empty-state-icon" style="font-size: 2.5rem;">📦</div>
                    <div style="font-weight: 600; margin-top: 12px;">No hay productos que coincidan</div>
                    <div class="text-muted" style="margin-top: 8px;">Ajusta la búsqueda o los filtros por columna</div>
                    <div style="margin-top: 20px;">
                        @if($hayFiltros ?? false)
                        <a href="{{ route('productos.index') }}" class="btn btn-secondary">Limpiar filtros</a>
                        @endif
                        <a href="{{ route('productos.create') }}" class="btn btn-primary" style="margin-left: 8px;">➕ Nuevo producto</a>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($productos->isNotEmpty())
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $productos->withQueryString()->links() }}
    </div>
    @endif
</div>
@else
{{-- Catálogo vacío (sin productos en el sistema) --}}
<div class="table-container">
    <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <div class="empty-state-title">No hay productos registrados</div>
        <div class="empty-state-text">Comienza agregando tu primer producto al catálogo</div>
        <div style="margin-top: 20px;">
            <a href="{{ route('productos.create') }}" class="btn btn-primary">➕ Crear Primer Producto</a>
        </div>
    </div>
</div>
@endif
</form>

@push('styles')
<style>
.table-productos-filtros thead th {
    vertical-align: top;
    padding: 10px 8px;
}
.th-sort-title {
    font-weight: 600;
    margin-bottom: 6px;
}
.th-sort-links {
    font-size: 11px;
    font-weight: 500;
}
.th-sort-links a {
    color: var(--color-primary);
    text-decoration: none;
}
.th-sort-links a:hover { text-decoration: underline; }
.th-sort-links a.active {
    font-weight: 700;
    text-decoration: underline;
}
.th-sort-sep { color: var(--color-gray-400); margin: 0 2px; }
.tr-filtros-columna th {
    background: var(--color-gray-50, #f8f9fa);
    padding-top: 8px;
    padding-bottom: 10px;
}
.input-col-filter {
    font-size: 12px !important;
    padding: 6px 8px !important;
    min-width: 0;
    width: 100%;
    max-width: 140px;
}
.tr-filtros-columna .td-right .input-col-filter { max-width: 88px; margin-left: auto; display: block; }
.tr-filtros-columna .td-center .input-col-filter { max-width: 120px; margin: 0 auto; }
.btn-col-filter {
    font-size: 11px;
    padding: 6px 10px;
    margin-top: 4px;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-weight: 600;
}
.btn-col-filter:hover { opacity: 0.92; }
</style>
@endpush

@endsection
