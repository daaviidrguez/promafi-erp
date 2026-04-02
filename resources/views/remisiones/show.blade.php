@extends('layouts.app')
@section('title', 'Remisión ' . $remision->folio)
@section('page-title', '🚚 ' . $remision->folio)
@section('page-subtitle', $remision->cliente_nombre)

@php
$breadcrumbs = [
    ['title' => 'Remisiones', 'url' => route('remisiones.index')],
    ['title' => $remision->folio],
];
@endphp

@section('content')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">👥 Cliente</div>
                <a href="{{ route('clientes.show', $remision->cliente_id) }}" class="btn btn-light btn-sm">Ver cliente</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Razón Social</div><div class="info-value">{{ $remision->cliente_nombre }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $remision->cliente_rfc ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Fecha</div><div class="info-value">{{ $remision->fecha->format('d/m/Y') }}</div></div>
                    <div class="info-row"><div class="info-label">Orden de compra</div><div class="info-value text-mono">{{ $remision->orden_compra ?? '—' }}</div></div>
                    @if($remision->fecha_entrega)<div class="info-row"><div class="info-label">Fecha entrega</div><div class="info-value">{{ $remision->fecha_entrega->format('d/m/Y') }}</div></div>@endif
                    @if($remision->direccion_entrega)<div class="info-row"><div class="info-label">Dirección de entrega</div><div class="info-value" style="white-space:pre-wrap;">{{ $remision->direccion_entrega }}</div></div>@endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📦 Detalle</div></div>
            <div class="table-container" style="border:none;box-shadow:none;margin-bottom:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="td-center">Cantidad</th>
                            <th class="td-center">Unidad</th>
                            <th class="td-right">Precio unit.</th>
                            <th class="td-center">IVA</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($remision->detalles as $d)
                        <tr>
                            <td class="text-mono">{{ $d->codigo ?? '—' }}</td>
                            <td>{{ $d->descripcion }}</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-center">{{ $d->unidad }}</td>
                            <td class="td-right text-mono">
                                {{ number_format(($d->precio_unitario ?? $d->producto?->precio_venta ?? 0), 2) }}
                            </td>
                            <td class="td-center">
                                @if(($d->tasa_iva ?? $d->producto?->tasa_iva ?? null) === null)
                                    <span class="text-muted">Exento</span>
                                @else
                                    {{ number_format((($d->tasa_iva ?? $d->producto?->tasa_iva ?? 0) * 100), 0) }}%
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($remision->observaciones)
            <div class="card-body" style="border-top:1px solid var(--color-gray-100);">
                <div class="info-row"><div class="info-label">Observaciones</div><div class="info-value">{{ $remision->observaciones }}</div></div>
            </div>
            @endif
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Estado</div></div>
            <div class="card-body">
                @php
                    $evRemision = $remision->estadoVisualListado();
                    $ayudaEstadoRemision = $remision->estadoAyudaParaShow();
                @endphp
                <span class="badge {{ $evRemision['badge'] }}" style="font-size:14px;">{{ $evRemision['label'] }}</span>
                @if($ayudaEstadoRemision !== '')
                <p style="margin-top:12px;font-size:13px;">{{ $ayudaEstadoRemision }}</p>
                @endif

                <div style="margin-top:16px;padding-top:12px;border-top:1px solid var(--color-gray-100);">
                    <div class="info-label" style="margin-bottom:6px;">Facturada</div>
                    @if($remision->factura_id || $remision->factura_id_cancelada)
                        <span class="badge badge-success" style="font-size:13px;">Sí</span>
                        @can('facturas.ver')
                        <div style="margin-top:10px;display:flex;flex-direction:column;gap:10px;">
                            @if($remision->factura_id_cancelada)
                                <a href="{{ route('facturas.show', $remision->factura_id_cancelada) }}"
                                   class="btn btn-outline btn-sm w-full">
                                    Ver factura vinculada - cancelada
                                </a>
                            @endif

                            @if($remision->factura_id)
                                @php
                                    $esCancelada = $remision->factura && $remision->factura->estado === 'cancelada';
                                @endphp
                                <a href="{{ route('facturas.show', $remision->factura_id) }}"
                                   class="btn btn-outline btn-sm w-full">
                                    {{ $esCancelada ? 'Ver factura vinculada - cancelada' : 'Ver factura vinculada' }}
                                </a>
                            @endif
                        </div>
                        @endcan
                    @else
                        <span class="badge badge-gray" style="font-size:13px;">No</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <a href="{{ route('remisiones.ver-pdf', $remision->id) }}" target="_blank" class="btn btn-outline w-full">📄 Ver PDF</a>
                <a href="{{ route('remisiones.descargar-pdf', $remision->id) }}" class="btn btn-outline w-full">⬇️ Descargar PDF</a>
                @if($remision->puedeEditarse())
                <a href="{{ route('remisiones.edit', $remision->id) }}" class="btn btn-primary w-full">✏️ Editar</a>
                <form method="POST" action="{{ route('remisiones.enviar', $remision->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-success w-full">📤 Marcar como enviada</button>
                </form>
                <p class="text-muted small mb-0" style="margin-top:6px;">Se validará stock y se descontará inventario de los productos que lo controlan. Sin stock suficiente no se puede enviar.</p>
                <form method="POST" action="{{ route('remisiones.destroy', $remision->id) }}" style="margin:0;" onsubmit="return confirm('¿Eliminar esta remisión?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar</button>
                </form>
                @endif
                @if($remision->puedeEntregarse())
                <button type="button"
                        class="btn btn-primary w-full"
                        onclick="document.getElementById('modalMarcarEntregadaRemision').classList.add('show')">
                    ✅ Marcar como entregada
                </button>
                @endif
                @can('facturas.crear')
                    @if($remision->factura && $remision->factura->estado === 'borrador')
                        <a href="{{ route('facturas.edit', $remision->factura->id) }}" class="btn btn-primary w-full">💰 Continuar factura en borrador</a>
                        <p class="text-muted small mb-0" style="margin-top:4px;">Esta remisión ya tiene un borrador vinculado.</p>
                    @elseif($remision->puedeConvertirseAFactura())
                        <a href="{{ route('facturas.create') }}?remision_id={{ $remision->id }}" class="btn btn-primary w-full">💰 Convertir a factura</a>
                        <p class="text-muted small mb-0" style="margin-top:4px;">Al timbrar no se vuelve a descontar inventario (ya se descontó al marcar la remisión como enviada).</p>
                    @endif
                @endcan
                @if($remision->estado === 'entregada')
                    @php
                        $facturaTimbrada = ($remision->factura && $remision->factura->estado === 'timbrada')
                            || ($remision->facturaCancelada && $remision->facturaCancelada->estado === 'timbrada');
                    @endphp
                    <form method="POST" action="{{ route('remisiones.cancelar', $remision->id) }}" style="margin:0;">
                        @csrf
                        <button type="submit"
                                class="btn btn-outline w-full"
                                style="border-color:var(--color-danger);color:var(--color-danger);"
                                {{ $facturaTimbrada ? 'disabled' : '' }}>
                            Cancelar remisión
                        </button>
                    </form>
                    @if($facturaTimbrada)
                        <p class="text-muted small mb-0" style="margin-top:4px;">
                            Inhabilitado: existe factura timbrada vinculada.
                        </p>
                    @endif
                @endif
                @if($remision->puedeCancelarse() && $remision->estado !== 'borrador')
                <form method="POST" action="{{ route('remisiones.cancelar', $remision->id) }}" style="margin:0;" onsubmit="return confirm('¿Cancelar esta remisión?');">
                    @csrf
                    <button type="submit" class="btn btn-outline w-full" style="border-color:var(--color-danger);color:var(--color-danger);">Cancelar remisión</button>
                </form>
                @endif
                <a href="{{ route('remisiones.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>
    </div>
</div>

@if($remision->puedeEntregarse())
<div id="modalMarcarEntregadaRemision" class="modal">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <div class="modal-title">Confirmar entrega</div>
            <button type="button"
                    class="modal-close"
                    onclick="document.getElementById('modalMarcarEntregadaRemision').classList.remove('show')"
                    aria-label="Cerrar">✕</button>
        </div>
        <form method="POST" action="{{ route('remisiones.entregar', $remision->id) }}">
            @csrf
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom:0;">
                    ¿Confirmas que todas las partidas de la remisión fueron entregadas?
                    Si es así, pulsa <strong>Aceptar</strong>; si no, cancela y comprueba en Logística que no existan partidas con entrega parcial.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-light"
                        onclick="document.getElementById('modalMarcarEntregadaRemision').classList.remove('show')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-primary">Aceptar</button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
