@extends('layouts.app')

@section('title', 'Cuentas por Cobrar')
@section('page-title', '💰 Cuentas por Cobrar')
@section('page-subtitle', 'Gestión de cobranza')

@php
$breadcrumbs = [
    ['title' => 'Cuentas por Cobrar']
];
@endphp

@section('content')

{{-- Stats de totales --}}
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card stat-warning">
        <div class="stat-info-box">
            <div class="stat-label">Total Pendiente</div>
            <div class="stat-value" style="font-size: 22px;">
                ${{ number_format($totales['pendiente'], 0, '.', ',') }}
            </div>
        </div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-info-box">
            <div class="stat-label">Total Vencido</div>
            <div class="stat-value" style="font-size: 22px;">
                ${{ number_format($totales['vencido'], 0, '.', ',') }}
            </div>
        </div>
        <div class="stat-icon">⚠️</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box">
            <div class="stat-label">Total Cobrado</div>
            <div class="stat-value" style="font-size: 22px;">
                ${{ number_format($totales['pagado'], 0, '.', ',') }}
            </div>
        </div>
        <div class="stat-icon">✅</div>
    </div>
</div>

{{-- Filtros --}}
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('cuentas-cobrar.index') }}"
              style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <select name="cliente_id" class="form-control" style="min-width: 200px;">
                <option value="">Todos los clientes</option>
                @foreach($clientes as $cliente)
                    <option value="{{ $cliente->id }}"
                        {{ ($cliente_id ?? '') == $cliente->id ? 'selected' : '' }}>
                        {{ $cliente->nombre }}
                    </option>
                @endforeach
            </select>
            <select name="estado" class="form-control" style="min-width: 180px;">
                <option value="">Todos los estados</option>
                <option value="pendiente" {{ ($estado ?? '') == 'pendiente' ? 'selected' : '' }}>⏳ Pendiente</option>
                <option value="parcial"   {{ ($estado ?? '') == 'parcial'   ? 'selected' : '' }}>📊 Parcial</option>
                <option value="vencidas"  {{ ($estado ?? '') == 'vencidas'  ? 'selected' : '' }}>⚠️ Vencidas</option>
                <option value="pagada"    {{ ($estado ?? '') == 'pagada'    ? 'selected' : '' }}>✅ Pagadas</option>
            </select>
            <button type="submit"
                    style="padding: 9px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">
                🔍 Filtrar
            </button>
            @if(($estado ?? false) || ($cliente_id ?? false))
            <a href="{{ route('cuentas-cobrar.index') }}"
               style="padding: 9px 16px; border: 1.5px solid var(--color-gray-300); border-radius: var(--radius-md); color: var(--color-gray-600); font-weight: 600;">
                ✕ Limpiar
            </a>
            @endif
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="table-container">
    @if($cuentas->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Factura</th>
                <th>Cliente</th>
                <th>Emisión</th>
                <th>Vencimiento</th>
                <th class="td-right">Monto Total</th>
                <th class="td-right">Pendiente</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cuentas as $cuenta)
            <tr style="{{ $cuenta->estaVencida() ? 'background: rgba(239,68,68,0.04);' : '' }}">
                <td>
                    <a href="{{ route('facturas.show', $cuenta->factura->id) }}"
                       class="text-mono fw-600" style="color: var(--color-primary);">
                        {{ $cuenta->factura->folio_completo }}
                    </a>
                </td>
                <td>
                    <div class="fw-600">{{ $cuenta->cliente->nombre }}</div>
                    @if($cuenta->cliente->telefono)
                        <div class="text-muted" style="font-size: 12px;">📱 {{ $cuenta->cliente->telefono }}</div>
                    @endif
                </td>
                <td>{{ $cuenta->fecha_emision->format('d/m/Y') }}</td>
                <td>
                    <div>{{ $cuenta->fecha_vencimiento->format('d/m/Y') }}</div>
                    @if($cuenta->estaVencida())
                        <div style="font-size: 11px; font-weight: 600; color: var(--color-danger);">
                            ⚠️ {{ $cuenta->dias_vencido }} días
                        </div>
                    @endif
                </td>
                <td class="td-right text-mono">
                    ${{ number_format($cuenta->monto_total, 2, '.', ',') }}
                </td>
                <td class="td-right text-mono fw-600"
                    style="color: {{ $cuenta->monto_pendiente > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
                    ${{ number_format($cuenta->monto_pendiente, 2, '.', ',') }}
                </td>
                <td class="td-center">
                    @if($cuenta->estado === 'pagada')
                        <span class="badge badge-success">✅ Pagada</span>
                    @elseif($cuenta->estado === 'vencida')
                        <span class="badge badge-danger">⚠️ Vencida</span>
                    @elseif($cuenta->estado === 'parcial')
                        <span class="badge badge-warning">📊 Parcial</span>
                    @else
                        <span class="badge badge-info">⏳ Pendiente</span>
                    @endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('cuentas-cobrar.show', $cuenta->id) }}"
                       class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $cuentas->withQueryString()->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">💰</div>
        <div class="empty-state-title">No hay cuentas por cobrar</div>
        <div class="empty-state-text">Las facturas a crédito (PPD) aparecerán aquí automáticamente</div>
    </div>
    @endif
</div>

@endsection