@extends('layouts.app')
@section('title', 'Remisiones')
@section('page-title', '🚚 Remisiones')
@section('page-subtitle', 'Documentos de entrega de mercancía a clientes')
@section('page-actions')
    <a href="{{ route('remisiones.create') }}" class="btn btn-primary">➕ Nueva Remisión</a>
@endsection

@php $breadcrumbs = [['title' => 'Facturación'], ['title' => 'Remisiones']]; @endphp

@section('content')

<div class="stats-grid">
    <div class="stat-card stat-warning">
        <div class="stat-info-box"><div class="stat-label">Borrador</div><div class="stat-value">{{ $estadisticas['borrador'] ?? 0 }}</div></div>
        <div class="stat-icon">📝</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box"><div class="stat-label">Enviadas</div><div class="stat-value">{{ $estadisticas['enviada'] ?? 0 }}</div></div>
        <div class="stat-icon">📤</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box"><div class="stat-label">Entregadas</div><div class="stat-value">{{ $estadisticas['entregada'] ?? 0 }}</div></div>
        <div class="stat-icon">✅</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('remisiones.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Folio, cliente..." class="form-control" style="min-width:200px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    <option value="borrador" {{ request('estado') === 'borrador' ? 'selected' : '' }}>Borrador</option>
                    <option value="enviada" {{ request('estado') === 'enviada' ? 'selected' : '' }}>Enviada</option>
                    <option value="entregada" {{ request('estado') === 'entregada' ? 'selected' : '' }}>Entregada</option>
                    <option value="cancelada" {{ request('estado') === 'cancelada' ? 'selected' : '' }}>Cancelada</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<div class="table-container">
    @if($remisiones->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($remisiones as $r)
            <tr>
                <td class="text-mono fw-600">{{ $r->folio }}</td>
                <td>{{ $r->cliente_nombre }}</td>
                <td>{{ $r->fecha->format('d/m/Y') }}</td>
                <td class="td-center">
                    @if($r->estado === 'borrador')<span class="badge badge-warning">Borrador</span>
                    @elseif($r->estado === 'enviada')<span class="badge badge-info">Enviada</span>
                    @elseif($r->estado === 'entregada')<span class="badge badge-success">Entregada</span>
                    @else<span class="badge badge-danger">Cancelada</span>@endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('remisiones.show', $r->id) }}" class="btn btn-info btn-sm">Ver</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px;border-top:1px solid var(--color-gray-100);">{{ $remisiones->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🚚</div>
        <div class="empty-state-title">No hay remisiones</div>
        <div class="empty-state-text">Crea una remisión para documentar la entrega de mercancía a un cliente</div>
        <a href="{{ route('remisiones.create') }}" class="btn btn-primary" style="margin-top:16px;">➕ Nueva Remisión</a>
    </div>
    @endif
</div>

@endsection
