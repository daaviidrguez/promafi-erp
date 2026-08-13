@extends('layouts.app')
@section('title', $entrada->folio)
@section('page-title', '📥 '.$entrada->folio)
@section('page-subtitle', $entrada->proveedor?->nombre)

@php
$breadcrumbs = [
    ['title' => 'Entradas anticipadas', 'url' => route('entradas-anticipadas.index')],
    ['title' => $entrada->folio],
];
$comprasVinculadas = $entrada->facturasCompra->isNotEmpty()
    ? $entrada->facturasCompra
    : collect($entrada->facturaCompra ? [$entrada->facturaCompra] : []);
@endphp

@section('content')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">🏭 Proveedor</div></div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Proveedor</div><div class="info-value">{{ $entrada->proveedor?->nombre }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $entrada->proveedor?->rfc ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Fecha recepción</div><div class="info-value">{{ $entrada->fecha_recepcion->format('d/m/Y') }}</div></div>
                    @if($entrada->ordenCompra)
                    <div class="info-row"><div class="info-label">Orden de compra</div><div class="info-value"><a href="{{ route('ordenes-compra.show', $entrada->ordenCompra->id) }}">{{ $entrada->ordenCompra->folio }}</a></div></div>
                    @endif
                    @if($comprasVinculadas->isNotEmpty())
                    <div class="info-row">
                        <div class="info-label">{{ $comprasVinculadas->count() > 1 ? 'Compras vinculadas' : 'Compra vinculada' }}</div>
                        <div class="info-value">
                            @foreach($comprasVinculadas as $fc)
                                <div><a href="{{ route('compras.show', $fc->id) }}">{{ $fc->folio_interno ?? $fc->folio }}</a></div>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📦 Detalle recibido</div></div>
            <div class="table-container" style="border:none;box-shadow:none;">
                <table>
                    <thead>
                        <tr>
                            <th>Producto</th>
                            <th class="td-center">Recibida</th>
                            <th class="td-center">Facturada</th>
                            <th class="td-right">Costo s/IVA</th>
                            <th class="td-center">IVA</th>
                            <th class="td-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($entrada->detalles as $d)
                        <tr>
                            <td>
                                <div class="fw-600">{{ $d->descripcion }}</div>
                                <div class="text-mono text-muted" style="font-size:12px;">{{ $d->producto?->codigo }}</div>
                            </td>
                            <td class="td-center">{{ number_format($d->cantidad_recibida, 2) }}</td>
                            <td class="td-center">{{ number_format($d->cantidad_facturada, 2) }}</td>
                            <td class="td-right text-mono">${{ number_format($d->precio_unitario_estimado, 2) }}</td>
                            <td class="td-center">{{ $d->etiquetaTasaIva() }}</td>
                            <td class="td-right text-mono fw-600">${{ number_format($d->total, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="display:flex;justify-content:flex-end;">
                <div class="totales-panel" style="min-width:260px;">
                    <div class="totales-row"><span>Subtotal</span><span class="monto text-mono">${{ number_format($entrada->subtotal, 2) }}</span></div>
                    @if($entrada->descuento > 0)
                    <div class="totales-row descuento"><span>Descuento</span><span class="monto">−${{ number_format($entrada->descuento, 2) }}</span></div>
                    @endif
                    <div class="totales-row"><span>IVA</span><span class="monto text-mono">${{ number_format($entrada->iva, 2) }}</span></div>
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto">${{ number_format($entrada->total, 2) }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Estado</div></div>
            <div class="card-body">
                @if($entrada->estado === 'borrador')
                <span class="badge badge-warning" style="font-size:14px;">Borrador</span>
                <p style="margin-top:12px;font-size:13px;">Confirme la recepción para registrar inventario.</p>
                @elseif($entrada->estado === 'confirmada')
                <span class="badge badge-info" style="font-size:14px;">Confirmada</span>
                <p style="margin-top:12px;font-size:13px;">Mercancía en inventario. Registre la factura cuando la reciba del proveedor.</p>
                @elseif($entrada->estado === 'parcialmente_facturada')
                <span class="badge badge-warning" style="font-size:14px;">Parcialmente facturada</span>
                <p style="margin-top:12px;font-size:13px;">Hay partidas con saldo. Puede registrar otra factura (otro CFDI o compra manual) para completar.</p>
                @elseif($entrada->estado === 'facturada')
                <span class="badge badge-success" style="font-size:14px;">Facturada</span>
                @elseif($entrada->estado === 'cancelada')
                <span class="badge badge-danger" style="font-size:14px;">Cancelada</span>
                @else
                <span class="badge badge-secondary" style="font-size:14px;">{{ $entrada->etiquetaEstado() }}</span>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <a href="{{ route('entradas-anticipadas.ver-pdf', $entrada->id) }}"
                   target="_blank" class="btn btn-outline w-full">👁️ Ver PDF</a>
                <a href="{{ route('entradas-anticipadas.descargar-pdf', $entrada->id) }}"
                   class="btn btn-outline w-full">📄 Descargar PDF</a>

                @if($entrada->puedeEditarse())
                <a href="{{ route('entradas-anticipadas.edit', $entrada->id) }}" class="btn btn-outline w-full">✏️ Editar borrador</a>
                <form method="POST" action="{{ route('entradas-anticipadas.confirmar', $entrada->id) }}">@csrf
                    <button type="submit" class="btn btn-primary w-full">✅ Confirmar recepción</button>
                </form>
                @endif
                @if($entrada->puedeFacturarse())
                <a href="{{ route('entradas-anticipadas.facturar', $entrada->id) }}" class="btn btn-success w-full">{{ $entrada->estado === 'parcialmente_facturada' ? '🧾 Registrar siguiente factura' : '🧾 Registrar factura' }}</a>
                @endif
                @if($comprasVinculadas->count() === 1)
                <a href="{{ route('compras.show', $comprasVinculadas->first()->id) }}" class="btn btn-outline w-full">🛒 Ver compra</a>
                @elseif($comprasVinculadas->count() > 1)
                @foreach($comprasVinculadas as $fc)
                <a href="{{ route('compras.show', $fc->id) }}" class="btn btn-outline w-full">🛒 Ver {{ $fc->folio_interno ?? $fc->folio }}</a>
                @endforeach
                @endif
                @if($entrada->puedeCancelarse())
                <form method="POST" action="{{ route('entradas-anticipadas.cancelar', $entrada->id) }}" onsubmit="return confirm('¿Cancelar esta entrada anticipada?');">@csrf
                    <button type="submit" class="btn btn-danger w-full">🗑️ Cancelar</button>
                </form>
                @endif
                <a href="{{ route('entradas-anticipadas.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>
    </div>
</div>

@endsection
