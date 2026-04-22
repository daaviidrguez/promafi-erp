@extends('layouts.app')
@section('title', 'Compra ' . $compra->folio_completo)
@section('page-title', '🛒 ' . $compra->folio_completo)
@section('page-subtitle', $compra->nombre_emisor)

@php
$breadcrumbs = [
    ['title' => 'Compras', 'url' => route('compras.index')],
    ['title' => $compra->folio_completo],
];
@endphp

@section('content')

@if(!empty($revisionPreciosBanner))
<div class="card" style="margin-bottom:20px;border-left:4px solid #d97706;background:#fffbeb;">
    <div class="card-body" style="display:flex;flex-wrap:wrap;gap:14px;align-items:center;justify-content:space-between;">
        <div style="font-size:14px;color:var(--color-gray-800);">
            <strong>⚠️ {{ $revisionPreciosBanner }} producto(s)</strong> requieren revisión de precio de venta tras esta compra (CFDI).
        </div>
        <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
            @can('productos.ver')
            <a href="{{ route('productos.revision-precios', ['compra_id' => $compra->id]) }}" class="btn btn-primary btn-sm">Revisar ahora</a>
            @else
            <span style="font-size:13px;color:var(--color-gray-600);">Se requiere permiso «Ver productos» para abrir la revisión.</span>
            @endcan
            <form method="POST" action="{{ route('compras.dismiss-revision-precios', $compra) }}" style="margin:0;">
                @csrf
                <button type="submit" class="btn btn-light btn-sm">Después</button>
            </form>
        </div>
    </div>
</div>
@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏭 Proveedor</div>
                @if($compra->proveedor)
                <a href="{{ route('proveedores.show', $compra->proveedor_id) }}" class="btn btn-light btn-sm">Ver proveedor</a>
                @endif
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Razón Social (Emisor)</div><div class="info-value">{{ $compra->nombre_emisor }}</div></div>
                    <div class="info-row"><div class="info-label">RFC Emisor</div><div class="info-value text-mono">{{ $compra->rfc_emisor ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Fecha emisión</div><div class="info-value">{{ $compra->fecha_emision?->format('d/m/Y') ?? '—' }}</div></div>
                    @if($compra->uuid)
                    <div class="info-row"><div class="info-label">UUID</div><div class="info-value text-mono" style="font-size:12px;">{{ $compra->uuid }}</div></div>
                    @endif
                    @php
                        $usoCfdiEtiqueta = $usoCfdi
                            ? (optional(\App\Models\UsoCfdi::where('clave', $usoCfdi)->first())->etiqueta ?? null)
                            : null;
                    @endphp
                    <div class="info-row">
                        <div class="info-label">Uso del CFDI</div>
                        <div class="info-value">
                            @if($usoCfdi)
                                @php
                                    $et = $usoCfdiEtiqueta ? trim((string) $usoCfdiEtiqueta) : '';
                                    $clave = trim((string) $usoCfdi);
                                    $etUpper = mb_strtoupper($et);
                                    $claveUpper = mb_strtoupper($clave);
                                    $etiquetaIncluyeClave = $et !== '' && str_starts_with($etUpper, $claveUpper);
                                @endphp
                                @if($etiquetaIncluyeClave)
                                    {{ $et }}
                                @elseif($et !== '')
                                    {{ $clave }} - {{ $et }}
                                @else
                                    {{ $clave }}
                                @endif
                            @else
                                —
                            @endif
                        </div>
                    </div>
                    @if($compra->forma_pago)
                    <div class="info-row"><div class="info-label">Forma de pago</div><div class="info-value">{{ optional(\App\Models\FormaPago::where('clave', $compra->forma_pago)->first())->etiqueta ?? $compra->forma_pago }}</div></div>
                    @endif
                    <div class="info-row"><div class="info-label">Método de pago</div><div class="info-value">{{ $compra->metodo_pago === 'PPD' ? 'PPD - Pago diferido' : 'PUE - Una exhibición' }}</div></div>
                    @if($compra->ordenCompra)
                    <div class="info-row"><div class="info-label">Orden de compra de origen</div><div class="info-value"><a href="{{ route('ordenes-compra.show', $compra->ordenCompra->id) }}" class="text-mono fw-600">{{ $compra->ordenCompra->folio }}</a></div></div>
                    @endif
                    @if($compra->ordenCompra?->cotizacionCompra)
                    <div class="info-row"><div class="info-label">Cotización de compra de origen</div><div class="info-value"><a href="{{ route('cotizaciones-compra.show', $compra->ordenCompra->cotizacionCompra->id) }}" class="text-mono fw-600">{{ $compra->ordenCompra->cotizacionCompra->folio }}</a></div></div>
                    @endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Detalle</div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;align-items:center;">
                    @if($compra->uuid)<span class="badge badge-success">CFDI</span>@else<span class="badge badge-info">Manual</span>@endif
                    @if($compra->ordenCompra)<span class="badge badge-gray">Desde orden de compra {{ $compra->ordenCompra->folio }}</span>@endif
                    @if($compra->ordenCompra?->cotizacionCompra)<span class="badge badge-info">Desde cotización {{ $compra->ordenCompra->cotizacionCompra->folio }}</span>@endif
                </div>
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
                        @foreach($compra->detalles as $d)
                        @php $totalLinea = ($d->importe ?? 0) - ($d->descuento ?? 0) + $d->impuestos->sum('importe'); @endphp
                        <tr>
                            <td class="text-mono">{{ $d->no_identificacion ?? '—' }}</td>
                            <td>{{ $d->descripcion }}</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-right text-mono">${{ number_format($d->valor_unitario ?? 0, 2) }}</td>
                            <td class="td-right text-mono fw-600">${{ number_format($totalLinea, 2) }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="display:flex;justify-content:flex-end;">
                <div class="totales-panel" style="min-width:260px;">
                    <div class="totales-row"><span>Subtotal</span><span class="monto text-mono">${{ number_format($compra->subtotal ?? 0, 2) }}</span></div>
                    @if(($compra->descuento ?? 0) > 0)<div class="totales-row descuento"><span>Descuento</span><span class="monto">−${{ number_format($compra->descuento, 2) }}</span></div>@endif
                    <div class="totales-row"><span>IVA</span><span class="monto text-mono">${{ number_format(($compra->detalles->sum(fn($d)=>$d->impuestos->sum('importe'))), 2) }}</span></div>
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto">${{ number_format($compra->total ?? 0, 2) }} MXN</span></div>
                </div>
            </div>
        </div>
        @if($compra->observaciones)
        <div class="card">
            <div class="card-header"><div class="card-title">Notas</div></div>
            <div class="card-body">{{ $compra->observaciones }}</div>
        </div>
        @endif
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Estado</div></div>
            <div class="card-body">
                @if($compra->estado === 'recibida')
                <span class="badge badge-success" style="font-size:14px;">Recibida</span>
                <p style="margin-top:12px;font-size:13px;">Entrada de inventario registrada.</p>
                @if($compra->fecha_recepcion)<p style="margin-top:4px;font-size:12px;color:var(--color-gray-500);">Recibida el {{ $compra->fecha_recepcion->format('d/m/Y') }}</p>@endif
                @elseif($compra->estado === 'cancelada')
                <span class="badge badge-danger" style="font-size:14px;">Cancelada</span>
                <p style="margin-top:12px;font-size:13px;">Compra cancelada.</p>
                @elseif($compra->estado === 'borrador')
                <span class="badge badge-warning" style="font-size:14px;">Borrador</span>
                <p style="margin-top:12px;font-size:13px;">Compra en borrador.</p>
                @else
                <span class="badge badge-info" style="font-size:14px;">Registrada</span>
                <p style="margin-top:12px;font-size:13px;">{{ $compra->uuid ? 'Registrada desde XML del proveedor.' : 'Registrada manualmente.' }} Recibe la mercancía para registrar la entrada de inventario.</p>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">

                <a href="{{ route('compras.ver-pdf', $compra->id) }}"
                   target="_blank" class="btn btn-outline w-full">👁️ Ver PDF</a>

                <a href="{{ route('compras.descargar-pdf', $compra->id) }}"
                   class="btn btn-outline w-full">📄 Descargar PDF</a>

                @if($revisionPreciosAccionCount > 0)
                @can('productos.ver')
                <a href="{{ route('productos.revision-precios', ['compra_id' => $compra->id]) }}"
                   class="btn w-full btn-revision-precios-accion">⚠️ Revisión de precios ({{ $revisionPreciosAccionCount }})</a>
                @endcan
                @endif

                @if($compra->puedeRecibirse())
                <form method="POST" action="{{ route('compras.recibir', $compra->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">📥 Recibir mercancía (entrada inventario)</button>
                </form>
                @endif

                @if($compra->cuentaPorPagar)
                <a href="{{ route('cuentas-por-pagar.show', $compra->cuentaPorPagar->id) }}" class="btn btn-outline w-full">💳 Ver cuenta por pagar</a>
                @endif

                <a href="{{ route('compras.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .btn-revision-precios-accion {
        background: #fffbeb !important;
        border: 1px solid #fde68a !important;
        border-left: 4px solid #d97706 !important;
        color: #92400e !important;
        font-weight: 700 !important;
        box-shadow: none !important;
        text-decoration: none !important;
    }
    .btn-revision-precios-accion:hover {
        background: #fef3c7 !important;
        color: #78350f !important;
    }
</style>
@endpush
@endsection
