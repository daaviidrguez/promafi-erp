@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', '📊 Dashboard')
@section('page-subtitle', 'Vista general del sistema')

@php
$breadcrumbs = [
    ['title' => 'Dashboard']
];
@endphp

@section('content')

{{-- Stats --}}
<div class="stats-grid">
    <div class="stat-card stat-info">
        <div class="stat-info-box">
            <div class="stat-label">Total Clientes</div>
            <div class="stat-value">{{ $totalClientes ?? 0 }}</div>
            <div class="stat-sub">{{ $clientesActivos ?? 0 }} activos</div>
        </div>
        <div class="stat-icon">👥</div>
    </div>

    <div class="stat-card stat-success">
        <div class="stat-info-box">
            <div class="stat-label">Facturas del Mes</div>
            <div class="stat-value">{{ $facturasDelMes ?? 0 }}</div>
            <div class="stat-sub">${{ number_format($montoFacturado ?? 0, 2, '.', ',') }}</div>
        </div>
        <div class="stat-icon">🧾</div>
    </div>

    <div class="stat-card stat-warning">
        <div class="stat-info-box">
            <div class="stat-label">Por Cobrar</div>
            <div class="stat-value" style="font-size: 22px;">
                ${{ number_format($porCobrar ?? 0, 0, '.', ',') }}
            </div>
            <div class="stat-sub">{{ $cuentasVencidas ?? 0 }} vencidas</div>
        </div>
        <div class="stat-icon">💰</div>
    </div>

    <div class="stat-card stat-danger">
        <div class="stat-info-box">
            <div class="stat-label">Productos</div>
            <div class="stat-value">{{ $totalProductos ?? 0 }}</div>
            <div class="stat-sub">{{ $productosBajoStock ?? 0 }} bajo stock</div>
        </div>
        <div class="stat-icon">📦</div>
    </div>
</div>

{{-- Cuentas Vencidas (alerta) --}}
@if(isset($cuentasVencidasList) && count($cuentasVencidasList) > 0)
<div class="card" style="border-left: 4px solid var(--color-danger);">
    <div class="card-header" style="border-bottom-color: rgba(239,68,68,0.15);">
        <div class="card-title" style="color: var(--color-danger);">⚠️ Cuentas Vencidas — Requieren Atención</div>
        <a href="{{ route('cuentas-cobrar.index') }}?estado=vencidas" class="btn btn-danger btn-sm">
            Ver todas
        </a>
    </div>
    <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Factura</th>
                    <th>Vencimiento</th>
                    <th class="td-center">Días Vencido</th>
                    <th class="td-right">Monto Pendiente</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cuentasVencidasList as $cuenta)
                <tr>
                    <td>
                        <div class="fw-600">{{ $cuenta->cliente->nombre }}</div>
                        @if($cuenta->cliente->telefono)
                            <div class="text-muted" style="font-size: 12px;">📱 {{ $cuenta->cliente->telefono }}</div>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('facturas.show', $cuenta->factura->id) }}"
                           class="text-mono fw-600" style="color: var(--color-primary);">
                            {{ $cuenta->factura->folio_completo }}
                        </a>
                    </td>
                    <td>{{ $cuenta->fecha_vencimiento->format('d/m/Y') }}</td>
                    <td class="td-center">
                        <span class="badge badge-danger">{{ $cuenta->dias_vencido }} días</span>
                    </td>
                    <td class="td-right text-mono fw-600" style="color: var(--color-danger);">
                        ${{ number_format($cuenta->monto_pendiente, 2, '.', ',') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Facturas Recientes --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">🧾 Facturas Recientes</div>
        <a href="{{ route('facturas.create') }}" class="btn btn-primary btn-sm">➕ Nueva Factura</a>
    </div>

    @if(isset($facturasRecientes) && count($facturasRecientes) > 0)
    <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
        <table>
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th class="td-right">Total</th>
                    <th class="td-center">Estado</th>
                    <th class="td-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($facturasRecientes as $factura)
                <tr>
                    <td>
                        <span class="text-mono fw-600">{{ $factura->folio_completo }}</span>
                    </td>
                    <td>
                        <div class="fw-600" style="color: var(--color-primary);">{{ $factura->cliente->nombre }}</div>
                        <div class="text-muted" style="font-size: 12px;">{{ $factura->cliente->rfc }}</div>
                    </td>
                    <td>{{ $factura->fecha_emision->format('d/m/Y') }}</td>
                    <td class="td-right text-mono fw-600">
                        ${{ number_format($factura->total, 2, '.', ',') }}
                    </td>
                    <td class="td-center">
                        @if($factura->estado === 'timbrada')
                            <span class="badge badge-success">✓ Timbrada</span>
                        @elseif($factura->estado === 'borrador')
                            <span class="badge badge-warning">📝 Borrador</span>
                        @else
                            <span class="badge badge-danger">✗ Cancelada</span>
                        @endif
                    </td>
                    <td class="td-actions">
                        <a href="{{ route('facturas.show', $factura->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @else
    <div class="empty-state" style="padding: 40px 20px;">
        <div class="empty-state-icon">📄</div>
        <div class="empty-state-title">No hay facturas recientes</div>
        <div style="margin-top: 16px;">
            <a href="{{ route('facturas.create') }}" class="btn btn-primary">➕ Crear Primera Factura</a>
        </div>
    </div>
    @endif
</div>

@endsection