@extends('layouts.app')
{{-- resources/views/cotizaciones/index.blade.php --}}

@section('title', 'Cotizaciones')

@php
$breadcrumbs = [
    ['title' => 'Cotizaciones']
];
@endphp

@section('content')

<div class="page-header">
    <div>
        <h1 class="page-title">📋 Cotizaciones</h1>
        <p class="page-subtitle">Gestiona tus presupuestos y propuestas comerciales</p>
    </div>
    <a href="{{ route('cotizaciones.create') }}" class="btn btn-primary">
        ➕ Nueva Cotización
    </a>
</div>

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card stat-warning">
        <div class="stat-info-box">
            <div class="stat-label">Borradores</div>
            <div class="stat-value">{{ $estadisticas['borradores'] ?? 0 }}</div>
        </div>
        <div class="stat-icon">📝</div>
    </div>
    <div class="stat-card stat-info">
        <div class="stat-info-box">
            <div class="stat-label">Enviadas</div>
            <div class="stat-value">{{ $estadisticas['enviadas'] ?? 0 }}</div>
        </div>
        <div class="stat-icon">📧</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box">
            <div class="stat-label">Aceptadas</div>
            <div class="stat-value">{{ $estadisticas['aceptadas'] ?? 0 }}</div>
        </div>
        <div class="stat-icon">✅</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-info-box">
            <div class="stat-label">Por Vencer</div>
            <div class="stat-value">{{ $estadisticas['por_vencer'] ?? 0 }}</div>
        </div>
        <div class="stat-icon">⏰</div>
    </div>
</div>

{{-- Filtros --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🔍 Filtros</div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('cotizaciones.index') }}">
            <div class="form-grid" style="grid-template-columns: 2fr 1fr 1fr 1fr auto;">
                <div class="form-group">
                    <label class="form-label">Buscar</label>
                    <input type="text"
                           name="search"
                           value="{{ request('search') }}"
                           placeholder="Folio, cliente..."
                           class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Estado</label>
                    <select name="estado" class="form-control">
                        <option value="">Todos</option>
                        @foreach(['borrador','enviada','aceptada','facturada','rechazada','vencida'] as $e)
                        <option value="{{ $e }}" {{ request('estado') == $e ? 'selected' : '' }}>
                            {{ ucfirst($e) }}
                        </option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_inicio" value="{{ request('fecha_inicio') }}" class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_fin" value="{{ request('fecha_fin') }}" class="form-control">
                </div>
                <div class="form-group" style="justify-content: flex-end;">
                    <label class="form-label">&nbsp;</label>
                    <div style="display:flex; gap:8px;">
                        <button type="submit" class="btn btn-primary">Buscar</button>
                        @if(request()->hasAny(['search','estado','fecha_inicio','fecha_fin']))
                        <a href="{{ route('cotizaciones.index') }}" class="btn btn-light">✕</a>
                        @endif
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="table-container">
    @if($cotizaciones->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th>Vigencia</th>
                <th class="td-right">Total</th>
                <th class="td-center">Estado</th>
                <th class="td-center">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizaciones as $c)
            <tr>
                <td>
                    <span class="text-mono fw-bold" style="color: var(--color-primary);">
                        {{ $c->folio }}
                    </span>
                </td>
                <td>
                    <div class="fw-600">{{ $c->cliente->nombre ?? $c->cliente_nombre }}</div>
                    <div class="text-muted" style="font-size:12px;">{{ $c->cliente->rfc ?? $c->cliente_rfc }}</div>
                </td>
                <td>{{ $c->fecha->format('d/m/Y') }}</td>
                <td>
                    <span>{{ $c->fecha_vencimiento->format('d/m/Y') }}</span>
                    @if($c->diasHastaVencimiento() !== null && $c->diasHastaVencimiento() <= 7 && $c->diasHastaVencimiento() >= 0)
                        <span class="badge badge-warning" style="font-size:10px; margin-left:4px;">{{ $c->diasHastaVencimiento() }}d</span>
                    @endif
                </td>
                <td class="td-right text-mono fw-bold" style="color: var(--color-secondary);">
                    ${{ number_format($c->total, 2) }}
                </td>
                <td class="td-center">
                    @php
                        $badgeMap = [
                            'borrador'  => 'badge-warning',
                            'enviada'   => 'badge-info',
                            'aceptada'  => 'badge-success',
                            'facturada' => 'badge-primary',
                            'rechazada' => 'badge-danger',
                            'vencida'   => 'badge-gray',
                        ];
                        $iconMap = [
                            'borrador'  => '📝',
                            'enviada'   => '📧',
                            'aceptada'  => '✅',
                            'facturada' => '💰',
                            'rechazada' => '✗',
                            'vencida'   => '⏰',
                        ];
                    @endphp
                    <span class="badge {{ $badgeMap[$c->estado] ?? 'badge-gray' }}">
                        {{ $iconMap[$c->estado] ?? '' }} {{ ucfirst($c->estado) }}
                    </span>
                </td>
                <td class="td-actions">
                    <div style="display:flex; gap:6px; justify-content:center;">
                        <a href="{{ route('cotizaciones.show', $c->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>

                        <a href="{{ route('cotizaciones.descargar-pdf', $c->id) }}"
                           class="btn btn-light btn-sm btn-icon" title="PDF">📄</a>

                        @if($c->puedeEliminarse())
                        <form action="{{ route('cotizaciones.destroy', $c->id) }}" method="POST"
                              onsubmit="return confirm('¿Eliminar esta cotización?')">
                            @csrf @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm btn-icon" title="Eliminar">🗑️</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $cotizaciones->withQueryString()->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <div class="empty-state-title">Sin cotizaciones</div>
        <div class="empty-state-text">
            @if(request()->hasAny(['search','estado','fecha_inicio','fecha_fin']))
                No hay resultados para tu búsqueda.
                <a href="{{ route('cotizaciones.index') }}" style="color: var(--color-primary);">Limpiar filtros</a>
            @else
                Crea tu primera cotización.
            @endif
        </div>
    </div>
    @endif
</div>

@endsection