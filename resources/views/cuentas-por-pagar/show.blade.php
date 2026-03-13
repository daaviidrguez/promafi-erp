@extends('layouts.app')
@php
$esFacturaCompra = $cuentaPorPagar->factura_compra_id !== null;
$referencia = $esFacturaCompra ? ($cuentaPorPagar->facturaCompra->folio_completo ?? 'Factura') : ($cuentaPorPagar->ordenCompra->folio ?? 'Orden');
@endphp
@section('title', 'Cuenta por Pagar')
@section('page-title', '💳 Cuenta por Pagar')
@section('page-subtitle', ($esFacturaCompra ? 'Factura ' : 'Orden ') . $referencia)

@php
$breadcrumbs = [
    ['title' => 'Cuentas por Pagar', 'url' => route('cuentas-por-pagar.index')],
    ['title' => $referencia],
];
@endphp

@section('content')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏭 Proveedor</div>
                <a href="{{ route('proveedores.show', $cuentaPorPagar->proveedor_id) }}" class="btn btn-light btn-sm">Ver proveedor</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Nombre</div><div class="info-value">{{ $cuentaPorPagar->proveedor->nombre }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $cuentaPorPagar->proveedor->rfc ?? '—' }}</div></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">{{ $esFacturaCompra ? '📄 Factura de Compra' : '📦 Orden de Compra' }}</div>
                @if($esFacturaCompra && $cuentaPorPagar->facturaCompra)
                <a href="{{ route('compras.show', $cuentaPorPagar->factura_compra_id) }}" class="btn btn-light btn-sm">Ver compra</a>
                @elseif($cuentaPorPagar->ordenCompra)
                <a href="{{ route('ordenes-compra.show', $cuentaPorPagar->orden_compra_id) }}" class="btn btn-light btn-sm">Ver orden</a>
                @endif
            </div>
            <div class="table-container" style="border:none;margin-bottom:0;">
                <table>
                    <thead>
                        <tr><th>Descripción</th><th class="td-center">Cant.</th><th class="td-right">P. Unit.</th><th class="td-right">Total</th></tr>
                    </thead>
                    <tbody>
                        @if($esFacturaCompra && $cuentaPorPagar->facturaCompra)
                        @foreach($cuentaPorPagar->facturaCompra->detalles ?? [] as $d)
                        @php $totalLinea = ($d->importe ?? 0) - ($d->descuento ?? 0) + ($d->impuestos->sum('importe') ?? 0); @endphp
                        <tr>
                            <td>{{ $d->descripcion }}</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-right text-mono">${{ number_format($d->valor_unitario ?? 0, 2) }}</td>
                            <td class="td-right text-mono fw-600">${{ number_format($totalLinea, 2) }}</td>
                        </tr>
                        @endforeach
                        @elseif($cuentaPorPagar->ordenCompra)
                        @foreach($cuentaPorPagar->ordenCompra->detalles as $d)
                        <tr>
                            <td>{{ $d->descripcion }}</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-right text-mono">${{ number_format($d->precio_unitario, 2) }}</td>
                            <td class="td-right text-mono fw-600">${{ number_format($d->total, 2) }}</td>
                        </tr>
                        @endforeach
                        @endif
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="display:flex;justify-content:flex-end;">
                <div class="totales-panel" style="min-width:240px;">
                    <div class="totales-row grand"><span>Total</span><span class="monto">${{ number_format($cuentaPorPagar->monto_total, 2) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">💰 Resumen</div></div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Emisión</div><div class="info-value">{{ $cuentaPorPagar->fecha_emision->format('d/m/Y') }}</div></div>
                    <div class="info-row"><div class="info-label">Vencimiento</div><div class="info-value">{{ $cuentaPorPagar->fecha_vencimiento->format('d/m/Y') }}</div></div>
                    <div class="info-row"><div class="info-label">Monto total</div><div class="info-value text-mono">${{ number_format($cuentaPorPagar->monto_total, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Pagado</div><div class="info-value text-mono">${{ number_format($cuentaPorPagar->monto_pagado, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Pendiente</div><div class="info-value text-mono fw-600" style="font-size:18px;">${{ number_format($cuentaPorPagar->monto_pendiente, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Estado</div><div>@if($cuentaPorPagar->estado === 'pendiente')<span class="badge badge-warning">Pendiente</span>@elseif($cuentaPorPagar->estado === 'parcial')<span class="badge badge-info">Parcial</span>@elseif($cuentaPorPagar->estado === 'vencida')<span class="badge badge-danger">Vencida</span>@else<span class="badge badge-success">Pagada</span>@endif</div></div>
                </div>
            </div>
        </div>
        @if($cuentaPorPagar->comprobante_pago_path)
        <div class="card">
            <div class="card-header"><div class="card-title">📎 Comprobante de pago</div></div>
            <div class="card-body">
                <a href="{{ route('cuentas-por-pagar.ver-comprobante', $cuentaPorPagar->id) }}" target="_blank" class="btn btn-primary w-full">👁️ Ver comprobante de pago</a>
                <a href="{{ route('cuentas-por-pagar.descargar-comprobante', $cuentaPorPagar->id) }}" class="btn btn-outline w-full" style="margin-top:8px;">📄 Descargar comprobante</a>
            </div>
        </div>
        @endif
        @if($cuentaPorPagar->monto_pendiente > 0)
        <div class="card">
            <div class="card-header"><div class="card-title">Registrar pago</div></div>
            <div class="card-body">
                <form method="POST" action="{{ route('cuentas-por-pagar.registrar-pago', $cuentaPorPagar->id) }}" enctype="multipart/form-data">
                    @csrf
                    <div class="form-group">
                        <label class="form-label">Monto <span class="req">*</span></label>
                        <input type="number" name="monto" step="0.01" min="0.01" max="{{ $cuentaPorPagar->monto_pendiente }}" value="{{ min($cuentaPorPagar->monto_pendiente, 10000) }}" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de pago <span class="req">*</span></label>
                        <input type="date" name="fecha_pago" value="{{ date('Y-m-d') }}" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Referencia</label>
                        <input type="text" name="referencia" class="form-control" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Comprobante de pago</label>
                        <input type="file" name="comprobante_pago" accept=".pdf,.jpg,.jpeg,.png" class="form-control">
                        <span class="form-hint">PDF, JPG o PNG. Máx. 5 MB</span>
                    </div>
                    <button type="submit" class="btn btn-primary w-full">Registrar pago</button>
                </form>
            </div>
        </div>
        @endif
    </div>
</div>

@endsection
