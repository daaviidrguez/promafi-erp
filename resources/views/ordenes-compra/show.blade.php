@extends('layouts.app')
@section('title', 'Orden de Compra ' . $ordenCompra->folio)
@section('page-title', '📦 ' . $ordenCompra->folio)
@section('page-subtitle', $ordenCompra->proveedor_nombre)

@php
$breadcrumbs = [
    ['title' => 'Órdenes de Compra', 'url' => route('ordenes-compra.index')],
    ['title' => $ordenCompra->folio],
];
@endphp

@section('content')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏭 Proveedor</div>
                <a href="{{ route('proveedores.show', $ordenCompra->proveedor_id) }}" class="btn btn-light btn-sm">Ver proveedor</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Razón Social</div><div class="info-value">{{ $ordenCompra->proveedor_nombre }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $ordenCompra->proveedor_rfc ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Fecha</div><div class="info-value">{{ $ordenCompra->fecha->format('d/m/Y') }}</div></div>
                    @if($ordenCompra->fecha_recepcion)<div class="info-row"><div class="info-label">Fecha recepción</div><div class="info-value">{{ $ordenCompra->fecha_recepcion->format('d/m/Y') }}</div></div>@endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Detalle</div>
                @if($ordenCompra->cotizacion_compra_id)<span class="badge badge-info">Desde cotización {{ $ordenCompra->cotizacionCompra->folio ?? '' }}</span>@endif
            </div>
            <div class="table-container" style="border:none;box-shadow:none;margin-bottom:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="td-center">Cant.</th>
                            <th class="td-right">P. Unit.</th>
                            <th class="td-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($ordenCompra->detalles as $d)
                        <tr>
                            <td class="text-mono">{{ $d->codigo ?? '—' }}</td>
                            <td>{{ $d->descripcion }}</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-right text-mono">${{ number_format($d->precio_unitario, 2) }}</td>
                            <td class="td-right text-mono fw-600">${{ number_format($d->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="display:flex;justify-content:flex-end;">
                <div class="totales-panel" style="min-width:260px;">
                    <div class="totales-row"><span>Subtotal</span><span class="monto text-mono">${{ number_format($ordenCompra->subtotal, 2) }}</span></div>
                    @if($ordenCompra->descuento>0)<div class="totales-row descuento"><span>Descuento</span><span class="monto">−${{ number_format($ordenCompra->descuento, 2) }}</span></div>@endif
                    <div class="totales-row"><span>IVA</span><span class="monto text-mono">${{ number_format($ordenCompra->iva, 2) }}</span></div>
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto">${{ number_format($ordenCompra->total, 2) }} MXN</span></div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Estado</div></div>
            <div class="card-body">
                @if($ordenCompra->estado === 'borrador')
                <span class="badge badge-warning" style="font-size:14px;">Borrador</span>
                <p style="margin-top:12px;font-size:13px;">Al aceptar se creará la cuenta por pagar.</p>
                @elseif($ordenCompra->estado === 'aceptada')
                <span class="badge badge-info" style="font-size:14px;">Aceptada</span>
                <p style="margin-top:12px;font-size:13px;">Recibe la mercancía para registrar la entrada de inventario.</p>
                @else
                <span class="badge badge-success" style="font-size:14px;">Recibida</span>
                <p style="margin-top:12px;font-size:13px;">Entrada de inventario registrada.</p>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                @if($ordenCompra->puedeAceptarse())
                <form method="POST" action="{{ route('ordenes-compra.aceptar', $ordenCompra->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-success w-full">✅ Aceptar (cargar a Cuentas por pagar)</button>
                </form>
                @endif
                @if($ordenCompra->puedeRecibirse())
                <form method="POST" action="{{ route('ordenes-compra.recibir', $ordenCompra->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">📥 Recibir mercancía (entrada inventario)</button>
                </form>
                @endif
                @if($ordenCompra->cuentaPorPagar)
                <a href="{{ route('cuentas-por-pagar.show', $ordenCompra->cuentaPorPagar->id) }}" class="btn btn-outline w-full">💳 Ver cuenta por pagar</a>
                @endif
                <a href="{{ route('ordenes-compra.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>
    </div>
</div>

@endsection
