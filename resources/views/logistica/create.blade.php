@extends('layouts.app')
@section('title', 'Nuevo envío — Logística')
@section('page-title', '📦 Nuevo envío')
@section('page-subtitle', 'Factura timbrada o remisión enviada / entregada')

@php
$breadcrumbs = [
    ['title' => 'Logística', 'url' => route('logistica.index')],
    ['title' => 'Elegir documento', 'url' => route('logistica.elegir-origen')],
    ['title' => 'Registrar'],
];
@endphp

@section('page-actions')
    <a href="{{ route('logistica.elegir-origen') }}" class="btn btn-light">← Elegir desde listado</a>
@endsection

@section('content')

@if(!empty($motivoPrecargaInvalida))
<div class="card" style="margin-bottom:16px;border-color:var(--color-warning);">
    <div class="card-body text-muted" style="font-size:14px;">
        ⚠️ {{ $motivoPrecargaInvalida }} Puedes buscar el documento abajo o volver al <a href="{{ route('logistica.elegir-origen') }}">listado de orígenes</a>.
    </div>
</div>
@endif

<form action="{{ route('logistica.store') }}" method="POST" id="logisticaForm">
@csrf

<input type="hidden" name="cliente_id" id="cliente_id" value="{{ old('cliente_id') }}" required>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">🔗 Origen del envío</div></div>
            <div class="card-body">
                <div class="form-group" style="display:flex;gap:20px;flex-wrap:wrap;">
                    <label class="form-check" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="radio" name="origen" value="factura" {{ old('origen', 'factura') === 'factura' ? 'checked' : '' }} id="origen_factura">
                        <span>Factura timbrada</span>
                    </label>
                    <label class="form-check" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="radio" name="origen" value="remision" {{ old('origen') === 'remision' ? 'checked' : '' }} id="origen_remision">
                        <span>Remisión (enviada / entregada, sin envío duplicado)</span>
                    </label>
                </div>

                <div id="bloque_factura" class="form-group search-box" style="margin-top:12px;">
                    <label class="form-label">Buscar factura <span class="req">*</span></label>
                    <input type="text" id="buscarFactura" class="form-control" placeholder="Folio, UUID, receptor..." autocomplete="off">
                    <input type="hidden" name="factura_id" id="factura_id" value="{{ old('factura_id') }}">
                    <div id="facturaResults" class="autocomplete-results"></div>
                    <div id="facturaSeleccion" class="text-muted" style="margin-top:8px;font-size:13px;"></div>
                </div>

                <div id="bloque_remision" class="form-group search-box" style="margin-top:12px;display:none;">
                    <label class="form-label">Buscar remisión <span class="req">*</span></label>
                    <input type="text" id="buscarRemision" class="form-control" placeholder="Folio, cliente..." autocomplete="off">
                    <input type="hidden" name="remision_id" id="remision_id" value="{{ old('remision_id') }}">
                    <div id="remisionResults" class="autocomplete-results"></div>
                    <div id="remisionSeleccion" class="text-muted" style="margin-top:8px;font-size:13px;"></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📍🚚 Dirección de entrega</div></div>
            <div class="card-body">
                <select name="cliente_direccion_entrega_id" id="direccionEntregaSelect" class="form-control mb-2" disabled>
                    <option value="">— Sin dirección guardada / escribir abajo —</option>
                </select>
                <textarea name="direccion_entrega"
                          id="direccion_entrega"
                          class="form-control"
                          rows="3"
                          placeholder="Si el cliente tiene direcciones en su ficha, se pueden precargar; si no, escribe aquí la dirección de entrega.">{{ old('direccion_entrega') }}</textarea>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📦 Partidas a enviar</div></div>
            <div class="card-body" style="padding:0;">
                <p class="text-muted" style="padding:12px 16px;margin:0;font-size:13px;">Selecciona las líneas y la cantidad de este envío (permite envíos parciales). Las cantidades ya enviadas en otros envíos no cancelados se restan automáticamente.</p>
                <div class="table-container" style="border:none;margin:0;">
                    <table>
                        <thead>
                            <tr>
                                <th class="td-center" style="width:44px;"></th>
                                <th>Descripción</th>
                                <th class="td-right">Pedido</th>
                                <th class="td-right">Enviado</th>
                                <th class="td-right">Pendiente</th>
                                <th class="td-center">Cant. envío</th>
                            </tr>
                        </thead>
                        <tbody id="lineasBody">
                            <tr id="emptyRow">
                                <td colspan="6" class="text-center text-muted" style="padding:24px;">Selecciona factura o remisión para cargar partidas.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Notas</label>
                    <textarea name="notas" class="form-control" rows="2">{{ old('notas') }}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">ℹ️ Cliente</div></div>
            <div class="card-body">
                <p id="clienteResumen" class="text-muted" style="font-size:13px;">El cliente se toma automáticamente de la factura o remisión elegida.</p>
            </div>
        </div>
        <div class="card">
            <div class="card-body">
                <p class="text-muted" style="font-size:13px;">El estado inicial del envío será <strong>pendiente</strong>. Si marcas la remisión como enviada o entregada, el sistema también sincroniza un envío vinculado.</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="{{ route('logistica.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">✓ Registrar envío</button>
    </div>
