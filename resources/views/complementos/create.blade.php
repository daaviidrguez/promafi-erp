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

@if(session('error'))
    <div class="alert alert-danger">
        <span>✗</span> {{ session('error') }}
    </div>
@endif
@if($errors->any())
    <div class="alert alert-danger">
        <span>✗</span>
        <strong>Revisa los datos:</strong>
        <ul class="mb-0 mt-1" style="padding-left: 1.2rem;">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('complementos.store') }}" id="formComplemento">
    @csrf

    <div class="responsive-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

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
                                    {{ old('cliente_id', $cuentaPreseleccionada?->cliente_id) == $cliente->id ? 'selected' : '' }}>
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
                        @error('monto_total')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
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

            {{-- Relación de CFDI (SAT 2026) --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🔗 Relación de CFDI</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">¿Sustituir un complemento emitido con errores?</label>
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" id="checkSustituirComplementoCfdi" name="sustituir_cfdi" value="1" {{ old('sustituir_cfdi') ? 'checked' : '' }}>
                                <span>Sí, este complemento sustituye un CFDI previo (complemento con errores)</span>
                            </label>
                        </div>
                    </div>
                    <div id="bloqueComplementoCfdiSustituir" class="form-group" style="display: none;">
                        <label class="form-label">CFDI a sustituir (UUID del complemento)</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="inputComplementoUuidReferenciaDisplay" class="form-control" readonly placeholder="Seleccione el complemento emitido con errores" style="flex: 1; min-width: 200px; background: var(--color-gray-50);">
                            <input type="hidden" name="uuid_referencia" id="inputComplementoUuidReferencia" value="{{ old('uuid_referencia') }}">
                            <input type="hidden" name="tipo_relacion" id="inputComplementoTipoRelacion" value="04">
                            <button type="button" class="btn btn-outline-primary" onclick="abrirModalSeleccionarComplementoCfdiSustituir()">Seleccionar complemento a sustituir</button>
                            <button type="button" class="btn btn-light btn-sm" onclick="limpiarComplementoCfdiSustituir()">Quitar</button>
                        </div>
                    </div>
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
                        <div style="margin-top: 8px; font-size: 12px;">El «Pendiente» por factura ya descuenta lo cubierto por Notas de Crédito; no se puede emitir un complemento por ese monto (coherencia SAT).</div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; flex-direction: column; gap: 12px;">
            <p class="text-muted" style="font-size: 13px; margin: 0;">
                Guarda el complemento en <strong>borrador</strong>. Después, en la ficha del complemento, usa <strong>Emitir complemento</strong> para timbrarlo y aplicar el pago a las cuentas por cobrar.
            </p>
            <div style="display: flex; gap: 12px; justify-content: flex-end;">
                <a href="{{ route('complementos.index') }}" class="btn btn-light">Cancelar</a>
                <button type="submit" class="btn btn-primary" id="btnGuardar">
                    Guardar en borrador
                </button>
            </div>
        </div>
    </div>

</form>

{{-- Modal seleccionar complemento a sustituir (Relación CFDI) --}}
<div id="modalSeleccionarComplementoCfdiSustituir" class="modal">
    <div class="modal-box" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar complemento a sustituir</div>
            <button type="button" class="modal-close" onclick="cerrarModalComplementoCfdiSustituir()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom: 12px;">Elija el complemento de pago timbrado que fue emitido con errores y que este nuevo complemento sustituye.</p>
            <div class="table-container" style="max-height: 320px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="listaComplementosCfdiSustituir"></tbody>
                </table>
            </div>
            <div id="cargandoComplementoCfdiSustituir" style="text-align: center; padding: 20px; color: var(--color-gray-500);">Cargando complementos...</div>
            <div id="sinComplementosCfdiSustituir" style="display: none; text-align: center; padding: 20px; color: var(--color-gray-500);">No hay complementos timbrados para seleccionar.</div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let facturasPendientes = [];
const listarComplementosParaRelacionUrl = '{{ route("complementos.listar-para-relacion") }}';
const facturasPendientesUrl = '{{ route("complementos.facturas-pendientes") }}';

(function() {
    var check = document.getElementById('checkSustituirComplementoCfdi');
    var bloque = document.getElementById('bloqueComplementoCfdiSustituir');
    if (check) {
        check.addEventListener('change', function() {
            bloque.style.display = this.checked ? 'block' : 'none';
            if (!this.checked) limpiarComplementoCfdiSustituir();
        });
        if (check.checked) bloque.style.display = 'block';
    }
})();

function abrirModalSeleccionarComplementoCfdiSustituir() {
    document.getElementById('modalSeleccionarComplementoCfdiSustituir').classList.add('show');
    document.getElementById('listaComplementosCfdiSustituir').innerHTML = '';
    document.getElementById('cargandoComplementoCfdiSustituir').style.display = 'block';
    document.getElementById('sinComplementosCfdiSustituir').style.display = 'none';
    fetch(listarComplementosParaRelacionUrl)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('cargandoComplementoCfdiSustituir').style.display = 'none';
            var tbody = document.getElementById('listaComplementosCfdiSustituir');
            var lista = Array.isArray(data) ? data : (data && (data.data || data.list) ? (data.data || data.list) : []);
            if (!lista.length) {
                document.getElementById('sinComplementosCfdiSustituir').style.display = 'block';
                return;
            }
            lista.forEach(function(c) {
                var folioTexto = c.folio_completo || '';
                if (c.estado === 'cancelado' && c.motivo_cancelacion === '02') {
                    folioTexto = folioTexto + ' <span class="badge badge-warning" style="font-size: 10px; margin-left: 6px;">Cancelado (02)</span>';
                }
                var tr = document.createElement('tr');
                tr.innerHTML = '<td class="text-mono">' + folioTexto + '</td>' +
                    '<td>' + (c.cliente || '') + '</td>' +
                    '<td>' + (c.fecha || '') + '</td>' +
                    '<td class="text-right">$' + (typeof c.monto_total === 'number' ? c.monto_total.toFixed(2) : c.monto_total) + '</td>' +
                    '<td><button type="button" class="btn btn-primary btn-sm" onclick="elegirComplementoCfdiSustituir(\'' + (c.uuid || '').replace(/'/g, "\\'") + '\', \'' + (c.folio_completo || '').replace(/'/g, "\\'") + '\')">Seleccionar</button></td>';
                tbody.appendChild(tr);
            });
        })
        .catch(function() {
            document.getElementById('cargandoComplementoCfdiSustituir').style.display = 'none';
            document.getElementById('sinComplementosCfdiSustituir').style.display = 'block';
        });
}

function cerrarModalComplementoCfdiSustituir() {
    document.getElementById('modalSeleccionarComplementoCfdiSustituir').classList.remove('show');
}

function elegirComplementoCfdiSustituir(uuid, folio) {
    document.getElementById('inputComplementoUuidReferencia').value = uuid;
    document.getElementById('inputComplementoTipoRelacion').value = '04';
    document.getElementById('inputComplementoUuidReferenciaDisplay').value = folio ? folio + ' — ' + uuid : uuid;
    cerrarModalComplementoCfdiSustituir();
}

function limpiarComplementoCfdiSustituir() {
    document.getElementById('inputComplementoUuidReferencia').value = '';
    document.getElementById('inputComplementoUuidReferenciaDisplay').value = '';
    document.getElementById('inputComplementoTipoRelacion').value = '04';
}

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
        const response = await fetch(facturasPendientesUrl + '?cliente_id=' + encodeURIComponent(clienteId));
        const data = await response.json();
        if (data.complemento_borrador_id) {
            window.location.href = '{{ url("complementos") }}/' + data.complemento_borrador_id;
            return;
        }
        facturasPendientes = Array.isArray(data.facturas) ? data.facturas : (data.facturas ? [].concat(data.facturas) : []);

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
                    <div class="text-mono text-muted" style="font-size: 11px;">${(f.uuid || '').substring(0, 20)}${(f.uuid || '').length > 20 ? '...' : ''}</div>
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

        // Restaurar valores de old() tras error de validación (por factura_id, no por índice)
        if (window.oldFacturas && Array.isArray(window.oldFacturas)) {
            const oldByFacturaId = {};
            window.oldFacturas.forEach(function(f) {
                if (f && f.factura_id) oldByFacturaId[String(f.factura_id)] = parseFloat(f.monto_pagado) || 0;
            });
            listDiv.querySelectorAll('input[name*="[factura_id]"]').forEach(function(hidden) {
                const fid = hidden.value;
                const name = hidden.getAttribute('name');
                const idx = name.match(/facturas\[(\d+)\]/)[1];
                const montoInp = listDiv.querySelector('input[name="facturas[' + idx + '][monto_pagado]"]');
                if (montoInp && oldByFacturaId[fid] !== undefined) montoInp.value = oldByFacturaId[fid].toFixed(2);
            });
            if (window.oldMontoTotal != null && window.oldMontoTotal !== '')
                document.getElementById('monto_total').value = window.oldMontoTotal;
        }

        actualizarTotales();

        // Si llegamos desde Cuenta por Cobrar: preseleccionar factura y monto
        if (window.cuentaPreseleccionada && !window.oldFacturas) {
            const cp = window.cuentaPreseleccionada;
            listDiv.querySelectorAll('input[name*="[factura_id]"]').forEach(hidden => {
                if (parseInt(hidden.value, 10) === cp.factura_id) {
                    const name = hidden.getAttribute('name');
                    const idx = name.match(/facturas\[(\d+)\]/)[1];
                    const montoInp = listDiv.querySelector(`input[name="facturas[${idx}][monto_pagado]"]`);
                    if (montoInp) {
                        montoInp.value = parseFloat(cp.monto_pendiente).toFixed(2);
                        document.getElementById('monto_total').value = parseFloat(cp.monto_pendiente).toFixed(2);
                        actualizarTotales();
                    }
                }
            });
        }

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
}

