@extends('layouts.app')
@section('title', 'Cotizaciones de Compra')
@section('page-title', '📋 Cotizaciones de Compra')
@section('page-subtitle', 'Solicitud de precios a proveedores')
@section('page-actions')
    <a href="{{ route('cotizaciones-compra.create') }}" class="btn btn-primary">➕ Nueva Cotización</a>
@endsection

@php $breadcrumbs = [['title' => 'Compras', 'url' => route('ordenes-compra.index')], ['title' => 'Cotizaciones de Compra']]; @endphp

@section('content')

<div class="stats-grid">
    <div class="stat-card stat-warning">
        <div class="stat-info-box"><div class="stat-label">Borrador</div><div class="stat-value">{{ $estadisticas['borrador'] ?? 0 }}</div></div>
        <div class="stat-icon">📝</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box"><div class="stat-label">Aprobadas</div><div class="stat-value">{{ $estadisticas['aprobada'] ?? 0 }}</div></div>
        <div class="stat-icon">✅</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box"><div class="stat-label">Con OC</div><div class="stat-value">{{ $estadisticas['convertida_oc'] ?? 0 }}</div></div>
        <div class="stat-icon">📦</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('cotizaciones-compra.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Folio, proveedor..." class="form-control" style="min-width:200px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="borrador" {{ request('estado')=='borrador'?'selected':'' }}>Borrador</option>
                    <option value="aprobada" {{ request('estado')=='aprobada'?'selected':'' }}>Aprobada</option>
                    <option value="convertida_oc" {{ request('estado')=='convertida_oc'?'selected':'' }}>Convertida a OC</option>
                    <option value="rechazada" {{ request('estado')=='rechazada'?'selected':'' }}>Rechazada</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<div class="table-container">
    @if($cotizaciones->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th class="td-right">Total</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizaciones as $c)
            <tr>
                <td class="text-mono fw-600">{{ $c->folio }}</td>
                <td>{{ $c->proveedor_nombre }}</td>
                <td>{{ $c->fecha->format('d/m/Y') }}</td>
                <td class="td-right text-mono">${{ number_format($c->total, 2) }}</td>
                <td class="td-center">
                    @if($c->estado === 'borrador')<span class="badge badge-warning">Borrador</span>
                    @elseif($c->estado === 'aprobada')<span class="badge badge-success">Aprobada</span>
                    @elseif($c->estado === 'convertida_oc')<span class="badge badge-info">Con OC</span>
                    @else<span class="badge badge-danger">{{ ucfirst($c->estado) }}</span>@endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('cotizaciones-compra.show', $c->id) }}" class="btn btn-info btn-sm">Ver</a>
                    @if($c->estado === 'borrador')
                    <a href="{{ route('cotizaciones-compra.create') }}?id={{ $c->id }}" class="btn btn-warning btn-sm">Editar</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px;border-top:1px solid var(--color-gray-100);">{{ $cotizaciones->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <div class="empty-state-title">No hay cotizaciones de compra</div>
        <div class="empty-state-text">Crea una para solicitar precios a proveedores</div>
        <a href="{{ route('cotizaciones-compra.create') }}" class="btn btn-primary" style="margin-top:16px;">➕ Nueva Cotización</a>
    </div>
    @endif
</div>

@endsection
