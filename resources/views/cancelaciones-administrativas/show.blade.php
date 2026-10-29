@extends('layouts.app')

@section('title', 'Cancelación administrativa #' . $cancelacion->id)
@section('page-title', '🛑 Cancelación administrativa #' . $cancelacion->id)
@section('page-subtitle', ($cancelacion->factura?->folio_completo ?? 'Factura') . ' · Solo ERP (sin PAC)')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Cancelaciones administrativas', 'url' => route('cancelaciones-administrativas.index')],
    ['title' => '#' . $cancelacion->id],
];
$factura = $cancelacion->factura;
@endphp

@section('content')

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom:16px;">
    @foreach($errors->all() as $err)
        <div>{{ $err }}</div>
    @endforeach
</div>
@endif

<div class="cancel-admin-show-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Factura relacionada</div>
            </div>
            <div class="card-body">
                @if($factura)
                <a href="{{ route('facturas.show', $factura->id) }}" class="fw-600 text-primary text-mono">{{ $factura->folio_completo }}</a>
                <div class="text-muted" style="font-size: 12px; margin-top:4px;">
                    {{ $factura->fecha_emision?->format('d/m/Y') }}
                    · ${{ number_format($factura->total, 2, '.', ',') }}
                    · {{ $factura->metodo_pago }}
                </div>
                @if($factura->uuid)
                <div class="text-mono text-muted" style="font-size:11px; margin-top:6px; word-break:break-all;">{{ $factura->uuid }}</div>
                @endif
                <div style="margin-top:12px;">
                    <div class="fw-600">{{ $factura->cliente?->nombre ?? $factura->nombre_receptor }}</div>
                    <div class="text-muted" style="font-size:12px;">{{ $factura->rfc_receptor }}</div>
                </div>
                @else
                <div class="text-muted">
                    {{ $detalle['folio_completo'] ?? 'Factura no disponible' }}
                    @if(!empty($detalle['uuid']))
                    <div class="text-mono" style="font-size:11px;">{{ $detalle['uuid'] }}</div>
                    @endif
                </div>
                @endif
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <div class="card-title">Motivo y auditoría</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Motivo</div>
                    <div class="info-value-sm">{{ $cancelacion->motivo }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Registró</div>
                    <div class="info-value-sm">{{ $cancelacion->user?->name ?? '—' }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Fecha</div>
                    <div class="info-value-sm">{{ $cancelacion->created_at?->format('d/m/Y H:i:s') }}</div>
                </div>
                @if($cancelacion->ip_address)
                <div class="info-row">
                    <div class="info-label">IP</div>
                    <div class="info-value-sm text-mono">{{ $cancelacion->ip_address }}</div>
                </div>
                @endif
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <div class="card-title">Efectos al cancelar</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Total</div>
                    <div class="info-value-sm text-mono">${{ number_format((float) ($detalle['total'] ?? $factura?->total ?? 0), 2, '.', ',') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Cuenta por cobrar</div>
                    <div class="info-value-sm">
                        @if(!empty($detalle['tenia_cuenta_por_cobrar']))
                            Cancelada
                            @if(isset($detalle['monto_pendiente_antes']))
                                <span class="text-muted">(pendiente antes: ${{ number_format((float) $detalle['monto_pendiente_antes'], 2, '.', ',') }})</span>
                            @endif
                        @else
                            Sin CxC
                        @endif
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Inventario</div>
                    <div class="info-value-sm">
                        @if(!empty($detalle['reversa_inventario']))
                            Reversado (hubo salida por timbrado en ERP)
                        @elseif(!empty($detalle['inventario_por_remision']))
                            Sin movimiento (descontado en remisión)
                        @else
                            Sin movimiento de inventario
                        @endif
                    </div>
                </div>
                <div class="alert alert-info" style="margin-top:12px; margin-bottom:0; font-size:13px;">
                    El CFDI no se canceló ante el SAT con esta operación. Use el flujo fiscal de la factura si debe enviarse al PAC.
                </div>
            </div>
        </div>

        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <div class="card-title">Bitácora del expediente</div>
            </div>
            <div class="card-body">
                <div style="display: grid; gap: 8px;">
                    @foreach($timeline as $evento)
                    <div style="border: 1px solid var(--color-gray-200); border-radius: var(--radius-sm); padding: 10px 12px;">
                        <div style="display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                            <strong>
                                {{ $evento->esReversion() ? 'Reactivación' : 'Cancelación administrativa' }}
                                @if($evento->id === $cancelacion->id)
                                    <span class="badge badge-info" style="margin-left:4px;">Este expediente</span>
                                @endif
                            </strong>
                            <span class="text-muted" style="font-size: 11px;">{{ $evento->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        @if($evento->user)
                        <div class="text-muted" style="font-size: 11px;">{{ $evento->user->name }}</div>
                        @endif
                        <div style="margin-top: 4px; font-size: 13px;">{{ $evento->motivo }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Estado</div>
            </div>
            <div class="card-body">
                @if($estado === 'vigente')
                    <span class="badge badge-danger">Vigente</span>
                    <p class="mt-2 text-muted" style="font-size: 13px;">
                        La factura permanece cancelada en el ERP. Puede reactivarse mientras no haya envío al PAC/SAT.
                    </p>
                @else
                    <span class="badge badge-secondary">Revertida</span>
                    <p class="mt-2 text-muted" style="font-size: 13px;">
                        Esta cancelación administrativa fue deshecha. La factura volvió a estado operativo en el ERP.
                    </p>
                    @if($reversion)
                    <div class="info-row" style="margin-top:8px;">
                        <div class="info-label">Reactivó</div>
                        <div class="info-value-sm">{{ $reversion->user?->name ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fecha</div>
                        <div class="info-value-sm">{{ $reversion->created_at?->format('d/m/Y H:i') }}</div>
                    </div>
                    @endif
                @endif

                @if($factura)
                <a href="{{ route('facturas.show', $factura->id) }}" class="btn btn-light w-full" style="margin-top:12px;">Ver factura</a>
                @endif
                <a href="{{ route('cancelaciones-administrativas.index') }}" class="btn btn-light w-full" style="margin-top:8px;">← Historial</a>
                <a href="{{ route('cancelaciones-administrativas.create') }}" class="btn btn-primary w-full" style="margin-top:8px;">➕ Nueva cancelación</a>
            </div>
        </div>

        @can('cancelaciones_administrativas.revertir')
        @if($estado === 'vigente' && $factura)
        <div class="card" style="margin-top:16px;">
            <div class="card-header">
                <div class="card-title">Reactivación</div>
            </div>
            <div class="card-body">
                @if($puedeReactivar)
                <p class="text-muted" style="font-size:13px; margin-top:0;">
                    Deshace la cancelación administrativa: la factura vuelve a <strong>timbrada</strong> en el ERP.
                    El CFDI nunca se canceló ante el SAT.
                </p>
                <button type="button" class="btn btn-outline w-full"
                        onclick="document.getElementById('modalReactivarCancelAdmin').classList.add('show')">
                    ↻ Reactivar cancelación administrativa
                </button>
                @else
                <button type="button" disabled class="btn btn-outline w-full" style="opacity:0.6; cursor:not-allowed;"
                        title="{{ e($motivoNoReactivar) }}">
                    ↻ Reactivar cancelación administrativa
                </button>
                <p class="text-muted" style="font-size:12px; margin-top:8px;">{{ $motivoNoReactivar }}</p>
                @endif
            </div>
        </div>
        @endif
        @endcan
    </div>
</div>

@can('cancelaciones_administrativas.revertir')
@if($estado === 'vigente' && $puedeReactivar && $factura)
<div id="modalReactivarCancelAdmin" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Reactivar cancelación administrativa</div>
            <button class="modal-close"
                    onclick="document.getElementById('modalReactivarCancelAdmin').classList.remove('show')">✕</button>
        </div>
        <form method="POST" action="{{ route('facturas.reactivar-cancelacion-administrativa', $factura->id) }}">
            @csrf
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom: 16px; font-size: 13px; line-height: 1.5;">
                    Reactiva solo el estado en el ERP (vuelve a <strong>timbrada</strong>).
                    El CFDI <strong>nunca se canceló ante el SAT</strong>; se reaplica inventario y cartera si se revirtieron.
                </div>
                <div class="form-group">
                    <label class="form-label">Motivo de reactivación <span class="req">*</span></label>
                    <textarea name="motivo" class="form-control" rows="4" required minlength="10" maxlength="2000"
                              placeholder="Explique por qué se deshace la cancelación administrativa (mín. 10 caracteres).">{{ old('motivo') }}</textarea>
                    <p class="form-hint" style="margin-top: 6px;">Registro obligatorio para auditoría.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                        onclick="document.getElementById('modalReactivarCancelAdmin').classList.remove('show')">
                    Cerrar
                </button>
                <button type="submit" class="btn btn-primary">Confirmar reactivación</button>
            </div>
        </form>
    </div>
</div>
@endif
@endcan

@endsection

@push('styles')
<style>
@media (max-width: 900px) {
    .cancel-admin-show-grid { grid-template-columns: 1fr !important; }
}
</style>
@endpush

@push('scripts')
<script>
(function () {
    var modal = document.getElementById('modalReactivarCancelAdmin');
    if (!modal) return;
    modal.addEventListener('click', function (e) {
        if (e.target === modal) modal.classList.remove('show');
    });
})();
</script>
@endpush