</div>

</form>

@push('scripts')
<script>
const precargaOrigen = @json($precargaPayload ?? null);
let timerF, timerR;
let lineas = [];

document.addEventListener('DOMContentLoaded', () => {
    const of = document.getElementById('origen_factura');
    const or = document.getElementById('origen_remision');
    function refrescarOrigen() {
        const esFactura = of.checked;
        document.getElementById('bloque_factura').style.display = esFactura ? 'block' : 'none';
        document.getElementById('bloque_remision').style.display = esFactura ? 'none' : 'block';
        if (esFactura) {
            document.getElementById('remision_id').value = '';
        } else {
            document.getElementById('factura_id').value = '';
        }
        lineas = [];
        renderLineas();
    }
    of.addEventListener('change', refrescarOrigen);
    or.addEventListener('change', refrescarOrigen);

    document.getElementById('buscarFactura').addEventListener('input', function() {
        clearTimeout(timerF);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('facturaResults').classList.remove('show'); return; }
        timerF = setTimeout(() => buscarFacturas(q), 280);
    });
    document.getElementById('buscarRemision').addEventListener('input', function() {
        clearTimeout(timerR);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('remisionResults').classList.remove('show'); return; }
        timerR = setTimeout(() => buscarRemisiones(q), 280);
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box')) {
            document.getElementById('facturaResults')?.classList.remove('show');
            document.getElementById('remisionResults')?.classList.remove('show');
        }
    });

    document.getElementById('logisticaForm').addEventListener('submit', prepararEnvio);

    (async () => {
        if (precargaOrigen && precargaOrigen.factura) {
            of.checked = true;
            refrescarOrigen();
            await seleccionarFactura(precargaOrigen.factura.id, precargaOrigen.factura.label, precargaOrigen.factura.cliente_id);
        } else if (precargaOrigen && precargaOrigen.remision) {
            or.checked = true;
            refrescarOrigen();
            await seleccionarRemision(precargaOrigen.remision.id, precargaOrigen.remision.label, precargaOrigen.remision.cliente_id);
        } else {
            refrescarOrigen();
        }
    })();
});

function closeD(id) { document.getElementById(id)?.classList.remove('show'); }

async function buscarFacturas(q) {
    try {
        const r = await fetch(`{{ route('logistica.buscar-facturas') }}?q=` + encodeURIComponent(q));
        const data = await r.json();
        const box = document.getElementById('facturaResults');
        box.innerHTML = '';
        if (!data.length) {
            box.innerHTML = '<div class="autocomplete-item"><div class="text-muted">Sin resultados</div></div>';
        } else {
            data.forEach(it => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                const bloqueado = it.permite_envio_logistica === false;
                if (bloqueado) {
                    div.style.opacity = '0.65';
                    div.style.cursor = 'not-allowed';
                    const sub = it.bloqueo_envio_logistica === 'remision_entregada'
                        ? 'No aplica: factura desde remisión entregada (usar envío por remisión).'
                        : 'No aplica: envío activo sin condición de otro alta, o sin partidas pendientes por entregar en destino.';
                    div.innerHTML = '<div class="autocomplete-item-name">' + escapeHtml(it.label) + '</div><div class="text-muted" style="font-size:11px;margin-top:4px;">' + escapeHtml(sub) + '</div>';
                } else {
                    div.innerHTML = '<div class="autocomplete-item-name">' + escapeHtml(it.label) + '</div>';
                    div.onclick = () => seleccionarFactura(it.id, it.label, it.cliente_id);
                }
                box.appendChild(div);
            });
        }
        box.classList.add('show');
    } catch (e) { console.error(e); }
}

