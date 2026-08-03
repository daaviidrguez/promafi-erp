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
            <div class="form-grid form-grid-filtros responsive-grid">
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
                @if($asesores->isNotEmpty())
                <div class="form-group">
                    <label class="form-label">Asesor</label>
                    <select name="asesor_id" class="form-control">
                        <option value="">Todos</option>
                        @foreach($asesores as $asesor)
                        <option value="{{ $asesor->id }}" {{ (string) request('asesor_id') === (string) $asesor->id ? 'selected' : '' }}>
                            {{ $asesor->name }}
                        </option>
                        @endforeach
                    </select>
                </div>
                @endif
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
                        @if(request()->hasAny(['search','estado','asesor_id','fecha_inicio','fecha_fin']))
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
                <th>Asesor</th>
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
                <td>{{ $c->usuario->name ?? '—' }}</td>
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
                        $estadoBadgeClass = $badgeMap[$c->estado] ?? 'badge-gray';
                        $estadoBadgeLabel = ($iconMap[$c->estado] ?? '') . ' ' . ucfirst($c->estado);
                        $estadoBadgeTitle = null;
                        if ($c->estado === 'facturada' && $c->factura) {
                            if ($c->factura->estado === 'timbrada') {
                                $estadoBadgeClass = 'badge-success';
                                $estadoBadgeTitle = 'Factura timbrada — ver detalle';
                            } elseif ($c->factura->estado === 'borrador') {
                                $estadoBadgeTitle = 'Factura en borrador — ver detalle';
                            } else {
                                $estadoBadgeTitle = 'Ver factura relacionada';
                            }
                        }
                    @endphp
                    @if($c->estado === 'facturada' && $c->factura)
                        @can('facturas.ver')
                        <a href="{{ route('facturas.show', $c->factura->id) }}"
                           class="badge {{ $estadoBadgeClass }}"
                           style="text-decoration: none;"
                           @if($estadoBadgeTitle) title="{{ $estadoBadgeTitle }}" @endif>
                            {{ trim($estadoBadgeLabel) }}
                        </a>
                        @else
                        <span class="badge {{ $estadoBadgeClass }}"
                              @if($estadoBadgeTitle) title="{{ $estadoBadgeTitle }}" @endif>
                            {{ trim($estadoBadgeLabel) }}
                        </span>
                        @endcan
                    @else
                    <span class="badge {{ $estadoBadgeClass }}">
                        {{ trim($estadoBadgeLabel) }}
                    </span>
                    @endif
                </td>
                <td class="td-actions">
                    <div style="display:flex; gap:6px; justify-content:center;">
                        <a href="{{ route('cotizaciones.show', $c->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>

                        <a href="{{ route('cotizaciones.descargar-pdf', $c->id) }}"
                           class="btn btn-light btn-sm btn-icon" title="PDF">📄</a>

                        @if($c->puedeEliminarse())
                        <button type="button"
                                class="btn btn-danger btn-sm btn-icon"
                                title="Eliminar"
                                onclick="abrirModalEliminarCotizacion(@json(route('cotizaciones.destroy', $c->id)), @json($c->folio))">
                            🗑️
                        </button>
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
            @if(request()->hasAny(['search','estado','asesor_id','fecha_inicio','fecha_fin']))
                No hay resultados para tu búsqueda.
                <a href="{{ route('cotizaciones.index') }}" style="color: var(--color-primary);">Limpiar filtros</a>
            @else
                Crea tu primera cotización.
            @endif
        </div>
    </div>
    @endif
</div>

{{-- Modal eliminar cotización (permanente) --}}
<div id="modalEliminarCotizacion" class="modal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--color-danger);">🗑️ Eliminar cotización</div>
            <button type="button" class="modal-close" onclick="cerrarModalEliminarCotizacion()" aria-label="Cerrar">✕</button>
        </div>
        <form id="formEliminarCotizacion" method="POST" action="">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 0;">
                    ¿Estás seguro de eliminar la cotización <strong id="modalEliminarCotizacionFolio"></strong>?
                    Esta acción es irreversible y liberará el folio de forma permanente.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="cerrarModalEliminarCotizacion()">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-danger">Eliminar definitivamente</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
function abrirModalEliminarCotizacion(url, folio) {
    const form = document.getElementById('formEliminarCotizacion');
    const folioEl = document.getElementById('modalEliminarCotizacionFolio');
    const modal = document.getElementById('modalEliminarCotizacion');
    if (!form || !folioEl || !modal) return;

    form.action = url;
    folioEl.textContent = folio;
    modal.classList.add('show');
}

function cerrarModalEliminarCotizacion() {
    const modal = document.getElementById('modalEliminarCotizacion');
    if (modal) modal.classList.remove('show');
}
</script>
@endpush

@endsection