@extends('layouts.app')

@section('title', 'Lista de Precios: ' . $listaPrecio->nombre)
@section('page-title', '💰 ' . $listaPrecio->nombre)
@section('page-subtitle', $listaPrecio->descripcion ?: 'Lista de precios')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Listas de Precios', 'url' => route('listas-precios.index')],
    ['title' => $listaPrecio->nombre]
];
@endphp

@section('content')

@if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Configuración</div>
                @if($listaPrecio->activo)
                    <span class="badge badge-success">Activa</span>
                @else
                    <span class="badge badge-gray">Inactiva</span>
                @endif
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Nombre</div>
                        <div class="info-value">{{ $listaPrecio->nombre }}</div>
                    </div>
                    @if($listaPrecio->descripcion)
                    <div class="info-row" style="grid-column:1/-1;">
                        <div class="info-label">Descripción</div>
                        <div class="info-value-sm">{{ $listaPrecio->descripcion }}</div>
                    </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Cliente asignado</div>
                        <div class="info-value">{{ $listaPrecio->cliente ? $listaPrecio->cliente->nombre : '— Sin asignar (lista general) —' }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Productos ({{ $listaPrecio->detalles->count() }})</div>
            </div>
            <div class="table-container" style="border:none;box-shadow:none;margin:0;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:28%;">Producto</th>
                            <th class="td-right" style="width:10%;">Costo</th>
                            <th class="td-center" style="width:14%;">Tipo utilidad</th>
                            <th class="td-right" style="width:10%;">Valor</th>
                            <th class="td-right" style="width:10%;">Precio</th>
                            <th class="td-center" style="width:8%;">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($listaPrecio->detalles as $d)
                        @php $p = $d->producto; @endphp
                        @if($p)
                        <tr>
                            <td>
                                <div class="fw-600">{{ $p->nombre }}</div>
                                <span class="text-muted" style="font-size:12px;">{{ $p->codigo }}</span>
                            </td>
                            <td class="td-right text-mono">${{ number_format($p->costo_promedio_mostrar ?? $p->costo ?? 0, 2, '.', ',') }}</td>
                            <td class="td-center">{{ $d->tipo_utilidad === 'factorizado' ? 'Factorizado (Markup)' : 'Utilidad Real (Margen)' }}</td>
                            <td class="td-right text-mono">{{ number_format(max(1, min(99, (float)$d->valor_utilidad)), 0) }}%</td>
                            <td class="td-right text-mono fw-600" style="color:var(--color-primary);">${{ number_format($d->precio_resultante, 2, '.', ',') }}</td>
                            <td class="td-center">
                                @if($d->activo ?? true)
                                    <span class="badge badge-success">Activo</span>
                                @else
                                    <span class="badge badge-gray">Desactivado</span>
                                @endif
                            </td>
                        </tr>
                        @endif
                        @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted" style="padding:32px;">Sin productos en esta lista</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div>
        <div class="card" style="border:2px solid var(--color-primary); box-shadow:0 2px 12px rgba(11, 60, 93, 0.15);">
            <div class="card-header" style="background:var(--color-primary); color:white; font-weight:bold;">
                <div class="card-title" style="color:white;">👤 Para Cliente</div>
            </div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:8px;">
                <a href="{{ route('listas-precios.ver-pdf-cliente', $listaPrecio) }}" target="_blank" class="btn btn-primary">📄 Ver PDF</a>
                <span class="text-muted" style="font-size:12px;">Solo producto, clave SAT y precio sin IVA</span>
                <span class="text-muted" style="font-size:12px;">Precio = Precio de venta </span>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
                <a href="{{ route('listas-precios.ver-pdf', $listaPrecio) }}" target="_blank" class="btn btn-primary">📄 Ver PDF</a>
                <a href="{{ route('listas-precios.descargar-pdf', $listaPrecio) }}" class="btn btn-light">⬇️ Descargar PDF</a>
                @can('listas_precios.editar')
                <a href="{{ route('listas-precios.edit', $listaPrecio) }}" class="btn btn-primary">✏️ Editar</a>
                @endcan
                @can('listas_precios.editar')
                <a href="{{ route('listas-precios.editar-masivamente', $listaPrecio) }}" target="_blank" class="btn btn-primary">📊 Editar masivamente</a>
                @endcan
                <a href="{{ route('listas-precios.index') }}" class="btn btn-light">← Volver al listado</a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">ℹ️ Ayuda</div></div>
            <div class="card-body" style="font-size:13px;">
                <p><strong>Factorizado (Markup):</strong> costo × (1 + %). Ej: 30% → costo × 1.30</p>
                <p><strong>Utilidad Real (Margen):</strong> costo ÷ (1 − %). Ej: 30% → costo ÷ 0.70</p>
                <p class="mt-3">Los precios se calculan con el costo promedio del producto.</p>
            </div>
        </div>
    </div>
</div>

@endsection