async function buscarRemisiones(q) {
    try {
        const r = await fetch(`{{ route('logistica.buscar-remisiones') }}?q=` + encodeURIComponent(q));
        const data = await r.json();
        const box = document.getElementById('remisionResults');
        box.innerHTML = '';
        if (!data.length) {
            box.innerHTML = '<div class="autocomplete-item"><div class="text-muted">Sin resultados</div></div>';
        } else {
            data.forEach(it => {
                const div = document.createElement('div');
                div.className = 'autocomplete-item';
                div.innerHTML = '<div class="autocomplete-item-name">' + escapeHtml(it.label) + '</div>';
                div.onclick = () => seleccionarRemision(it.id, it.label, it.cliente_id);
                box.appendChild(div);
            });
        }
        box.classList.add('show');
    } catch (e) { console.error(e); }
}

function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;');
}

async function seleccionarFactura(id, label, clienteId) {
    document.getElementById('factura_id').value = id;
    document.getElementById('buscarFactura').value = label;
    document.getElementById('facturaSeleccion').textContent = 'Factura seleccionada: ' + label;
    document.getElementById('cliente_id').value = clienteId;
    document.getElementById('clienteResumen').innerHTML = '<strong>Cliente ID</strong> ' + clienteId + ' (desde factura)';
    closeD('facturaResults');
    await cargarLineasFactura(id);
    await cargarDireccionesCliente(clienteId);
}

async function seleccionarRemision(id, label, clienteId) {
    document.getElementById('remision_id').value = id;
    document.getElementById('buscarRemision').value = label;
    document.getElementById('remisionSeleccion').textContent = 'Remisión seleccionada: ' + label;
    document.getElementById('cliente_id').value = clienteId;
    document.getElementById('clienteResumen').innerHTML = '<strong>Cliente ID</strong> ' + clienteId + ' (desde remisión)';
    closeD('remisionResults');
    await cargarLineasRemision(id);
    await cargarDireccionesCliente(clienteId);
}

async function cargarLineasFactura(id) {
    const r = await fetch("{{ url('/logistica/factura') }}/" + id + "/lineas");
    const data = await r.json();
    lineas = (data.lineas || []).map(l => ({
        tipo: 'factura',
        detalleId: l.factura_detalle_id,
        descripcion: l.descripcion,
        pedido: l.cantidad_facturada,
        enviado: l.cantidad_enviada,
        pendiente: l.cantidad_pendiente,
        cant: Math.min(l.cantidad_pendiente, l.cantidad_facturada) > 0 ? l.cantidad_pendiente : '',
        checked: l.cantidad_pendiente > 0,
    }));
    renderLineas();
}

async function cargarLineasRemision(id) {
    const r = await fetch("{{ url('/logistica/remision') }}/" + id + "/lineas");
    const data = await r.json();
    lineas = (data.lineas || []).map(l => ({
        tipo: 'remision',
        detalleId: l.remision_detalle_id,
        descripcion: l.descripcion,
        pedido: l.cantidad_remision,
        enviado: l.cantidad_enviada,
        pendiente: l.cantidad_pendiente,
        cant: l.cantidad_pendiente > 0 ? l.cantidad_pendiente : '',
        checked: l.cantidad_pendiente > 0,
    }));
    renderLineas();
}

