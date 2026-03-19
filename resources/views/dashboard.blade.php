@extends('layouts.app')

@section('title', 'Dashboard')
@section('page-title', '📊 Dashboard')
@section('page-subtitle', 'Resumen por departamento')

@php
$breadcrumbs = [
    ['title' => 'Dashboard']
];
@endphp

@section('content')

{{-- KPIs principales --}}
<div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));">
    <div class="stat-card stat-info">
        <div class="stat-info-box">
            <div class="stat-label">Clientes</div>
            <div class="stat-value">{{ $totalClientes ?? 0 }}</div>
            <div class="stat-sub">{{ $clientesActivos ?? 0 }} activos</div>
        </div>
        <div class="stat-icon">👥</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box">
            <div class="stat-label">Facturas del mes</div>
            <div class="stat-value">{{ $facturasDelMes ?? 0 }}</div>
            <div class="stat-sub">${{ number_format($montoFacturado ?? 0, 0, '.', ',') }}</div>
        </div>
        <div class="stat-icon">🧾</div>
    </div>
    <div class="stat-card stat-warning">
        <div class="stat-info-box">
            <div class="stat-label">Por cobrar</div>
            <div class="stat-value" style="font-size: 20px;">${{ number_format($porCobrar ?? 0, 0, '.', ',') }}</div>
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
    <div class="stat-card" style="border-left-color: var(--color-gray-500);">
        <div class="stat-info-box">
            <div class="stat-label">Por pagar</div>
            <div class="stat-value" style="font-size: 20px;">${{ number_format($porPagar ?? 0, 0, '.', ',') }}</div>
            <div class="stat-sub">{{ $cuentasPorPagarPendientes ?? 0 }} cuentas</div>
        </div>
        <div class="stat-icon">📤</div>
    </div>
</div>

