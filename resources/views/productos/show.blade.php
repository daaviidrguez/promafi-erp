@extends('layouts.app')

@section('title', 'Producto: ' . $producto->nombre)
@section('page-title', $producto->nombre)
@section('page-subtitle', 'Código: ' . $producto->codigo)

@php
$breadcrumbs = [
    ['title' => 'Productos', 'url' => route('productos.index')],
    ['title' => $producto->codigo]
];
@endphp

@section('content')

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

    {{-- Columna izquierda --}}
    <div>

        {{-- Información General --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información del Producto</div>
                <a href="{{ route('productos.edit', $producto->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Código</div>
                        <div class="info-value text-mono">{{ $producto->codigo }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Categoría</div>
                        <div style="margin-top: 4px;">
                            @if($producto->categoria)
                                <span class="badge" style="background: {{ $producto->categoria->color }}20; color: {{ $producto->categoria->color }};">
                                    {{ $producto->categoria->icono }} {{ $producto->categoria->nombre }}
                                </span>
                            @else
                                <span class="text-muted">Sin categoría</span>
                            @endif
                        </div>
                    </div>
                    @if($producto->descripcion)
                    <div class="info-row" style="grid-column: 1 / -1;">
                        <div class="info-label">Descripción</div>
                        <div class="info-value-sm" style="line-height: 1.7;">{{ $producto->descripcion }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Datos Fiscales --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏛️ Datos Fiscales (SAT)</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Clave Prod./Serv.</div>
                        <div class="info-value">{{ $claveSatEtiqueta ?? $producto->clave_sat }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Clave Unidad</div>
                        <div class="info-value text-mono">{{ $producto->clave_unidad_sat }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Unidad de Medida</div>
                        <div class="info-value">{{ $producto->unidad }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Objeto del impuesto</div>
                        <div class="info-value text-mono">
                            @php
                                $objetos = ['01' => '01 No objeto', '02' => '02 Sí objeto', '03' => '03 Sí objeto y no obligado al desglose'];
                            @endphp
                            {{ $objetos[$producto->objeto_impuesto ?? '02'] ?? $producto->objeto_impuesto }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipo de impuesto</div>
                        <div class="info-value">IVA</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipo factor</div>
                        <div class="info-value">{{ $producto->tipo_factor ?? 'Tasa' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tasa</div>
                        <div class="info-value text-mono">{{ number_format((float)($producto->tasa_iva ?? 0), 6, '.', '') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">IVA (resumen)</div>
                        <div style="margin-top: 4px;">
                            @if(($producto->tipo_factor ?? 'Tasa') === 'Exento' || !$producto->aplica_iva)
                                <span class="badge badge-warning">Exento</span>
                            @else
                                <span class="badge badge-success">✓ IVA {{ number_format(($producto->tasa_iva ?? 0) * 100, 0) }}%</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Precios e Inventario --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">💰 Precios e Inventario</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Costo</div>
                        <div class="info-value text-mono">
                            ${{ number_format($producto->costo, 2, '.', ',') }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Precio de Venta</div>
                        <div class="info-value text-mono" style="color: var(--color-primary); font-size: 18px;">
                            ${{ number_format($producto->precio_venta, 2, '.', ',') }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Precio con IVA</div>
                        <div class="info-value text-mono" style="color: var(--color-success); font-size: 18px;">
                            ${{ number_format($producto->precio_con_iva, 2, '.', ',') }}
                        </div>
                    </div>
                    @if($producto->costo > 0)
                    <div class="info-row">
                        <div class="info-label">Margen de Ganancia</div>
                        <div class="info-value" style="color: {{ $producto->margen > 30 ? 'var(--color-success)' : 'var(--color-warning)' }}; font-size: 18px;">
                            {{ number_format($producto->margen, 1) }}%
                        </div>
                    </div>
                    @endif
                </div>

                @if($producto->controla_inventario)
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-100);">
                        <div class="info-grid-2">
                            <div class="info-row">
                                <div class="info-label">Stock Actual</div>
                                <div class="info-value" style="font-size: 28px; color: {{ $producto->bajoEnStock() ? 'var(--color-danger)' : 'var(--color-success)' }};">
                                    {{ number_format($producto->stock, 0) }}
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Stock Mínimo</div>
                                <div class="info-value" style="font-size: 22px;">
                                    {{ number_format($producto->stock_minimo, 0) }}
                                </div>
                            </div>
                            @if($producto->stock_maximo !== null)
                            <div class="info-row">
                                <div class="info-label">Stock Máximo</div>
                                <div class="info-value">{{ number_format($producto->stock_maximo, 0) }}</div>
                            </div>
                            @endif
                        </div>
                        @if($producto->bajoEnStock())
                        <div class="alert alert-danger" style="margin-top: 12px; margin-bottom: 0;">
                            <span>⚠️</span>
                            <div>
                                <div class="fw-600">Stock bajo</div>
                                <div style="font-size: 12px;">Es necesario reabastecer este producto</div>
                            </div>
                        </div>
                        @endif
                        <a href="{{ route('inventario.show-producto', $producto->id) }}" class="btn btn-outline btn-sm" style="margin-top:12px;">📋 Ver movimientos en Inventario</a>
                    </div>
                @else
                    <div class="alert alert-info" style="margin-top: 16px; margin-bottom: 0;">
                        <span>ℹ️</span>
                        <div>No controla inventario — servicio o consumible</div>
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Columna derecha --}}
    <div>

        {{-- Estado y Datos Extra --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📊 Información Adicional</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @if($producto->activo)
                            <span class="badge badge-success">✓ Activo</span>
                        @else
                            <span class="badge badge-danger">✗ Inactivo</span>
                        @endif
                    </div>
                </div>

                @if($producto->codigo_barras)
                <div class="info-row" style="margin-top: 16px;">
                    <div class="info-label">Código de Barras</div>
                    <div class="info-value text-mono">{{ $producto->codigo_barras }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Acciones Rápidas</div>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">
                <a href="{{ route('productos.edit', $producto->id) }}"
                   class="btn btn-primary w-full">✏️ Editar Producto</a>

                <a href="{{ route('facturas.create') }}?producto_id={{ $producto->id }}"
                   class="btn btn-outline w-full">🧾 Crear Factura</a>

                <form method="POST" action="{{ route('productos.destroy', $producto->id) }}"
                      onsubmit="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar Producto</button>
                </form>
            </div>
        </div>

    </div>
</div>

@endsection