function renderLineas() {
    const tbody = document.getElementById('lineasBody');
    if (!lineas.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Sin partidas o carga pendiente.</td></tr>';
        return;
    }
    tbody.innerHTML = lineas.map((l, i) => `
        <tr data-idx="${i}">
            <td class="td-center"><input type="checkbox" class="chk-linea" data-idx="${i}" ${l.checked ? 'checked' : ''} ${l.pendiente <= 0 ? 'disabled' : ''}></td>
            <td><div class="fw-600">${escapeHtml(l.descripcion)}</div></td>
            <td class="td-right text-mono">${fmt(l.pedido)}</td>
            <td class="td-right text-mono">${fmt(l.enviado)}</td>
            <td class="td-right text-mono">${fmt(l.pendiente)}</td>
            <td class="td-center">
                <input type="number" class="form-control cant-input" data-idx="${i}"
                    step="0.0001" min="0.0001" max="${l.pendiente}"
                    value="${l.cant === '' ? '' : l.cant}" style="width:110px;text-align:center;"
                    ${l.pendiente <= 0 ? 'disabled' : ''}>
            </td>
        </tr>
    `).join('');

    tbody.querySelectorAll('.chk-linea').forEach(cb => cb.addEventListener('change', e => {
        const i = +e.target.dataset.idx;
        lineas[i].checked = e.target.checked;
    }));
    tbody.querySelectorAll('.cant-input').forEach(inp => inp.addEventListener('input', e => {
        const i = +e.target.dataset.idx;
        lineas[i].cant = e.target.value;
    }));
}

function fmt(x) {
    const n = parseFloat(x);
    return Number.isFinite(n) ? n : '0';
}

async function cargarDireccionesCliente(clienteId) {
    const sel = document.getElementById('direccionEntregaSelect');
    const tx = document.getElementById('direccion_entrega');
    sel.onchange = null;
    sel.innerHTML = '<option value="">— Sin dirección guardada / escribir abajo —</option>';
    if (!clienteId) { sel.disabled = true; return; }
    try {
        const r = await fetch("{{ url('/logistica/cliente') }}/" + clienteId + "/direcciones-entrega");
        const data = await r.json();
        const dirs = Array.isArray(data) ? data : [];
        if (!dirs.length) { sel.disabled = true; return; }
        sel.disabled = false;
        dirs.forEach(d => {
            const o = document.createElement('option');
            o.value = d.id;
            o.textContent = d.sucursal_almacen || ('Dir. ' + d.id);
            o.dataset.dir = d.direccion_completa || '';
            sel.appendChild(o);
        });
        const first = dirs[0];
        sel.value = String(first.id);
        tx.value = first.direccion_completa || '';
        sel.onchange = () => {
            const opt = sel.options[sel.selectedIndex];
            tx.value = opt && opt.dataset.dir ? opt.dataset.dir : '';
        };
    } catch (e) { console.error(e); sel.disabled = true; }
}

function prepararEnvio(e) {
    document.querySelectorAll('input[data-dynamic-item]').forEach(el => el.remove());

    const esFactura = document.getElementById('origen_factura').checked;
    if (!document.getElementById('cliente_id').value) {
        e.preventDefault(); alert('Falta cliente: elige factura o remisión.'); return;
    }
    if (esFactura && !document.getElementById('factura_id').value) {
        e.preventDefault(); alert('Selecciona una factura.'); return;
    }
    if (!esFactura && !document.getElementById('remision_id').value) {
        e.preventDefault(); alert('Selecciona una remisión.'); return;
    }

    const form = document.getElementById('logisticaForm');
    let n = 0;
    for (let i = 0; i < lineas.length; i++) {
        const l = lineas[i];
        const tr = document.querySelector(`tr[data-idx="${i}"]`);
        const chk = tr?.querySelector('.chk-linea');
        if (!chk || !chk.checked) continue;
        const inp = tr.querySelector('.cant-input');
        const cant = parseFloat(inp?.value);
        if (!Number.isFinite(cant) || cant <= 0) continue;
        if (cant - l.pendiente > 1e-6) {
            e.preventDefault();
            alert('Cantidad mayor al pendiente en fila ' + (i + 1));
            return;
        }
        const fid = document.createElement('input');
        fid.type = 'hidden';
        fid.name = `items[${n}][${l.tipo === 'factura' ? 'factura_detalle_id' : 'remision_detalle_id'}]`;
        fid.value = l.detalleId;
        fid.setAttribute('data-dynamic-item', '1');
        form.appendChild(fid);
        const fc = document.createElement('input');
        fc.type = 'hidden';
        fc.name = `items[${n}][cantidad]`;
        fc.value = String(cant);
        fc.setAttribute('data-dynamic-item', '1');
        form.appendChild(fc);
        n++;
    }
    if (n === 0) {
        e.preventDefault();
        alert('Selecciona al menos una partida con cantidad.');
    }
}
</script>
@endpush

@endsection
