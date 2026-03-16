@extends('layouts.app')

@section('title', 'Facturas')
@section('page-title', '🧾 Facturas')
@section('page-subtitle', 'Gestión de comprobantes fiscales CFDI 4.0')

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

{{-- Filtros + Acción --}}
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('facturas.index') }}"
              style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; gap: 12px; flex: 1; flex-wrap: wrap;">
                <input type="text" name="search" value="{{ $search ?? '' }}"
                       placeholder="Buscar por folio, UUID, cliente..."
                       class="form-control" style="flex: 1; min-width: 240px;">

                <select name="estado" class="form-control" style="min-width: 160px;">
                    <option value="">Todos los estados</option>
                    <option value="borrador"  {{ ($estado ?? '') == 'borrador'  ? 'selected' : '' }}>📝 Borrador</option>
                    <option value="timbrada"  {{ ($estado ?? '') == 'timbrada'  ? 'selected' : '' }}>Timbrada</option>
                    <option value="cancelada" {{ ($estado ?? '') == 'cancelada' ? 'selected' : '' }}>❌ Cancelada</option>
                </select>

                <button type="submit"
                        style="padding: 9px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">
                    🔍 Buscar
                </button>
            </div>
            <a href="{{ route('facturas.create') }}" class="btn btn-primary">➕ Nueva Factura</a>
        </form>
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
                    @if($factura->uuid)
                        <div class="text-mono text-muted" style="font-size: 11px;">{{ substr($factura->uuid, 0, 18) }}...</div>
                    @endif
                </td>
                <td>
                    <div class="fw-600 text-primary">{{ $factura->cliente->nombre }}</div>
                    <div class="text-muted" style="font-size: 12px;">{{ $factura->cliente->rfc }}</div>
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
                        <span class="badge badge-danger" title="{{ $factura->fecha_cancelacion ? $factura->fecha_cancelacion->format('d/m/Y H:i') : '' }}">✗ Cancelada</span>
                        @if($factura->codigo_estatus_cancelacion)
                            <div class="text-mono" style="font-size: 11px; margin-top: 4px; color: var(--color-gray-600);" title="Estatus de la solicitud (paso a paso)">
                                {{ $factura->codigo_estatus_cancelacion }} — {{ $factura->estatus_solicitud_label }}
                            </div>
                        @else
                            <div style="font-size: 10px; margin-top: 4px; color: var(--color-gray-500);">Sin respuesta SAT aún</div>
                        @endif
                    @endif
                </td>
                <td class="td-actions">
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        @if($factura->estado === 'cancelada')
                            <form method="POST" action="{{ route('facturas.actualizar-estatus-cancelacion', $factura->id) }}" style="display: inline;" onsubmit="return confirm('¿Consultar respuesta actual del SAT para esta factura cancelada?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm btn-icon" title="Actualizar estatus">🔄</button>
                            </form>
                        @endif
                        <a href="{{ route('facturas.show', $factura->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                        @if($factura->esBorrador() && auth()->user()->can('facturas.crear'))
                            <a href="{{ route('facturas.edit', $factura->id) }}"
                               class="btn btn-primary btn-sm btn-icon" title="Editar">✏️</a>
                        @endif
                        @if($factura->xml_path)
                            <a href="{{ route('facturas.descargar-xml', $factura->id) }}"
                               class="btn btn-success btn-sm btn-icon" title="Descargar XML">📄</a>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $facturas->withQueryString()->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🧾</div>
        <div class="empty-state-title">No hay facturas registradas</div>
        <div class="empty-state-text">Comienza creando tu primera factura</div>
        <div style="margin-top: 20px;">
            <a href="{{ route('facturas.create') }}" class="btn btn-primary">➕ Crear Primera Factura</a>
        </div>
    </div>
    @endif
</div>

@endsection