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
$esManual = $movimiento->esManual();
$esReversible = $movimiento->esReversible();
$estaRevertido = $movimiento->estaRevertido();
$esReverso = $movimiento->esReverso();
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
                        @if($estaRevertido)
                            <span class="badge badge-gray" style="margin-left:6px;">Revertido</span>
                        @endif
                    </div></div>
                    <div class="info-row"><div class="info-label">Cantidad</div><div class="info-value text-mono fw-600">{{ \App\Models\InventarioMovimiento::esEntrada($movimiento->tipo) ? '+' : '−' }}{{ number_format($movimiento->cantidad, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Stock anterior</div><div class="info-value text-mono">{{ number_format($movimiento->stock_anterior ?? 0, 2) }}</div></div>
                    <div class="info-row"><div class="info-label">Stock resultante</div><div class="info-value text-mono fw-600">{{ number_format($movimiento->stock_resultante ?? 0, 2) }}</div></div>
                    @if($movimiento->costo_unitario !== null)
                    <div class="info-row"><div class="info-label">Costo unitario</div><div class="info-value text-mono">${{ number_format((float) $movimiento->costo_unitario, 2, '.', ',') }}</div></div>
                    <div class="info-row"><div class="info-label">Costo promedio anterior</div><div class="info-value text-mono">${{ number_format((float) ($movimiento->costo_promedio_anterior ?? 0), 2, '.', ',') }}</div></div>
                    <div class="info-row"><div class="info-label">Costo promedio resultante</div><div class="info-value text-mono fw-600">${{ number_format((float) ($movimiento->costo_promedio_resultante ?? 0), 2, '.', ',') }}</div></div>
                    @endif
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
                    <a href="{{ route('facturas.show', [$movimiento->factura_id, 'from' => 'inventario-movimiento', 'from_id' => $movimiento->id]) }}" class="btn btn-outline w-full">Factura {{ $movimiento->factura->folio ?? $movimiento->factura_id }}</a>
                @elseif($movimiento->remision_id)
                    <a href="{{ route('remisiones.show', $movimiento->remision_id) }}" class="btn btn-outline w-full">Remisión {{ $movimiento->remision->folio ?? $movimiento->remision_id }}</a>
                @elseif($movimiento->orden_compra_id)
                    <a href="{{ route('ordenes-compra.show', $movimiento->orden_compra_id) }}" class="btn btn-outline w-full">Orden de compra #{{ $movimiento->orden_compra_id }}</a>
                @elseif($movimiento->factura_compra_id)
                    <a href="{{ route('compras.show', $movimiento->factura_compra_id) }}" class="btn btn-outline w-full">Compra {{ optional($movimiento->facturaCompra)->folio_completo ?? '#' . $movimiento->factura_compra_id }}</a>
                @elseif($esManual)
                    <span class="text-muted">{{ $movimiento->folio ?? $movimiento->etiqueta_tipo }}</span>
                @else
                    <span class="text-muted">{{ $movimiento->observaciones ? Str::limit($movimiento->observaciones, 60) : '—' }}</span>
                @endif

                @if($esReverso && $movimiento->reversoDe)
                    <div style="margin-top:12px;">
                        <div class="info-label" style="margin-bottom:6px;">Revierte a</div>
                        <a href="{{ route('inventario.movimiento.show', $movimiento->reversoDe->id) }}" class="btn btn-outline w-full">
                            {{ $movimiento->reversoDe->folio ?? ('#' . $movimiento->reversoDe->id) }}
                        </a>
                    </div>
                @endif

                @if($estaRevertido && $movimiento->reverso)
                    <div style="margin-top:12px;">
                        <div class="info-label" style="margin-bottom:6px;">Revertido por</div>
                        <a href="{{ route('inventario.movimiento.show', $movimiento->reverso->id) }}" class="btn btn-outline w-full">
                            {{ $movimiento->reverso->folio ?? ('#' . $movimiento->reverso->id) }}
                        </a>
                    </div>
                @endif
            </div>
        </div>

        @if($esReversible)
        <div class="card" style="margin-top:16px;">
            <div class="card-header"><div class="card-title">Reversar movimiento</div></div>
            <div class="card-body">
                <p class="text-muted" style="font-size:13px;margin:0 0 12px;">
                    Genera el movimiento contrario y ajusta el stock actual.
                    @if(\App\Models\InventarioMovimiento::esEntrada($movimiento->tipo))
                        Si el stock ya se consumió, no se podrá reversar.
                    @endif
                </p>
                <button type="button"
                        class="btn btn-danger-outline w-full"
                        onclick="document.getElementById('modalReversarMovimiento').classList.add('show')">
                    ↺ Reversar
                </button>
            </div>
        </div>
        @elseif($esManual && $estaRevertido)
        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <span class="badge badge-gray">Ya revertido</span>
                <p class="text-muted" style="font-size:13px;margin:8px 0 0;">Este movimiento no se puede reversar otra vez.</p>
            </div>
        </div>
        @elseif($esManual && $esReverso)
        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <span class="badge badge-gray">Movimiento de reversa</span>
                <p class="text-muted" style="font-size:13px;margin:8px 0 0;">Las reversas no se vuelven a reversar; corrige con un nuevo ajuste manual si hace falta.</p>
            </div>
        </div>
        @endif

        <div class="card" style="margin-top:16px;">
            <div class="card-body">
                <a href="{{ route('inventario.movimientos') }}" class="btn btn-light w-full">← Volver a movimientos</a>
                <a href="{{ route('inventario.show-producto', $movimiento->producto_id) }}" class="btn btn-outline w-full" style="margin-top:8px;">Ver historial del producto</a>
            </div>
        </div>
    </div>
</div>

@if($esReversible)
<div id="modalReversarMovimiento" class="modal" onclick="if(event.target===this)this.classList.remove('show')">
    <div class="modal-box" style="max-width:480px;" onclick="event.stopPropagation()">
        <div class="modal-header">
            <div class="modal-title" style="color:var(--color-danger);">⚠️ Reversar movimiento</div>
            <button type="button"
                    class="modal-close"
                    onclick="document.getElementById('modalReversarMovimiento').classList.remove('show')"
                    aria-label="Cerrar">✕</button>
        </div>
        <form method="POST" action="{{ route('inventario.movimiento.reversar', $movimiento) }}">
            @csrf
            <div class="modal-body">
                <p class="text-muted" style="margin:0 0 12px;font-size:14px;line-height:1.45;">
                    ¿Reversar <strong>{{ $movimiento->folio ?? ('#' . $movimiento->id) }}</strong>?
                    Se registrará un movimiento opuesto en el kardex. El original <strong>no se elimina</strong>.
                </p>
                @if(\App\Models\InventarioMovimiento::esEntrada($movimiento->tipo))
                <div class="alert alert-warning" style="margin-bottom:16px;font-size:13px;line-height:1.5;">
                    Requiere stock disponible de <strong>{{ number_format((float) $movimiento->cantidad, 2) }}</strong>.
                    Si ya se consumió, la operación se bloqueará.
                </div>
                @endif
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label" for="motivo">Motivo (opcional)</label>
                    <input type="text"
                           name="motivo"
                           id="motivo"
                           class="form-control"
                           maxlength="400"
                           value="{{ old('motivo') }}"
                           placeholder="Ej. captura errónea">
                    @error('motivo')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
            </div>
            <div class="modal-footer">
                <button type="button"
                        class="btn btn-light"
                        onclick="document.getElementById('modalReversarMovimiento').classList.remove('show')">
                    Cerrar
                </button>
                <button type="submit" class="btn btn-danger">Confirmar reversa</button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection
