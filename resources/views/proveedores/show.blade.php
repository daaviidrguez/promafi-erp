@extends('layouts.app')
@section('title', $proveedor->nombre)
@section('page-title', $proveedor->nombre)
@section('page-subtitle', 'Proveedor')

@php $breadcrumbs = [['title' => 'Proveedores', 'url' => route('proveedores.index')], ['title' => $proveedor->nombre]]; @endphp

@section('content')
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Datos del Proveedor</div>
                <a href="{{ route('proveedores.edit', $proveedor->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Nombre</div><div class="info-value">{{ $proveedor->nombre }}</div></div>
                    <div class="info-row"><div class="info-label">Código</div><div class="info-value text-mono">{{ $proveedor->codigo ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $proveedor->rfc ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Días crédito</div><div class="info-value">{{ $proveedor->dias_credito ? $proveedor->dias_credito . ' días' : 'Contado' }}</div></div>
                    @if($proveedor->email)<div class="info-row"><div class="info-label">Email</div><div class="info-value">{{ $proveedor->email }}</div></div>@endif
                    @if($proveedor->telefono)<div class="info-row"><div class="info-label">Teléfono</div><div class="info-value">{{ $proveedor->telefono }}</div></div>@endif
                    <div class="info-row"><div class="info-label">Estado</div><div>@if($proveedor->activo)<span class="badge badge-success">Activo</span>@else<span class="badge badge-danger">Inactivo</span>@endif</div></div>
                </div>
            </div>
        </div>

        {{-- Productos asociados --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Productos asociados</div>
            </div>
            <div class="card-body">
                @if($proveedor->productoProveedores->count())
                    <div class="table-container" style="border:none; box-shadow:none;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Producto</th>
                                    <th>Código proveedor</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($proveedor->productoProveedores as $pp)
                                    @php $p = $pp->producto; @endphp
                                    <tr>
                                        <td>
                                            @if($p)
                                                <a href="{{ route('productos.show', $p->id) }}" class="fw-600" style="color: var(--color-primary); text-decoration: none;">
                                                    {{ $p->nombre }}
                                                </a>
                                                <div class="text-muted" style="font-size:12px; margin-top:2px;">
                                                    {{ $p->codigo }}
                                                </div>
                                            @else
                                                <span class="text-muted">Producto eliminado</span>
                                            @endif
                                        </td>
                                        <td class="text-mono fw-600">{{ $pp->codigo }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <div class="empty-state-title">Sin productos asociados</div>
                        <div class="empty-state-text">Cuando agregues códigos de proveedor al producto, aparecerán aquí.</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Compras Recientes --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🧾 Compras Recientes</div>
                <a href="{{ route('ordenes-compra.create') }}?proveedor_id={{ $proveedor->id }}" class="btn btn-primary btn-sm">📦 Nueva orden</a>
            </div>
            @if($proveedor->ordenesCompra->count() > 0)
            <div class="table-container" style="border: none; box-shadow: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th class="td-right">Total</th>
                            <th class="td-center">Estado</th>
                            <th class="td-center">Ver</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($proveedor->ordenesCompra as $orden)
                        <tr>
                            <td class="text-mono fw-600">{{ $orden->folio ?? '—' }}</td>
                            <td>{{ $orden->fecha ? $orden->fecha->format('d/m/Y') : '—' }}</td>
                            <td class="td-right text-mono">${{ number_format((float) $orden->total, 2, '.', ',') }}</td>
                            <td class="td-center">
                                @if($orden->estado === 'borrador')
                                    <span class="badge badge-warning">📝 Borrador</span>
                                @elseif($orden->estado === 'aceptada')
                                    <span class="badge badge-info">✓ Aceptada</span>
                                @elseif($orden->estado === 'recibida')
                                    <span class="badge badge-success">📦 Recibida</span>
                                @else
                                    <span class="badge badge-secondary">{{ $orden->estado }}</span>
                                @endif
                            </td>
                            <td class="td-center">
                                <a href="{{ route('ordenes-compra.show', $orden) }}" class="btn btn-light btn-sm">Ver</a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body">
                <div class="empty-state" style="padding: 32px 20px;">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-title">Sin órdenes de compra</div>
                </div>
            </div>
            @endif
        </div>
    </div>
    <div>
        {{-- Estadísticas --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📊 Estadísticas</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Tipo de proveedor</div>
                    <div style="margin-top: 4px;">
                        @if($proveedor->esContado())
                            <span class="badge badge-success">💵 Contado</span>
                        @else
                            <span class="badge badge-warning">💳 Crédito ({{ $proveedor->dias_credito }} días)</span>
                        @endif
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Total órdenes</div>
                    <div class="info-value">{{ $estadisticas['total_ordenes'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Borrador</div>
                    <div class="info-value">{{ $estadisticas['ordenes_borrador'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Aceptadas</div>
                    <div class="info-value">{{ $estadisticas['ordenes_aceptadas'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Recibidas</div>
                    <div class="info-value">{{ $estadisticas['ordenes_recibidas'] }}</div>
                </div>
                <div class="info-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-200);">
                    <div class="info-label">Cuentas por pagar pendientes</div>
                    <div class="info-value">{{ $estadisticas['cuentas_pendientes'] }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Monto pendiente</div>
                    <div class="info-value" style="color: {{ $estadisticas['monto_pendiente'] > 0 ? 'var(--color-warning)' : 'inherit' }};">
                        ${{ number_format($estadisticas['monto_pendiente'], 2, '.', ',') }}
                    </div>
                </div>
                <div class="info-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-200);">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @if($proveedor->activo)
                            <span class="badge badge-success">✓ Activo</span>
                        @else
                            <span class="badge badge-danger">✗ Inactivo</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <a href="{{ route('cotizaciones-compra.create') }}?proveedor_id={{ $proveedor->id }}" class="btn btn-primary w-full">📋 Nueva cotización de compra</a>
                <a href="{{ route('ordenes-compra.create') }}?proveedor_id={{ $proveedor->id }}" class="btn btn-outline w-full">📦 Nueva orden de compra</a>
                <a href="{{ route('proveedores.edit', $proveedor->id) }}" class="btn btn-outline w-full">✏️ Editar</a>
            </div>
        </div>
    </div>
</div>
@endsection
