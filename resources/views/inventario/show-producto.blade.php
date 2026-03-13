@extends('layouts.app')
@section('title', 'Inventario — ' . $producto->nombre)
@section('page-title', $producto->nombre)
@section('page-subtitle', 'Movimientos de inventario')

@php
$breadcrumbs = [['title' => 'Inventario', 'url' => route('inventario.index')], ['title' => $producto->codigo]];
@endphp

@section('content')
<div style="display:grid;grid-template-columns:1fr 2fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📦 Stock</div></div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Código</div><div class="info-value text-mono">{{ $producto->codigo }}</div></div>
                    <div class="info-row"><div class="info-label">Stock actual</div><div class="info-value text-mono fw-bold">{{ number_format($producto->stock, 2) }} {{ $producto->unidad }}</div></div>
                    <div class="info-row"><div class="info-label">Stock mínimo</div><div class="info-value">{{ number_format($producto->stock_minimo, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Stock máximo</div><div class="info-value">{{ $producto->stock_maximo !== null ? number_format($producto->stock_maximo, 2) : '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Estado</div><div>@if($producto->bajoEnStock())<span class="badge badge-danger">Bajo stock</span>@else<span class="badge badge-success">OK</span>@endif</div></div>
                </div>
                <a href="{{ route('inventario.create-movimiento') }}?producto_id={{ $producto->id }}" class="btn btn-primary w-full" style="margin-top:12px;">➕ Entrada / Salida manual</a>
                <a href="{{ route('productos.show', $producto->id) }}" class="btn btn-outline w-full" style="margin-top:8px;">Ver producto</a>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Historial de movimientos</div></div>
            <div class="card-body" style="padding:0;">
                @if($movimientos->count() > 0)
                <div class="table-container" style="border:none;box-shadow:none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Tipo</th>
                                <th class="td-right">Cantidad</th>
                                <th class="td-right">Stock resultante</th>
                                <th>Referencia</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($movimientos as $m)
                            <tr>
                                <td class="text-mono" style="font-size:13px;">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                                <td>
                                    @if(\App\Models\InventarioMovimiento::esEntrada($m->tipo))
                                        <span class="badge badge-success">{{ $m->etiqueta_tipo }}</span>
                                    @else
                                        <span class="badge badge-warning">{{ $m->etiqueta_tipo }}</span>
                                    @endif
                                </td>
                                <td class="td-right text-mono">{{ \App\Models\InventarioMovimiento::esEntrada($m->tipo) ? '+' : '−' }}{{ number_format($m->cantidad, 2) }}</td>
                                <td class="td-right text-mono">{{ number_format($m->stock_resultante ?? 0, 2) }}</td>
                                <td style="font-size:13px;">
                                    @if($m->factura_id)
                                        <a href="{{ route('facturas.show', $m->factura_id) }}">Factura {{ $m->factura->folio ?? $m->factura_id }}</a>
                                    @elseif($m->remision_id)
                                        <a href="{{ route('remisiones.show', $m->remision_id) }}">Remisión {{ $m->remision->folio ?? $m->remision_id }}</a>
                                    @elseif($m->orden_compra_id)
                                        <a href="{{ route('ordenes-compra.show', $m->orden_compra_id) }}">OC #{{ $m->orden_compra_id }}</a>
                                    @elseif($m->factura_compra_id)
                                        <a href="{{ route('compras.show', $m->factura_compra_id) }}">Compra {{ optional($m->facturaCompra)->folio_completo ?? '#' . $m->factura_compra_id }}</a>
                                    @elseif(in_array($m->tipo, ['entrada_manual', 'salida_manual']))
                                        <a href="{{ route('inventario.movimiento.show', $m->id) }}">{{ $m->folio ?? $m->etiqueta_tipo }}{{ $m->observaciones ? ' — ' . Str::limit($m->observaciones, 30) : '' }}</a>
                                    @else
                                        {{ $m->observaciones ? Str::limit($m->observaciones, 40) : '—' }}
                                    @endif
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">{{ $movimientos->links() }}</div>
                @else
                <div style="padding:32px 20px;text-align:center;color:var(--color-gray-500);">Aún no hay movimientos para este producto.</div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
