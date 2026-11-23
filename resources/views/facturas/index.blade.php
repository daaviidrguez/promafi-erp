@extends('layouts.app')

@section('title', 'Facturas')
@section('page-title', '🧾 Facturas')
@section('page-subtitle', 'Gestión de comprobantes fiscales CFDI 4.0')
@section('page-actions')
    @can('facturas.crear')
    <a href="{{ route('facturas.create') }}" class="btn btn-primary">➕ Nueva Factura</a>
    @endcan
@endsection

@php
$breadcrumbs = [
    ['title' => 'Facturas']
];
@endphp

@section('content')

@if(session('success'))
    <div class="alert alert-success"><span>✓</span> {{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger"><span>✗</span> {{ session('error') }}</div>
@endif
@if(session('info'))
    <div class="alert alert-info"><span>ℹ</span> {{ session('info') }}</div>
@endif

{{-- Filtros: búsqueda + estado + fechas en una sola línea --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🔍 Filtros</div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('facturas.index') }}" class="filtros-bar filtros-bar--inline">
            <div class="filtros-bar-left">
                <input type="text" name="search" value="{{ $search ?? '' }}"
                       placeholder="Buscar por folio, UUID, cliente..."
                       class="form-control filtros-bar__search"
                       aria-label="Buscar">

                <select name="estado" class="form-control filtros-bar__estado" aria-label="Estado">
                    <option value="">Todos los estados</option>
                    <option value="borrador"  {{ ($estado ?? '') == 'borrador'  ? 'selected' : '' }}>📝 Borrador</option>
                    <option value="timbrada"  {{ ($estado ?? '') == 'timbrada'  ? 'selected' : '' }}>Timbrada</option>
                    <option value="cancelada" {{ ($estado ?? '') == 'cancelada' ? 'selected' : '' }}>❌ Cancelada</option>
                </select>

                <label class="filtros-bar__fecha">
                    <span>Desde</span>
                    <input type="date" name="fecha_inicio" value="{{ $fechaInicio ?? '' }}"
                           class="form-control" title="Desde">
                </label>
                <label class="filtros-bar__fecha">
                    <span>Hasta</span>
                    <input type="date" name="fecha_fin" value="{{ $fechaFin ?? '' }}"
                           class="form-control" title="Hasta">
                </label>

                <button type="submit" class="btn btn-primary filtros-bar__submit">
                    🔍 Buscar
                </button>
                @if(request()->boolean('todas') || request()->hasAny(['search','estado','fecha_inicio','fecha_fin']))
                    <a href="{{ route('facturas.index') }}" class="btn btn-light" title="Últimos 90 días">↺</a>
                @endif
                @if(!request()->boolean('todas'))
                    <a href="{{ route('facturas.index', array_merge(request()->except('fecha_inicio','fecha_fin'), ['todas' => 1])) }}"
                       class="btn btn-light" title="Sin límite de fechas">Todas</a>
                @endif
            </div>
        </form>
        @if(!empty($rangoDefault))
            <div class="text-muted" style="font-size: 12px; margin-top: 8px;">
                Mostrando últimos 90 días. Usa «Todas» para ver el historial completo.
            </div>
        @endif
    </div>
</div>

{{-- Tabla --}}
<div class="table-container">
    @if($facturas->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th class="td-center">Método</th>
                <th class="td-right">Total</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($facturas as $factura)
            <tr>
                <td>
                    <div class="text-mono fw-600" style="font-size: 14px;">{{ $factura->folio_completo }}</div>
                </td>
                <td>
                    <div class="fw-600 text-primary">{{ $factura->cliente->nombre ?? $factura->nombre_receptor }}</div>
                    <div class="text-muted" style="font-size: 12px;">{{ $factura->cliente->rfc ?? $factura->rfc_receptor }}</div>
                </td>
                <td>
                    <div>{{ $factura->fecha_emision->format('d/m/Y') }}</div>
                    @if($factura->fecha_timbrado)
                        <div style="font-size: 11px; color: var(--color-success);">{{ $factura->fecha_timbrado->format('H:i') }}</div>
                    @endif
                </td>
                <td class="td-center">
                    @if($factura->metodo_pago === 'PUE')
                        <span class="badge badge-success">💵 PUE</span>
                    @else
                        <span class="badge badge-warning">💳 PPD</span>
                    @endif
                </td>
                <td class="td-right text-mono fw-600" style="font-size: 15px;">
                    ${{ number_format($factura->total, 2, '.', ',') }}
                </td>
                <td class="td-center" style="min-width: 200px;">
                    @if($factura->estado === 'timbrada')
                        <span class="badge badge-success">✓ Timbrada</span>
                    @elseif($factura->estado === 'borrador')
                        <span class="badge badge-warning">📝 Borrador</span>
                    @else
                        @php
                            $tituloCancelFechas = '';
                            if ($factura->cancelacion_administrativa ?? false) {
                                if ($factura->fecha_cancelacion) {
                                    $tituloCancelFechas = 'Administrativa (ERP): '.$factura->fecha_cancelacion->format('d/m/Y H:i');
                                }
                                if ($factura->fecha_cancelacion_pac ?? null) {
                                    $tituloCancelFechas .= ($tituloCancelFechas ? ' | ' : '').'PAC/SAT: '.$factura->fecha_cancelacion_pac->format('d/m/Y H:i');
                                }
                            } elseif ($factura->fecha_cancelacion) {
                                $tituloCancelFechas = $factura->fecha_cancelacion->format('d/m/Y H:i');
                            }
                            $subtituloCancel = $factura->etiquetaSolicitudParaListado();
                        @endphp
                        <span class="badge badge-danger" @if($tituloCancelFechas !== '') title="{{ $tituloCancelFechas }}" @endif>
                            {{ $factura->estado_etiqueta }}
                        </span>
                        @if($subtituloCancel)
                            <div class="text-mono" style="font-size: 11px; margin-top: 4px; color: var(--color-gray-600);">
                                {{ $subtituloCancel }}
                            </div>
                        @endif
                    @endif
                </td>
                <td class="td-actions">
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        <a href="{{ route('facturas.show', $factura->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                        @if($factura->esBorrador() && auth()->user()->can('facturas.crear'))
                            <a href="{{ route('facturas.edit', $factura->id) }}"
                               class="btn btn-primary btn-sm btn-icon" title="Editar">✏️</a>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $facturas->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🧾</div>
        <div class="empty-state-title">No hay facturas en este rango</div>
        <div class="empty-state-text">
            @if(!request()->boolean('todas'))
                Prueba ampliar fechas o
                <a href="{{ route('facturas.index', ['todas' => 1]) }}" style="color: var(--color-primary);">ver todas</a>.
            @else
                Comienza creando tu primera factura.
            @endif
        </div>
        <div style="margin-top: 20px;">
            @can('facturas.crear')
            <a href="{{ route('facturas.create') }}" class="btn btn-primary">➕ Crear Primera Factura</a>
            @endcan
        </div>
    </div>
    @endif
</div>

@endsection
