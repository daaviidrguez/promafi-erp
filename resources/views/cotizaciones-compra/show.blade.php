@extends('layouts.app')
@section('title', 'Cotización de Compra ' . $cotizacionCompra->folio)
@section('page-title', '📋 ' . $cotizacionCompra->folio)
@section('page-subtitle', $cotizacionCompra->proveedor_nombre)

@php
$breadcrumbs = [
    ['title' => 'Cotizaciones de Compra', 'url' => route('cotizaciones-compra.index')],
    ['title' => $cotizacionCompra->folio],
];
@endphp

@section('content')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏭 Proveedor</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Razón Social</div><div class="info-value">{{ $cotizacionCompra->proveedor_nombre }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $cotizacionCompra->proveedor_rfc ?? '—' }}</div></div>
                    @if($cotizacionCompra->proveedor_email)<div class="info-row"><div class="info-label">Email</div><div class="info-value">{{ $cotizacionCompra->proveedor_email }}</div></div>@endif
                    @if($cotizacionCompra->proveedor_telefono)<div class="info-row"><div class="info-label">Teléfono</div><div class="info-value">{{ $cotizacionCompra->proveedor_telefono }}</div></div>@endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Detalle de Productos</div>
                <span class="badge badge-primary">{{ $cotizacionCompra->detalles->count() }} artículo(s)</span>
            </div>
            <div class="table-container" style="border:none;box-shadow:none;margin-bottom:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="td-center">Cant.</th>
                            <th class="td-right">P. Unit.</th>
                            <th class="td-center">IVA</th>
                            <th class="td-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cotizacionCompra->detalles as $d)
                        <tr>
                            <td class="text-mono">{{ $d->codigo ?? '—' }}</td>
                            <td><div class="fw-600">{{ $d->descripcion }}</div>@if($d->es_producto_manual)<span class="text-muted" style="font-size:11px;">Manual</span>@endif</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-right text-mono">${{ number_format($d->precio_unitario, 2) }}</td>
                            <td class="td-center">@if($d->tasa_iva===null)Exento @else {{ number_format($d->tasa_iva*100,0) }}% @endif</td>
                            <td class="td-right text-mono fw-600">${{ number_format($d->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="display:flex;justify-content:flex-end;">
                <div class="totales-panel" style="min-width:260px;">
                    <div class="totales-row"><span>Subtotal</span><span class="monto text-mono">${{ number_format($cotizacionCompra->subtotal, 2) }}</span></div>
                    @if($cotizacionCompra->descuento>0)<div class="totales-row descuento"><span>Descuento</span><span class="monto">−${{ number_format($cotizacionCompra->descuento, 2) }}</span></div>@endif
                    <div class="totales-row"><span>IVA</span><span class="monto text-mono">${{ number_format($cotizacionCompra->iva, 2) }}</span></div>
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto">${{ number_format($cotizacionCompra->total, 2) }} MXN</span></div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Estado</div></div>
            <div class="card-body">
                @if($cotizacionCompra->estado === 'borrador')
                <span class="badge badge-warning" style="font-size:14px;">Borrador</span>
                <p style="margin-top:12px;font-size:13px;">Aprueba esta cotización para poder generar la orden de compra.</p>
                @elseif($cotizacionCompra->estado === 'aprobada')
                <span class="badge badge-success" style="font-size:14px;">Aprobada</span>
                <p style="margin-top:12px;font-size:13px;">Genera la orden de compra con el botón inferior.</p>
                @elseif($cotizacionCompra->estado === 'convertida_oc')
                <span class="badge badge-info" style="font-size:14px;">Convertida a OC</span>
                <p style="margin-top:12px;font-size:13px;">Ya se generó una orden de compra desde esta cotización.</p>
                @else
                <span class="badge badge-danger">{{ ucfirst($cotizacionCompra->estado) }}</span>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                @if($cotizacionCompra->estado === 'borrador')
                <form method="POST" action="{{ route('cotizaciones-compra.aprobar', $cotizacionCompra->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-success w-full">✅ Aprobar cotización</button>
                </form>
                <a href="{{ route('cotizaciones-compra.create') }}?id={{ $cotizacionCompra->id }}" class="btn btn-warning w-full">✏️ Editar</a>
                @endif
                @if($cotizacionCompra->estado === 'aprobada')
                <form method="POST" action="{{ route('cotizaciones-compra.generar-orden', $cotizacionCompra->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">📦 Generar orden de compra</button>
                </form>
                @endif
                <a href="{{ route('cotizaciones-compra.index') }}" class="btn btn-light w-full">← Volver al listado</a>
            </div>
        </div>
    </div>
</div>

@endsection
