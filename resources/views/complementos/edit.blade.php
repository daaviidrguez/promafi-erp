@extends('layouts.app')

@section('title', 'Editar Complemento ' . $complemento->folio_completo)
@section('page-title', '✏️ Editar Complemento ' . $complemento->folio_completo)
@section('page-subtitle', $complemento->cliente->nombre ?? '')

@php
$breadcrumbs = [
    ['title' => 'Complementos de Pago', 'url' => route('complementos.index')],
    ['title' => $complemento->folio_completo, 'url' => route('complementos.show', $complemento->id)],
    ['title' => 'Editar']
];
@endphp

@section('content')

@if(session('error'))
    <div class="alert alert-danger"><span>✗</span> {{ session('error') }}</div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <strong>Revisa los datos:</strong>
        <ul class="mb-0 mt-1" style="padding-left: 1.2rem;">@foreach($errors->all() as $m)<li>{{ $m }}</li>@endforeach</ul>
    </div>
@endif

<form method="POST" action="{{ route('complementos.update', $complemento->id) }}" id="formComplementoEdit">
@csrf
@method('PUT')

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">👤 Cliente</div></div>
            <div class="card-body">
                <div class="info-value">{{ $complemento->cliente->nombre ?? '' }} — {{ $complemento->cliente->rfc ?? '' }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">💵 Datos del Pago</div></div>
            <div class="card-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Fecha de Pago <span class="req">*</span></label>
                        <input type="date" name="fecha_pago" class="form-control" value="{{ old('fecha_pago', $pago->fecha_pago?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pago <span class="req">*</span></label>
                        <select name="forma_pago" class="form-control" required>
                            @foreach($formasPago ?? [] as $fp)
                                <option value="{{ $fp->clave }}" {{ old('forma_pago', $pago->forma_pago) == $fp->clave ? 'selected' : '' }}>{{ $fp->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Monto Total <span class="req">*</span></label>
                    <input type="number" id="monto_total" name="monto_total" class="form-control" min="0.01" step="0.01" value="{{ old('monto_total', $complemento->monto_total) }}" required oninput="actualizarTotales()">
                </div>
                <div class="form-group">
                    <label class="form-label">Número de Operación</label>
                    <input type="text" name="num_operacion" class="form-control" maxlength="100" value="{{ old('num_operacion', $pago->num_operacion) }}">
                </div>
                <input type="hidden" name="moneda" value="MXN">
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">🧾 Facturas a Aplicar</div></div>
            <div class="card-body" style="padding: 0;">
                @if($facturasDisponibles->isEmpty())
                <div class="empty-state" style="padding: 40px 20px;">
                    <div class="empty-state-icon">🧾</div>
                    <div class="empty-state-title">No hay facturas pendientes</div>
                </div>
                @else
                <div class="table-container" style="border: none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th>Fecha</th>
                                <th class="td-right">Pendiente</th>
                                <th class="td-right">Pagar</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($facturasDisponibles as $i => $f)
                            <tr>
                                <td>
                                    <input type="hidden" name="facturas[{{ $i }}][factura_id]" value="{{ $f['id'] }}">
                                    <div class="text-mono fw-600">{{ $f['folio'] }}</div>
                                    <div class="text-muted text-mono" style="font-size: 11px;">{{ substr($f['uuid'] ?? '', 0, 20) }}...</div>
                                </td>
                                <td>{{ $f['fecha'] }}</td>
                                <td class="td-right text-mono">${{ number_format($f['pendiente'], 2, '.', ',') }}</td>
                                <td class="td-right">
                                    <input type="number" name="facturas[{{ $i }}][monto_pagado]" class="form-control monto-pago"
                                           data-pendiente="{{ $f['pendiente'] }}" min="0" max="{{ $f['pendiente'] }}" step="0.01"
                                           value="{{ old('facturas.'.$i.'.monto_pagado', $f['monto_pagado']) }}"
                                           style="width: 140px; text-align: right;" oninput="actualizarTotales()">
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📊 Resumen</div></div>
            <div class="card-body">
                <div class="totales-panel">
                    <div class="totales-row">
                        <span>Monto a pagar</span>
                        <span class="monto" id="montoTotalDisplay">${{ number_format($complemento->monto_total, 2, '.', ',') }}</span>
                    </div>
                    <div class="totales-row" style="color: var(--color-success);">
                        <span>Total aplicado</span>
                        <span class="monto" id="totalAplicadoDisplay">${{ number_format($complemento->monto_total, 2, '.', ',') }}</span>
                    </div>
                    <div class="totales-row grand">
                        <span>Diferencia</span>
                        <span class="monto" id="diferenciaDisplay">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
        <a href="{{ route('complementos.show', $complemento->id) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</div>
</form>

@push('scripts')
<script>
function fmt(n) { return '$' + parseFloat(n).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }
function actualizarTotales() {
    const montoTotal = parseFloat(document.getElementById('monto_total')?.value) || 0;
    let totalAplicado = 0;
    document.querySelectorAll('.monto-pago').forEach(inp => { totalAplicado += parseFloat(inp.value) || 0; });
    const diferencia = montoTotal - totalAplicado;
    const ok = Math.abs(diferencia) < 0.01;
    const difEl = document.getElementById('diferenciaDisplay');
    if (difEl) difEl.textContent = fmt(Math.abs(diferencia));
    if (difEl) difEl.style.color = ok ? 'var(--color-success)' : 'var(--color-danger)';
    const mDisplay = document.getElementById('montoTotalDisplay');
    const aDisplay = document.getElementById('totalAplicadoDisplay');
    if (mDisplay) mDisplay.textContent = fmt(montoTotal);
    if (aDisplay) aDisplay.textContent = fmt(totalAplicado);
}
document.getElementById('formComplementoEdit')?.addEventListener('submit', function(e) {
    const montoTotal = parseFloat(document.getElementById('monto_total')?.value) || 0;
    let totalAplicado = 0;
    document.querySelectorAll('.monto-pago').forEach(inp => { totalAplicado += parseFloat(inp.value) || 0; });
    if (Math.abs(montoTotal - totalAplicado) >= 0.01) {
        e.preventDefault();
        alert('El monto aplicado debe coincidir con el monto total del pago.');
    }
});
document.addEventListener('DOMContentLoaded', actualizarTotales);
</script>
@endpush
@endsection
