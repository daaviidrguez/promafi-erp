@extends('layouts.app')
@section('title', 'Logística')
@section('page-title', '📦 Logística')
@section('page-subtitle', 'Seguimiento de envíos — facturas timbradas y remisiones')
@section('page-actions')
    @can('logistica.crear')
        <a href="{{ route('logistica.elegir-origen') }}" class="btn btn-primary">➕ Nuevo envío</a>
    @endcan
@endsection

@php $breadcrumbs = [['title' => 'Logística']]; @endphp

@section('content')

<div class="stats-grid">
    <div class="stat-card stat-warning">
        <div class="stat-info-box"><div class="stat-label">Pendiente</div><div class="stat-value">{{ $stats['pendiente'] ?? 0 }}</div></div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box"><div class="stat-label">Preparado</div><div class="stat-value">{{ $stats['preparado'] ?? 0 }}</div></div>
        <div class="stat-icon">📋</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box"><div class="stat-label">Enviado</div><div class="stat-value">{{ $stats['enviado'] ?? 0 }}</div></div>
        <div class="stat-icon">📤</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box"><div class="stat-label">En ruta</div><div class="stat-value">{{ $stats['en_ruta'] ?? 0 }}</div></div>
        <div class="stat-icon">🛣️</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-info-box"><div class="stat-label">Entrega parcial</div><div class="stat-value">{{ $stats['entrega_parcial'] ?? 0 }}</div></div>
        <div class="stat-icon">📦</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box"><div class="stat-label">Entregado</div><div class="stat-value">{{ $stats['entregado'] ?? 0 }}</div></div>
        <div class="stat-icon">✅</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('logistica.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Folio, cliente..." class="form-control" style="min-width:200px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    @foreach(\App\Models\LogisticaEnvio::ESTADOS as $st)
                        <option value="{{ $st }}" {{ request('estado') === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<div class="table-container">
    @if($envios->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Origen</th>
                <th class="td-center">Estado</th>
                <th class="td-center">Registro</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($envios as $e)
            <tr>
                <td class="text-mono fw-600">{{ $e->folio }}</td>
                <td>{{ $e->cliente->nombre ?? '—' }}</td>
                <td class="text-muted" style="font-size:13px;">
                    @if($e->remision_id)
                        Remisión {{ $e->remision?->folio ?? '#' . $e->remision_id }}
                        @if($e->remision?->factura)
                            - {{ $e->remision->factura->folio_completo }}
                        @endif
                    @elseif($e->factura_id)
                        Factura {{ $e->factura?->folio_completo ?? '#' . $e->factura_id }}
                    @else
                        —
                    @endif
                </td>
                <td class="td-center">
                    @if($e->estado === 'pendiente')<span class="badge badge-warning">{{ $e->estado_etiqueta }}</span>
                    @elseif($e->estado === 'entrega_parcial')<span class="badge badge-warning">{{ $e->estado_etiqueta }}</span>
                    @elseif($e->estado === 'entregado')<span class="badge badge-success">{{ $e->estado_etiqueta }}</span>
                    @elseif($e->estado === 'cancelado')<span class="badge badge-danger">{{ $e->estado_etiqueta }}</span>
                    @else<span class="badge badge-info">{{ $e->estado_etiqueta }}</span>@endif
                </td>
                <td class="td-center text-muted">{{ $e->created_at?->format('d/m/Y H:i') }}</td>
                <td class="td-actions">
                    <a href="{{ route('logistica.show', $e) }}" class="btn btn-light btn-sm">Ver</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $envios->links() }}
    @else
        <p class="text-muted" style="padding:24px;text-align:center;">Sin envíos. @can('logistica.crear')<a href="{{ route('logistica.elegir-origen') }}">Crear el primero</a>@endcan</p>
    @endif
</div>

@endsection
