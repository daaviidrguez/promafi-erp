@extends('layouts.app')
@section('title', 'Movimientos de inventario')
@section('page-title', '📋 Movimientos de inventario')
@section('page-subtitle', 'Trazabilidad de entradas y salidas')
@section('page-actions')
    <a href="{{ route('inventario.create-movimiento') }}" class="btn btn-primary">➕ Entrada / Salida manual</a>
    <a href="{{ route('inventario.index') }}" class="btn btn-outline">📦 Ver stock</a>
@endsection

@php
$breadcrumbs = [['title' => 'Inventario', 'url' => route('inventario.index')], ['title' => 'Movimientos']];
@endphp

@section('content')

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('inventario.movimientos') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <select name="producto_id" class="form-control" style="min-width:220px;">
                <option value="">Todos los productos</option>
                @foreach($productos as $p)
                    <option value="{{ $p->id }}" {{ ($productoId ?? '') == $p->id ? 'selected' : '' }}>{{ $p->codigo }} — {{ Str::limit($p->nombre, 40) }}</option>
                @endforeach
            </select>
            <select name="tipo" class="form-control" style="min-width:180px;">
                <option value="">Todos los tipos</option>
                <option value="entrada_compra" {{ ($tipo ?? '') == 'entrada_compra' ? 'selected' : '' }}>Entrada (compra)</option>
                <option value="salida_factura" {{ ($tipo ?? '') == 'salida_factura' ? 'selected' : '' }}>Salida (factura)</option>
                <option value="devolucion_factura" {{ ($tipo ?? '') == 'devolucion_factura' ? 'selected' : '' }}>Devolución (factura)</option>
                <option value="salida_remision" {{ ($tipo ?? '') == 'salida_remision' ? 'selected' : '' }}>Salida (remisión)</option>
                <option value="entrada_manual" {{ ($tipo ?? '') == 'entrada_manual' ? 'selected' : '' }}>Entrada manual</option>
                <option value="salida_manual" {{ ($tipo ?? '') == 'salida_manual' ? 'selected' : '' }}>Salida manual</option>
            </select>
            <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
        </form>
    </div>
</div>

<div class="table-container">
    @if($movimientos->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Producto</th>
                <th>Tipo</th>
                <th class="td-right">Cantidad</th>
                <th class="td-right">Stock resultante</th>
                <th>Referencia</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $m)
            <tr>
                <td class="text-mono" style="font-size:13px;">{{ $m->created_at->format('d/m/Y H:i') }}</td>
                <td>
                    <div class="fw-600">{{ $m->producto->nombre }}</div>
                    <span class="text-muted" style="font-size:12px;">{{ $m->producto->codigo }}</span>
                </td>
                <td>
                    @if(\App\Models\InventarioMovimiento::esEntrada($m->tipo))
                        <span class="badge badge-success">{{ $m->etiqueta_tipo }}</span>
                    @else
                        <span class="badge badge-warning">{{ $m->etiqueta_tipo }}</span>
                    @endif
                </td>
                <td class="td-right text-mono fw-600">{{ \App\Models\InventarioMovimiento::esEntrada($m->tipo) ? '+' : '−' }}{{ number_format($m->cantidad, 2) }}</td>
                <td class="td-right text-mono">{{ number_format($m->stock_resultante ?? 0, 2) }}</td>
                <td style="font-size:13px;">
                    @if($m->factura_id)
                        <a href="{{ route('facturas.show', $m->factura_id) }}">Factura {{ $m->factura->folio ?? $m->factura_id }}</a>
                    @elseif($m->remision_id)
                        <a href="{{ route('remisiones.show', $m->remision_id) }}">Remisión {{ $m->remision->folio ?? $m->remision_id }}</a>
                    @elseif($m->orden_compra_id)
                        <a href="{{ route('ordenes-compra.show', $m->orden_compra_id) }}">OC #{{ $m->orden_compra_id }}</a>
                    @else
                        {{ $m->observaciones ? Str::limit($m->observaciones, 30) : '—' }}
                    @endif
                </td>
                <td>{{ $m->usuario->name ?? '—' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">{{ $movimientos->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">📋</div>
        <div class="empty-state-title">No hay movimientos</div>
        <div class="empty-state-text">Los movimientos se generan al facturar, recibir compras, entregar remisiones o registrar entradas/salidas manuales.</div>
    </div>
    @endif
</div>

@endsection
