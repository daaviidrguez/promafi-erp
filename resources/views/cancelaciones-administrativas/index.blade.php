@extends('layouts.app')

@section('title', 'Cancelaciones administrativas')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Cancelaciones administrativas'],
];
@endphp

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">🛑 Cancelaciones administrativas</h1>
        <p class="page-subtitle">Historial de cancelaciones solo en ERP (sin PAC). El CFDI puede seguir vigente ante el SAT.</p>
    </div>
    <a href="{{ route('cancelaciones-administrativas.create') }}" class="btn btn-primary">
        ➕ Nueva cancelación administrativa
    </a>
</div>

<div class="stats-grid">
    <div class="stat-card stat-danger">
        <div class="stat-info-box">
            <div class="stat-label">Cancelaciones del mes</div>
            <div class="stat-value">{{ $estadisticas['cancelaciones_mes'] ?? 0 }}</div>
        </div>
        <div class="stat-icon">🛑</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-info-box">
            <div class="stat-label">Vigentes</div>
            <div class="stat-value">{{ $estadisticas['vigentes'] ?? 0 }}</div>
        </div>
        <div class="stat-icon">📌</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box">
            <div class="stat-label">Reactivadas del mes</div>
            <div class="stat-value">{{ $estadisticas['revertidas_mes'] ?? 0 }}</div>
        </div>
        <div class="stat-icon">↻</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box">
            <div class="stat-label">Monto del mes</div>
            <div class="stat-value" style="font-size:1.25rem;">${{ number_format($estadisticas['monto_mes'] ?? 0, 2, '.', ',') }}</div>
        </div>
        <div class="stat-icon">💵</div>
    </div>
</div>

<div class="card" style="margin-bottom:16px;">
    <div class="card-header">
        <div class="card-title">🔍 Filtros</div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('cancelaciones-administrativas.index') }}" class="filtros-bar" style="flex-wrap:wrap; gap:12px; align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ request('fecha_desde') }}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ request('fecha_hasta') }}">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-control" style="min-width:140px;">
                    <option value="">Todos</option>
                    <option value="vigente" {{ request('estado') === 'vigente' ? 'selected' : '' }}>Vigente</option>
                    <option value="revertida" {{ request('estado') === 'revertida' ? 'selected' : '' }}>Revertida</option>
                </select>
            </div>
            @if(($usuariosFiltro ?? collect())->isNotEmpty())
            <div class="form-group" style="margin:0;">
                <label class="form-label">Usuario</label>
                <select name="user_id" class="form-control" style="min-width:160px;">
                    <option value="">Todos</option>
                    @foreach($usuariosFiltro as $u)
                    <option value="{{ $u->id }}" {{ (string) request('user_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            @endif
            <div class="form-group" style="margin:0; flex:1; min-width:200px;">
                <label class="form-label">Buscar</label>
                <input type="text" name="q" class="form-control" value="{{ request('q') }}" placeholder="Folio, UUID, cliente, RFC, motivo…">
            </div>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            @if(request()->anyFilled(['fecha_desde', 'fecha_hasta', 'q', 'estado', 'user_id']))
            <a href="{{ route('cancelaciones-administrativas.index') }}" class="btn btn-light">Limpiar</a>
            @endif
        </form>
        <p class="form-hint" style="margin:12px 0 0;">
            Listado de <strong>operaciones realizadas</strong>. No sustituye la cancelación ante el SAT.
            La reactivación se gestiona desde el expediente o el detalle de la factura.
        </p>
    </div>
</div>

<div class="table-container">
    @if($cancelaciones->isNotEmpty())
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Folio</th>
                <th>Cliente</th>
                <th class="td-right">Total</th>
                <th>Usuario</th>
                <th>Motivo</th>
                <th class="td-center">Estado</th>
                <th>Efectos</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cancelaciones as $c)
            @php
                $estado = $c->estadoOperacion();
                $detalle = is_array($c->detalle) ? $c->detalle : [];
                $total = (float) ($detalle['total'] ?? $c->factura?->total ?? 0);
                $efectos = [];
                if (!empty($detalle['tenia_cuenta_por_cobrar'])) {
                    $efectos[] = 'CxC';
                }
                if (!empty($detalle['reversa_inventario'])) {
                    $efectos[] = 'Inv.';
                }
            @endphp
            <tr>
                <td>
                    <div class="fw-600">{{ $c->created_at?->format('d/m/Y') }}</div>
                    <div class="text-muted" style="font-size:12px;">{{ $c->created_at?->format('H:i') }}</div>
                </td>
                <td>
                    <div class="text-mono fw-600">{{ $c->factura?->folio_completo ?? ($detalle['folio_completo'] ?? '—') }}</div>
                    @if(!empty($detalle['uuid']) || $c->factura?->uuid)
                    <div class="text-mono text-muted" style="font-size:11px;">{{ \Illuminate\Support\Str::limit($detalle['uuid'] ?? $c->factura->uuid, 18) }}</div>
                    @endif
                </td>
                <td>
                    <div class="fw-600">{{ $c->factura?->cliente?->nombre ?? $c->factura?->nombre_receptor ?? '—' }}</div>
                    <div class="text-muted" style="font-size:12px;">{{ $c->factura?->rfc_receptor }}</div>
                </td>
                <td class="td-right text-mono">${{ number_format($total, 2, '.', ',') }}</td>
                <td>{{ $c->user?->name ?? '—' }}</td>
                <td style="max-width:220px;">
                    <span title="{{ e($c->motivo) }}">{{ \Illuminate\Support\Str::limit($c->motivo, 60) }}</span>
                </td>
                <td class="td-center">
                    @if($estado === 'vigente')
                        <span class="badge badge-danger">Vigente</span>
                    @else
                        <span class="badge badge-secondary">Revertida</span>
                    @endif
                </td>
                <td>
                    @if(count($efectos))
                        <span class="text-muted" style="font-size:12px;">{{ implode(' · ', $efectos) }}</span>
                    @else
                        <span class="text-muted">—</span>
                    @endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('cancelaciones-administrativas.show', $c) }}" class="btn btn-info btn-sm">Ver</a>
                    @if($c->factura)
                    <a href="{{ route('facturas.show', $c->factura_id) }}" class="btn btn-light btn-sm">Factura</a>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">
        {{ $cancelaciones->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🛑</div>
        <div class="empty-state-title">Sin cancelaciones administrativas</div>
        <div class="empty-state-text">
            Aún no hay operaciones registradas
            @if(request()->anyFilled(['fecha_desde', 'fecha_hasta', 'q', 'estado', 'user_id']))
                con los filtros actuales
            @endif.
        </div>
        <a href="{{ route('cancelaciones-administrativas.create') }}" class="btn btn-primary" style="margin-top:12px;">
            ➕ Nueva cancelación administrativa
        </a>
    </div>
    @endif
</div>

@endsection
