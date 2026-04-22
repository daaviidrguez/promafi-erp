@extends('layouts.app')
@section('title', 'Orden de Compra ' . $ordenCompra->folio)
@section('page-title', '📦 ' . $ordenCompra->folio)
@section('page-subtitle', $ordenCompra->proveedor_nombre)

@php
$breadcrumbs = [
    ['title' => 'Órdenes de Compra', 'url' => route('ordenes-compra.index')],
    ['title' => $ordenCompra->folio],
];
$diasCreditoOrden = (int) ($ordenCompra->dias_credito ?? 0);
$esCreditoOrden = $diasCreditoOrden > 0;
$cuentaVinculada = $ordenCompra->cuentaPorPagar ?? $ordenCompra->facturaCompra?->cuentaPorPagar;
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
                    <div class="info-row"><div class="info-label">Régimen Fiscal</div><div class="info-value text-mono">{{ $ordenCompra->proveedor_regimen_fiscal ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Uso CFDI</div><div class="info-value text-mono">{{ $ordenCompra->proveedor_uso_cfdi ?? '—' }}</div></div>
                    <div class="info-row">
                        <div class="info-label">Condición de compra</div>
                        <div class="info-value">
                            @if($esCreditoOrden)
                                <span class="badge badge-warning">💳 Crédito ({{ $diasCreditoOrden }} días)</span>
                            @else
                                <span class="badge badge-success">💵 Contado</span>
                            @endif
                        </div>
                    </div>
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
                            <th class="td-right">Costo unit.</th>
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
                <p style="margin-top:12px;font-size:13px;">Convierta la orden en compra para continuar con inventario y cuentas por pagar desde el módulo de Compras.</p>
                @elseif($ordenCompra->estado === 'convertida_compra')
                <span class="badge badge-success" style="font-size:14px;">Convertida a compra</span>
                <p style="margin-top:12px;font-size:13px;">Ya existe una compra asociada. Use la ficha de la compra para recibir mercancía o consultar el CFDI.</p>
                @elseif($ordenCompra->estado === 'recibida')
                <span class="badge badge-success" style="font-size:14px;">Recibida</span>
                <p style="margin-top:12px;font-size:13px;">Entrada de inventario registrada (flujo anterior).</p>
                @elseif($ordenCompra->estado === 'cancelada')
                <span class="badge badge-danger" style="font-size:14px;">Cancelada</span>
                <p style="margin-top:12px;font-size:13px;">Orden cancelada. La cuenta por pagar asociada también fue cancelada.</p>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">

                <a href="{{ route('ordenes-compra.ver-pdf', $ordenCompra->id) }}"
                   target="_blank" class="btn btn-outline w-full">👁️ Ver PDF</a>

                <a href="{{ route('ordenes-compra.descargar-pdf', $ordenCompra->id) }}"
                   class="btn btn-outline w-full">📄 Descargar PDF</a>

                @if($ordenCompra->puedeEditarse())
                <a href="{{ route('ordenes-compra.edit', $ordenCompra->id) }}" class="btn btn-primary w-full">✏️ Editar</a>
                @endif

                @if($ordenCompra->puedeAceptarse())
                <form method="POST" action="{{ route('ordenes-compra.aceptar', $ordenCompra->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-success w-full">✅ Aceptar (cargar a Cuentas por pagar)</button>
                </form>
                @endif
                @if($ordenCompra->puedeConvertirseACompra())
                <button type="button" class="btn btn-primary w-full" onclick="document.getElementById('modalConvertirCompra').classList.add('show')">🛒 Convertir a compra</button>
                @endif
                @if($ordenCompra->facturaCompra)
                <a href="{{ route('compras.show', $ordenCompra->facturaCompra->id) }}" class="btn btn-outline w-full">🛒 Ver compra generada</a>
                @endif
                @if($cuentaVinculada && $ordenCompra->estado !== 'cancelada')
                <a href="{{ route('cuentas-por-pagar.show', $cuentaVinculada->id) }}" class="btn btn-outline w-full">💳 Ver cuenta por pagar</a>
                @endif

                @if($ordenCompra->puedeCancelarse())
                <form method="POST" action="{{ route('ordenes-compra.destroy', $ordenCompra->id) }}" style="margin:0;" onsubmit="return confirm('¿Cancelar esta orden de compra? Si existe cuenta por pagar vinculada a la orden, también se cancelará.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Cancelar orden de compra</button>
                </form>
                @endif

                <a href="{{ route('ordenes-compra.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>
    </div>
</div>

@if($ordenCompra->puedeConvertirseACompra())
<div id="modalConvertirCompra" class="modal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-box" style="max-width: 480px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title">Convertir orden en compra</div>
            <button type="button" class="modal-close" onclick="document.getElementById('modalConvertirCompra').classList.remove('show')" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin:0;font-size:14px;line-height:1.45;">Elija cómo desea registrar la compra. En ambos casos se abrirá la ficha de la compra al finalizar para continuar el flujo.</p>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:18px;">
                <form method="POST" action="{{ route('ordenes-compra.convertir-compra-normal', $ordenCompra->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">A — Registrar compra normal</button>
                </form>
                <a href="{{ route('compras.upload-cfdi', ['orden_compra_id' => $ordenCompra->id]) }}" class="btn btn-outline w-full" style="text-align:center;text-decoration:none;" onclick="document.getElementById('modalConvertirCompra').classList.remove('show')">B — Registrar compra por CFDI</a>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="document.getElementById('modalConvertirCompra').classList.remove('show')">Cerrar</button>
        </div>
    </div>
</div>
@endif

@endsection
