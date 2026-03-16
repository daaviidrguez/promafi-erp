@extends('layouts.app')

@section('title', 'Complementos de Pago')
@section('page-title', '💳 Complementos de Pago')
@section('page-subtitle', 'Gestión de CFDI de pagos (Tipo P)')

@php
$breadcrumbs = [
    ['title' => 'Complementos de Pago']
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
        <form method="GET" action="{{ route('complementos.index') }}"
              style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; gap: 12px; flex: 1; flex-wrap: wrap;">
                <select name="cliente_id" class="form-control" style="min-width: 200px;">
                    <option value="">Todos los clientes</option>
                    @foreach($clientes as $cliente)
                        <option value="{{ $cliente->id }}" {{ ($cliente_id ?? '') == $cliente->id ? 'selected' : '' }}>
                            {{ $cliente->nombre }}
                        </option>
                    @endforeach
                </select>
                <select name="estado" class="form-control" style="min-width: 150px;">
                    <option value="">Todos los estados</option>
                    <option value="borrador"  {{ ($estado ?? '') == 'borrador'  ? 'selected' : '' }}>📝 Borrador</option>
                    <option value="timbrado"  {{ ($estado ?? '') == 'timbrado'  ? 'selected' : '' }}>Timbrado</option>
                    <option value="cancelado" {{ ($estado ?? '') == 'cancelado' ? 'selected' : '' }}>❌ Cancelado</option>
                </select>
                <button type="submit"
                        style="padding: 9px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">
                    🔍 Filtrar
                </button>
            </div>
            <a href="{{ route('complementos.create') }}" class="btn btn-primary">
                ➕ Nuevo Complemento
            </a>
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="table-container">
    @if($complementos->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Cliente</th>
                <th>Fecha</th>
                <th class="td-right">Monto</th>
                <th class="td-center">Facturas</th>
                <th class="td-center" style="min-width: 200px;">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($complementos as $complemento)
            <tr>
                <td>
                    <div class="text-mono fw-600" style="font-size: 14px;">
                        {{ $complemento->folio_completo }}
                    </div>
                    @if($complemento->uuid)
                        <div class="text-mono text-muted" style="font-size: 11px;">
                            {{ substr($complemento->uuid, 0, 18) }}...
                        </div>
                    @endif
                </td>
                <td>
                    <div class="fw-600" style="color: var(--color-primary);">{{ $complemento->cliente->nombre }}</div>
                    <div class="text-muted" style="font-size: 12px;">{{ $complemento->cliente->rfc }}</div>
                </td>
                <td>
                    <div>{{ $complemento->fecha_emision->format('d/m/Y') }}</div>
                    @if($complemento->fecha_timbrado)
                        <div style="font-size: 11px; color: var(--color-success);">
                            ✓ {{ $complemento->fecha_timbrado->format('H:i') }}
                        </div>
                    @endif
                </td>
                <td class="td-right text-mono fw-600" style="font-size: 15px;">
                    ${{ number_format($complemento->monto_total, 2, '.', ',') }}
                </td>
                <td class="td-center">
                    <span class="badge badge-info">
                        {{ $complemento->pagosRecibidos->sum(fn($p) => $p->documentosRelacionados->count()) }} facturas
                    </span>
                </td>
                <td class="td-center">
                    @if($complemento->estado === 'timbrado')
                        <span class="badge badge-success">Timbrado</span>
                    @elseif($complemento->estado === 'borrador')
                        <span class="badge badge-warning">📝 Borrador</span>
                    @else
                        <span class="badge badge-danger" title="{{ $complemento->fecha_cancelacion ? $complemento->fecha_cancelacion->format('d/m/Y H:i') : '' }}">✗ Cancelado</span>
                        @if($complemento->codigo_estatus_cancelacion)
                            <div class="text-mono" style="font-size: 11px; margin-top: 4px; color: var(--color-gray-600);" title="Estatus de la solicitud (paso a paso)">
                                {{ $complemento->codigo_estatus_cancelacion }} — {{ $complemento->estatus_solicitud_label }}
                            </div>
                        @else
                            <div style="font-size: 10px; margin-top: 4px; color: var(--color-gray-500);">Sin respuesta SAT aún</div>
                        @endif
                    @endif
                </td>
                <td class="td-actions">
                    <div style="display: flex; gap: 8px; justify-content: center; flex-wrap: wrap;">
                        @if($complemento->estado === 'cancelado')
                            <form method="POST" action="{{ route('complementos.actualizar-estatus-cancelacion', $complemento->id) }}" style="display: inline;" onsubmit="return confirm('¿Consultar respuesta actual del SAT para este complemento cancelado?');">
                                @csrf
                                <button type="submit" class="btn btn-outline-primary btn-sm btn-icon" title="Actualizar estatus">🔄</button>
                            </form>
                        @endif
                        <a href="{{ route('complementos.show', $complemento->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                        @if($complemento->xml_path)
                            <a href="{{ route('complementos.descargar-xml', $complemento->id) }}"
                               class="btn btn-success btn-sm btn-icon" title="XML">📄</a>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $complementos->withQueryString()->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">💳</div>
        <div class="empty-state-title">No hay complementos de pago</div>
        <div class="empty-state-text">Los complementos se generan al registrar pagos de facturas PPD</div>
        <div style="margin-top: 20px;">
            <a href="{{ route('complementos.create') }}" class="btn btn-primary">➕ Crear Primer Complemento</a>
        </div>
    </div>
    @endif
</div>

@endsection