function validarAntesDeEnviar() {
    const montoTotal = parseFloat(document.getElementById('monto_total').value) || 0;
    let totalAplicado = 0;
    document.querySelectorAll('.monto-pago').forEach(inp => {
        totalAplicado += parseFloat(inp.value) || 0;
    });
    const diferencia = Math.abs(montoTotal - totalAplicado);
    if (montoTotal === 0) {
        alert('Ingresa el monto total del pago.');
        return false;
    }
    if (!document.getElementById('cliente_id').value) {
        alert('Selecciona un cliente.');
        return false;
    }
    const listDiv = document.getElementById('facturasList');
    if (!listDiv || listDiv.style.display === 'none' || !listDiv.querySelectorAll('.monto-pago').length) {
        alert('Selecciona un cliente y espera a que se carguen las facturas pendientes.');
        return false;
    }
    if (diferencia >= 0.01) {
        alert('El monto aplicado a las facturas debe coincidir con el monto total del pago. Revisa que la suma de la columna "Pagar" sea igual al monto total.');
        return false;
    }
    const algunMonto = Array.from(listDiv.querySelectorAll('.monto-pago')).some(inp => (parseFloat(inp.value) || 0) > 0);
    if (!algunMonto) {
        alert('Indica al menos un monto a aplicar en alguna factura.');
        return false;
    }
    return true;
}

document.getElementById('formComplemento').addEventListener('submit', function(e) {
    if (!validarAntesDeEnviar()) {
        e.preventDefault();
    }
});

@if(old('facturas'))
window.oldFacturas = @json(old('facturas'));
window.oldMontoTotal = @json(old('monto_total'));
@endif
@if($cuentaPreseleccionada)
window.cuentaPreseleccionada = {
    factura_id: {{ $cuentaPreseleccionada->factura_id }},
    monto_pendiente: {{ number_format($cuentaPreseleccionada->saldo_pendiente_real, 2, '.', '') }}
};
@endif
document.addEventListener('DOMContentLoaded', function() {
    var clienteId = document.getElementById('cliente_id').value;
    if (clienteId) cargarFacturasPendientes();
});
</script>
@endpush