{{-- Secciones por departamento --}}
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; margin-top: 24px;">

    {{-- Facturación --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">🧾 Facturación</div>
            <a href="{{ route('facturas.index') }}" class="btn btn-light btn-sm">Ver</a>
        </div>
        <div class="card-body">
            <ul class="dashboard-list">
                <li><span class="dashboard-list-label">Facturas del mes</span><span class="dashboard-list-value">{{ $facturasDelMes ?? 0 }}</span> <span class="text-muted">(${{ number_format($montoFacturado ?? 0, 2, '.', ',') }})</span></li>
                <li><span class="dashboard-list-label">Borradores</span><span class="dashboard-list-value">{{ $facturasBorrador ?? 0 }}</span></li>
                <li><span class="dashboard-list-label">Cotizaciones</span><span class="dashboard-list-value">{{ $cotizacionesTotal ?? 0 }}</span> <span class="text-muted">({{ $cotizacionesPendientes ?? 0 }} pend.)</span></li>
                <li><span class="dashboard-list-label">Complementos pago (mes)</span><span class="dashboard-list-value">{{ $complementosMes ?? 0 }}</span></li>
                <li><span class="dashboard-list-label">Remisiones (mes)</span><span class="dashboard-list-value">{{ $remisionesMes ?? 0 }}</span></li>
                <li><span class="dashboard-list-label">Notas de crédito (mes)</span><span class="dashboard-list-value">{{ $notasCreditoMes ?? 0 }}</span></li>
            </ul>
            <div style="margin-top: 12px; display: flex; flex-wrap: wrap; gap: 8px;">
                <a href="{{ route('facturas.create') }}" class="btn btn-primary btn-sm">➕ Factura</a>
                <a href="{{ route('cotizaciones.index') }}" class="btn btn-outline btn-sm">Cotizaciones</a>
                <a href="{{ route('complementos.index') }}" class="btn btn-outline btn-sm">Complementos</a>
            </div>
        </div>
    </div>

    {{-- Cobranza / Finanzas --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">💰 Cobranza</div>
            <a href="{{ route('cuentas-cobrar.index') }}" class="btn btn-light btn-sm">Ver</a>
        </div>
        <div class="card-body">
            <ul class="dashboard-list">
                <li><span class="dashboard-list-label">Total por cobrar</span><span class="dashboard-list-value" style="color: var(--color-warning);">${{ number_format($porCobrar ?? 0, 2, '.', ',') }}</span></li>
                <li><span class="dashboard-list-label">Cuentas vencidas</span><span class="dashboard-list-value" style="color: {{ ($cuentasVencidas ?? 0) > 0 ? 'var(--color-danger)' : 'inherit' }};">{{ $cuentasVencidas ?? 0 }}</span></li>
            </ul>
            <div style="margin-top: 12px;">
                <a href="{{ route('estado-cuenta.index') }}" class="btn btn-outline btn-sm">📋 Estado de cuenta</a>
                <a href="{{ route('cuentas-cobrar.index') }}" class="btn btn-primary btn-sm">Cuentas por cobrar</a>
            </div>
        </div>
    </div>

    {{-- Compras --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">🏭 Compras</div>
            <a href="{{ route('ordenes-compra.index') }}" class="btn btn-light btn-sm">Ver</a>
        </div>
        <div class="card-body">
            <ul class="dashboard-list">
                <li><span class="dashboard-list-label">Proveedores</span><span class="dashboard-list-value">{{ $totalProveedores ?? 0 }}</span> <span class="text-muted">({{ $proveedoresActivos ?? 0 }} activos)</span></li>
                <li><span class="dashboard-list-label">Órdenes de compra (mes)</span><span class="dashboard-list-value">{{ $ordenesMes ?? 0 }}</span></li>
                <li><span class="dashboard-list-label">OC borrador</span><span class="dashboard-list-value">{{ $ordenesBorrador ?? 0 }}</span></li>
                <li><span class="dashboard-list-label">OC aceptadas / recibidas</span><span class="dashboard-list-value">{{ ($ordenesAceptadas ?? 0) + ($ordenesRecibidas ?? 0) }}</span></li>
                <li><span class="dashboard-list-label">Cotizaciones de compra</span><span class="dashboard-list-value">{{ $cotizacionesCompraTotal ?? 0 }}</span></li>
                <li><span class="dashboard-list-label">Por pagar</span><span class="dashboard-list-value">${{ number_format($porPagar ?? 0, 2, '.', ',') }}</span></li>
            </ul>
            <div style="margin-top: 12px;">
                <a href="{{ route('ordenes-compra.index') }}" class="btn btn-primary btn-sm">Órdenes</a>
                <a href="{{ route('cuentas-por-pagar.index') }}" class="btn btn-outline btn-sm">CxP</a>
            </div>
        </div>
    </div>

    {{-- Inventario / Productos --}}
    <div class="card">
        <div class="card-header">
            <div class="card-title">📦 Inventario</div>
            <a href="{{ route('productos.index') }}" class="btn btn-light btn-sm">Ver</a>
        </div>
        <div class="card-body">
            <ul class="dashboard-list">
                <li><span class="dashboard-list-label">Productos</span><span class="dashboard-list-value">{{ $totalProductos ?? 0 }}</span> <span class="text-muted">({{ $productosActivos ?? 0 }} activos)</span></li>
                <li><span class="dashboard-list-label">Bajo stock</span><span class="dashboard-list-value" style="color: {{ ($productosBajoStock ?? 0) > 0 ? 'var(--color-danger)' : 'inherit' }};">{{ $productosBajoStock ?? 0 }}</span></li>
            </ul>
            <div style="margin-top: 12px;">
                <a href="{{ route('productos.index') }}" class="btn btn-primary btn-sm">Productos</a>
                <a href="{{ route('inventario.index') }}" class="btn btn-outline btn-sm">Movimientos</a>
            </div>
        </div>
    </div>
</div>

{{-- Cuentas vencidas (alerta) --}}
@if(isset($cuentasVencidasList) && count($cuentasVencidasList) > 0)
<div class="card" style="margin-top: 24px; border-left: 4px solid var(--color-danger);">
    <div class="card-header" style="border-bottom-color: rgba(239,68,68,0.15);">
        <div class="card-title" style="color: var(--color-danger);">⚠️ Cuentas vencidas — Requieren atención</div>
        <a href="{{ route('cuentas-cobrar.index') }}?estado=vencidas" class="btn btn-danger btn-sm">Ver todas</a>
    </div>
    <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
        <table>
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Factura</th>
                    <th>Vencimiento</th>
                    <th class="td-center">Días vencido</th>
                    <th class="td-right">Pendiente</th>
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
                        <a href="{{ route('facturas.show', $cuenta->factura->id) }}" class="text-mono fw-600" style="color: var(--color-primary);">
                            {{ $cuenta->factura->folio_completo }}
                        </a>
                    </td>
                    <td>{{ $cuenta->fecha_vencimiento->format('d/m/Y') }}</td>
                    @php
                        // Cálculo en tiempo real por fecha_vencimiento; se detiene cuando está pagada (accessor devuelve null)
                        $diff = $cuenta->dias_contra_vencimiento_realtime;
                        $diasVencido = ($diff !== null && $diff < 0) ? abs($diff) : (int) ($cuenta->dias_vencido ?? 0);
                    @endphp
                    <td class="td-center"><span class="badge badge-danger">{{ $diasVencido }} días</span></td>
                    <td class="td-right text-mono fw-600" style="color: var(--color-danger);">
                        ${{ number_format($cuenta->saldo_pendiente_real, 2, '.', ',') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

{{-- Remisiones entregadas pendientes de facturar --}}
<div class="card" style="margin-top: 24px; border-left: 4px solid var(--color-warning);">
    <div class="card-header">
        <div class="card-title">🚚 Remisiones pendientes de facturar</div>
        <div style="display: flex; gap: 8px; align-items: center;">
            <span class="badge badge-warning" style="font-size: 13px;">{{ $remisionesPendientesFacturar ?? 0 }}</span>
            <a href="{{ route('remisiones.index', ['estado' => 'entregada']) }}" class="btn btn-light btn-sm">Ver remisiones</a>
        </div>
    </div>
    <div class="card-body" style="padding-top: 0;">
        <p class="text-muted" style="font-size: 13px; margin-bottom: 12px;">
            Remisiones en estado <strong>Entregada</strong> sin factura vinculada (mercancía ya salió de inventario).
        </p>
        @if(isset($remisionesPendientesFacturarList) && $remisionesPendientesFacturarList->isNotEmpty())
        <ul class="dashboard-list" style="margin: 0;">
            @foreach($remisionesPendientesFacturarList as $rem)
            <li style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                <span>
                    <span class="fw-600 text-mono">{{ $rem->folio }}</span>
                    <span class="text-muted"> — {{ $rem->cliente_nombre }}</span>
                </span>
                @can('facturas.crear')
                <a href="{{ route('facturas.create', ['remision_id' => $rem->id]) }}" class="btn btn-primary btn-sm">Facturar</a>
                @else
                <a href="{{ route('remisiones.show', $rem->id) }}" class="btn btn-outline btn-sm">Ver</a>
                @endcan
            </li>
            @endforeach
        </ul>
        @else
        <p class="text-muted mb-0" style="font-size: 13px;">No hay remisiones pendientes de facturar.</p>
        @endif
    </div>
</div>

{{-- Facturas recientes --}}
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div class="card-title">🧾 Facturas recientes</div>
        <a href="{{ route('facturas.create') }}" class="btn btn-primary btn-sm">➕ Nueva factura</a>
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
                    <td><span class="text-mono fw-600">{{ $factura->folio_completo }}</span></td>
                    <td>
                        <div class="fw-600" style="color: var(--color-primary);">{{ $factura->cliente->nombre }}</div>
                        <div class="text-muted" style="font-size: 12px;">{{ $factura->cliente->rfc }}</div>
                    </td>
                    <td>{{ $factura->fecha_emision->format('d/m/Y') }}</td>
                    <td class="td-right text-mono fw-600">${{ number_format($factura->total, 2, '.', ',') }}</td>
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
                        <a href="{{ route('facturas.show', $factura->id) }}" class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
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
            <a href="{{ route('facturas.create') }}" class="btn btn-primary">➕ Crear primera factura</a>
        </div>
    </div>
    @endif
</div>

<style>
.dashboard-list { list-style: none; margin: 0; padding: 0; }
.dashboard-list li { display: flex; align-items: center; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid var(--color-gray-100); gap: 12px; }
.dashboard-list li:last-child { border-bottom: none; }
.dashboard-list-label { color: var(--color-gray-600); font-size: 13px; }
.dashboard-list-value { font-weight: 600; font-variant-numeric: tabular-nums; }
</style>

@endsection
