@extends('layouts.app')
@section('title', 'Órdenes de Compra')
@section('page-title', '📦 Órdenes de Compra')
@section('page-subtitle', 'Gestiona órdenes de compra y recepción de mercancía')
@section('page-actions')
    <a href="{{ route('ordenes-compra.create') }}" class="btn btn-primary">➕ Nueva Orden</a>
@endsection

@php $breadcrumbs = [['title' => 'Compras'], ['title' => 'Órdenes de Compra']]; @endphp

@section('content')

<div class="stats-grid">
    <div class="stat-card stat-warning">
        <div class="stat-info-box"><div class="stat-label">Borrador</div><div class="stat-value">{{ $estadisticas['borrador'] ?? 0 }}</div></div>
        <div class="stat-icon">📝</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box"><div class="stat-label">Aceptadas</div><div class="stat-value">{{ $estadisticas['aceptada'] ?? 0 }}</div></div>
        <div class="stat-icon">✅</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box"><div class="stat-label">Recibidas</div><div class="stat-value">{{ $estadisticas['recibida'] ?? 0 }}</div></div>
        <div class="stat-icon">📥</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('ordenes-compra.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;"><label class="form-label">Buscar</label><input type="text" name="search" value="{{ request('search') }}" placeholder="Folio, proveedor..." class="form-control" style="min-width:200px;"></div>
            <div class="form-group" style="margin:0;"><label class="form-label">Estado</label><select name="estado" class="form-control"><option value="">Todos</option><option value="borrador" {{ request('estado')=='borrador'?'selected':'' }}>Borrador</option><option value="aceptada" {{ request('estado')=='aceptada'?'selected':'' }}>Aceptada</option><option value="recibida" {{ request('estado')=='recibida'?'selected':'' }}>Recibida</option></select></div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<div class="table-container">
    @if($ordenes->count() > 0)
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
            @foreach($ordenes as $o)
            <tr>
                <td class="text-mono fw-600">{{ $o->folio }}</td>
                <td>{{ $o->proveedor_nombre }}</td>
                <td>{{ $o->fecha->format('d/m/Y') }}</td>
                <td class="td-right text-mono">${{ number_format($o->total, 2) }}</td>
                <td class="td-center">
                    @if($o->estado === 'borrador')<span class="badge badge-warning">Borrador</span>
                    @elseif($o->estado === 'aceptada')<span class="badge badge-info">Aceptada</span>
                    @else<span class="badge badge-success">Recibida</span>@endif
                </td>
                <td class="td-actions"><a href="{{ route('ordenes-compra.show', $o->id) }}" class="btn btn-info btn-sm">Ver</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px;border-top:1px solid var(--color-gray-100);">{{ $ordenes->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <div class="empty-state-title">No hay órdenes de compra</div>
        <div class="empty-state-text">Crea una desde cotización de compra aprobada o nueva orden</div>
        <a href="{{ route('ordenes-compra.create') }}" class="btn btn-primary" style="margin-top:16px;">➕ Nueva Orden</a>
    </div>
    @endif
</div>

@endsection
