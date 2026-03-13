@extends('layouts.app')
@section('title', 'Movimiento ' . ($movimiento->folio ?? '#' . $movimiento->id))
@section('page-title', '📋 ' . ($movimiento->folio ?? 'Movimiento #' . $movimiento->id))
@section('page-subtitle', $movimiento->etiqueta_tipo)

@php
$breadcrumbs = [
    ['title' => 'Inventario', 'url' => route('inventario.index')],
    ['title' => 'Movimientos', 'url' => route('inventario.movimientos')],
    ['title' => $movimiento->folio ?? '#' . $movimiento->id],
];
@endphp

@section('content')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📦 Detalle del movimiento</div></div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Producto</div><div class="info-value fw-600">{{ $movimiento->producto->nombre }}</div></div>
                    <div class="info-row"><div class="info-label">Código</div><div class="info-value text-mono">{{ $movimiento->producto->codigo ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Tipo</div><div class="info-value">
                        @if(\App\Models\InventarioMovimiento::esEntrada($movimiento->tipo))
                            <span class="badge badge-success">{{ $movimiento->etiqueta_tipo }}</span>
                        @else
                            <span class="badge badge-warning">{{ $movimiento->etiqueta_tipo }}</span>
                        @endif
                    </div></div>
                    <div class="info-row"><div class="info-label">Cantidad</div><div class="info-value text-mono fw-600">{{ \App\Models\InventarioMovimiento::esEntrada($movimiento->tipo) ? '+' : '−' }}{{ number_format($movimiento->cantidad, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Stock anterior</div><div class="info-value text-mono">{{ number_format($movimiento->stock_anterior ?? 0, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Stock resultante</div><div class="info-value text-mono fw-600">{{ number_format($movimiento->stock_resultante ?? 0, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Fecha</div><div class="info-value">{{ $movimiento->created_at->format('d/m/Y H:i') }}</div></div>
                    <div class="info-row"><div class="info-label">Usuario</div><div class="info-value">{{ $movimiento->usuario->name ?? '—' }}</div></div>
                    @if($movimiento->observaciones)
                    <div class="info-row" style="grid-column:1/-1;"><div class="info-label">Observaciones</div><div class="info-value">{{ $movimiento->observaciones }}</div></div>
                    @endif
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Referencia</div></div>
            <div class="card-body">
                @if($movimiento->factura_id)
                    <a href="{{ route('facturas.show', $movimiento->factura_id) }}" class="btn btn-outline w-full">Factura {{ $movimiento->factura->folio ?? $movimiento->factura_id }}</a>
                @elseif($movimiento->remision_id)
                    <a href="{{ route('remisiones.show', $movimiento->remision_id) }}" class="btn btn-outline w-full">Remisión {{ $movimiento->remision->folio ?? $movimiento->remision_id }}</a>
                @elseif($movimiento->orden_compra_id)
                    <a href="{{ route('ordenes-compra.show', $movimiento->orden_compra_id) }}" class="btn btn-outline w-full">Orden de compra #{{ $movimiento->orden_compra_id }}</a>
                @elseif($movimiento->factura_compra_id)
                    <a href="{{ route('compras.show', $movimiento->factura_compra_id) }}" class="btn btn-outline w-full">Compra {{ optional($movimiento->facturaCompra)->folio_completo ?? '#' . $movimiento->factura_compra_id }}</a>
                @elseif(in_array($movimiento->tipo, ['entrada_manual', 'salida_manual']))
                    <span class="text-muted">{{ $movimiento->folio ?? $movimiento->etiqueta_tipo }}</span>
                @else
                    <span class="text-muted">{{ $movimiento->observaciones ? Str::limit($movimiento->observaciones, 60) : '—' }}</span>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <a href="{{ route('inventario.movimientos') }}" class="btn btn-light w-full">← Volver a movimientos</a>
                <a href="{{ route('inventario.show-producto', $movimiento->producto_id) }}" class="btn btn-outline w-full" style="margin-top:8px;">Ver historial del producto</a>
            </div>
        </div>
    </div>
</div>

@endsection
