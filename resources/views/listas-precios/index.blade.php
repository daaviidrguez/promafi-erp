@extends('layouts.app')

@section('title', 'Listas de Precios')
@section('page-title', '💰 Listas de Precios')
@section('page-subtitle', 'Precios por cliente con utilidad factorizada o porcentual')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Listas de Precios']
];
@endphp

@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('listas-precios.index') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ request('search') }}" placeholder="Buscar por nombre..." class="form-control" style="max-width:280px;">
            <button type="submit" class="btn btn-primary">Buscar</button>
            @if(request('search'))<a href="{{ route('listas-precios.index') }}" class="btn btn-light">Limpiar</a>@endif
            @can('listas_precios.crear')
            <a href="{{ route('listas-precios.create') }}" class="btn btn-primary" style="margin-left:auto;">➕ Nueva Lista</a>
            @endcan
        </form>
    </div>
</div>

<div class="table-container">
    @if($items->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Cliente</th>
                <th class="td-center">Productos</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td>
                    <div class="fw-600">{{ $item->nombre }}</div>
                    @if($item->descripcion)
                        <div class="text-muted" style="font-size:12px;">{{ Str::limit($item->descripcion, 50) }}</div>
                    @endif
                </td>
                <td>{{ $item->cliente ? $item->cliente->nombre : '—' }}</td>
                <td class="td-center">{{ $item->detalles_count ?? 0 }}</td>
                <td class="td-center">
                    @if($item->activo)
                        <span class="badge badge-success">Activa</span>
                    @else
                        <span class="badge badge-gray">Inactiva</span>
                    @endif
                </td>
                <td class="td-actions">
                    @can('listas_precios.ver')
                    <a href="{{ route('listas-precios.show', $item) }}" class="btn btn-light btn-sm">Ver</a>
                    @endcan
                    @can('listas_precios.editar')
                    <a href="{{ route('listas-precios.edit', $item) }}" class="btn btn-primary btn-sm">Editar</a>
                    <form action="{{ route('listas-precios.toggle-activo', $item) }}" method="POST" style="display:inline;" onsubmit="return confirm('¿{{ $item->activo ? 'Desactivar' : 'Activar' }} esta lista?');">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-light btn-sm">{{ $item->activo ? 'Desactivar' : 'Activar' }}</button>
                    </form>
                    @endcan
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">💰</div>
        <div class="empty-state-title">No hay listas de precios</div>
        <div class="empty-state-text">Crea una lista para asignar precios por cliente con utilidad factorizada o porcentual.</div>
        @can('listas_precios.crear')
        <a href="{{ route('listas-precios.create') }}" class="btn btn-primary mt-3">➕ Crear primera lista</a>
        @endcan
    </div>
    @endif
</div>

<div class="mt-3">{{ $items->links() }}</div>

@endsection
