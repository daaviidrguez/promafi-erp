@extends('layouts.app')

@section('title', 'Nuevo Complemento de Pago')
@section('page-title', '➕ Nuevo Complemento de Pago')
@section('page-subtitle', 'Crea un CFDI de pago (Tipo P) para tus facturas PPD')

@php
$breadcrumbs = [
    ['title' => 'Complementos de Pago', 'url' => route('complementos.index')],
    ['title' => 'Nuevo Complemento']
];
@endphp

@section('content')

<form method="POST" action="{{ route('complementos.store') }}" id="formComplemento">
    @csrf

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

        {{-- Columna izquierda --}}
        <div>

            {{-- Cliente --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">👤 Cliente</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Cliente <span class="req">*</span></label>
                        <select id="cliente_id" name="cliente_id" class="form-control" required
                                onchange="cargarFacturasPendientes()">
                            <option value="">Seleccionar cliente...</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->id }}"
                                    {{ ($cuentaPreseleccionada && $cuentaPreseleccionada->cliente_id == $cliente->id) ? 'selected' : '' }}>
                                    {{ $cliente->nombre }} — {{ $cliente->rfc }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Datos del Pago --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">💵 Datos del Pago</div>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Fecha de Pago <span class="req">*</span></label>
                            <input type="date" id="fecha_pago" name="fecha_pago" class="form-control"
                                   value="{{ old('fecha_pago', now()->format('Y-m-d')) }}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Forma de Pago <span class="req">*</span></label>
                            <select name="forma_pago" class="form-control" required>
                                @foreach($formasPago ?? [] as $fp)
                                    <option value="{{ $fp->clave }}" {{ old('forma_pago', '03') == $fp->clave ? 'selected' : '' }}>{{ $fp->etiqueta }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Monto Total del Pago <span class="req">*</span></label>
                        <input type="number" id="monto_total" name="monto_total" class="form-control"
                               min="0.01" step="0.01" value="{{ old('monto_total') }}" required
                               oninput="actualizarTotales()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Número de Operación / Referencia</label>
                        <input type="text" name="num_operacion" class="form-control"
                               maxlength="100" placeholder="Ej: 123456789">
                        <span class="form-hint">Número de transferencia, cheque, etc.</span>
                    </div>
                    <input type="hidden" name="moneda" value="MXN">
                </div>
            </div>

            {{-- Facturas a Aplicar --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🧾 Facturas a Aplicar el Pago</div>
                </div>
                <div class="card-body" style="padding: 0;">

                    {{-- Estado inicial: sin cliente seleccionado --}}
                    <div id="facturasPlaceholder">
                        <div class="empty-state" style="padding: 40px 20px;">
                            <div class="empty-state-icon">🧾</div>
                            <div class="empty-state-title">Selecciona un cliente</div>
                            <div class="empty-state-text">Se mostrarán sus facturas pendientes de pago</div>
                        </div>
                    </div>

                    {{-- Facturas cargadas dinámicamente --}}
                    <div id="facturasList" style="display: none;"></div>

                </div>
            </div>

        </div>

        {{-- Columna derecha --}}
        <div>

            {{-- Resumen del Pago --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📊 Resumen del Pago</div>
                </div>
                <div class="card-body">
                    <div class="totales-panel">
                        <div class="totales-row">
                            <span>Monto a pagar</span>
                            <span class="monto" id="montoTotalDisplay">$0.00</span>
                        </div>
                        <div class="totales-row" style="color: var(--color-success);">
                            <span>Total aplicado</span>
                            <span class="monto" id="totalAplicadoDisplay">$0.00</span>
                        </div>
                        <div class="totales-row grand" id="rowDiferencia">
                            <span>Diferencia</span>
                            <span class="monto" id="diferenciaDisplay">$0.00</span>
                        </div>
                    </div>

                    <div class="alert alert-info" style="margin-top: 16px; margin-bottom: 0;">
                        <span>💡</span>
                        <div>El monto aplicado debe coincidir exactamente con el monto del pago para poder timbrar.</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('complementos.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary" id="btnGuardar" disabled>
                ✓ Guardar Complemento
            </button>
        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
let facturasPendientes = [];

async function cargarFacturasPendientes() {
    const clienteId = document.getElementById('cliente_id').value;
    const placeholder = document.getElementById('facturasPlaceholder');
    const listDiv = document.getElementById('facturasList');

    if (!clienteId) {
        placeholder.style.display = 'block';
        listDiv.style.display = 'none';
        return;
    }

    placeholder.innerHTML = `
        <div class="empty-state" style="padding: 40px 20px;">
            <div class="loader" style="margin: 0 auto 12px;"></div>
            <div class="empty-state-title">Cargando facturas...</div>
        </div>`;

    try {
        const response = await fetch(`/complementos/facturas-pendientes?cliente_id=${clienteId}`);
        facturasPendientes = await response.json();

        if (!facturasPendientes.length) {
            placeholder.innerHTML = `
                <div class="empty-state" style="padding: 40px 20px;">
                    <div class="empty-state-icon">✅</div>
                    <div class="empty-state-title">Sin facturas pendientes</div>
                    <div class="empty-state-text">Este cliente no tiene facturas PPD pendientes de pago</div>
                </div>`;
            listDiv.style.display = 'none';
            return;
        }

        placeholder.style.display = 'none';
        listDiv.style.display = 'block';

        let rows = facturasPendientes.map((f, i) => `
            <tr>
                <td>
                    <input type="hidden" name="facturas[${i}][factura_id]" value="${f.id}">
                    <div class="text-mono fw-600">${f.folio}</div>
                    <div class="text-mono text-muted" style="font-size: 11px;">${f.uuid.substring(0, 20)}...</div>
                </td>
                <td>${f.fecha}</td>
                <td class="td-right text-mono">$${parseFloat(f.pendiente).toFixed(2).replace(/\d(?=(\d{3})+\.)/g,'$&,')}</td>
                <td class="td-right">
                    <input type="number" name="facturas[${i}][monto_pagado]"
                           class="form-control monto-pago"
                           data-pendiente="${f.pendiente}"
                           min="0" max="${f.pendiente}" step="0.01" value="0"
                           style="width: 140px; text-align: right;"
                           oninput="actualizarTotales()">
                </td>
            </tr>`).join('');

        listDiv.innerHTML = `
            <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Factura</th>
                            <th>Fecha</th>
                            <th class="td-right">Pendiente</th>
                            <th class="td-right">Pagar</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>`;

        actualizarTotales();

    } catch (err) {
        console.error(err);
        placeholder.innerHTML = `
            <div class="empty-state" style="padding: 32px 20px;">
                <div class="empty-state-icon">⚠️</div>
                <div class="empty-state-title">Error al cargar facturas</div>
            </div>`;
    }
}

function fmt(n) { return '$' + n.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }

function actualizarTotales() {
    const montoTotal = parseFloat(document.getElementById('monto_total').value) || 0;
    let totalAplicado = 0;

    document.querySelectorAll('.monto-pago').forEach(inp => {
        totalAplicado += parseFloat(inp.value) || 0;
    });

    const diferencia = montoTotal - totalAplicado;
    const ok = Math.abs(diferencia) < 0.01;

    document.getElementById('montoTotalDisplay').textContent  = fmt(montoTotal);
    document.getElementById('totalAplicadoDisplay').textContent = fmt(totalAplicado);
    document.getElementById('diferenciaDisplay').textContent  = fmt(Math.abs(diferencia));
    document.getElementById('diferenciaDisplay').style.color  = ok ? 'var(--color-success)' : 'var(--color-danger)';
    document.getElementById('btnGuardar').disabled = !ok || montoTotal === 0;
}

@if($cuentaPreseleccionada)
document.addEventListener('DOMContentLoaded', cargarFacturasPendientes);
@endif
</script>
@endpush