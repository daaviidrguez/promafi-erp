@extends('layouts.app')
@section('title', 'Entrada anticipada desde cotización')
@section('page-title', '📥 Entradas anticipadas desde cotización')
@section('page-subtitle', $cotizacion->folio.' · '.($cotizacion->cliente_nombre ?? $cotizacion->cliente?->nombre ?? 'Cliente'))

@php
$breadcrumbs = [
    ['title' => 'Cotizaciones', 'url' => route('cotizaciones.index')],
    ['title' => $cotizacion->folio, 'url' => route('cotizaciones.show', $cotizacion->id)],
    ['title' => 'Entradas anticipadas'],
];
$puedeCrearProducto = auth()->user()?->can('productos.crear');
@endphp

@section('content')

@if(session('error'))
<div class="alert alert-error" id="flash-alert">
    <span>✗</span>
    <div>{{ session('error') }}</div>
</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <div id="pasoBadge1" class="badge badge-info" style="font-size:13px;padding:8px 12px;">1. Resolver productos</div>
        <span class="text-muted">→</span>
        <div id="pasoBadge2" class="badge" style="font-size:13px;padding:8px 12px;background:var(--color-gray-100);color:var(--color-gray-600);">2. Agrupar por proveedor</div>
        <p class="text-muted small mb-0" style="margin-left:auto;max-width:480px;">
            Una cotización puede generar <strong>varias entradas</strong> (una por proveedor).
            Asigne proveedor y cantidad por partida; cree cada grupo por separado.
        </p>
    </div>
</div>

<div id="alertWizard" class="alert alert-success" style="display:none;margin-bottom:16px;">
    <span>✓</span>
    <div id="alertWizardMsg"></div>
</div>

{{-- Entradas ya creadas --}}
<div id="cardEntradasPrevias" class="card" style="margin-bottom:16px;{{ empty($entradasPrevias) ? 'display:none;' : '' }}">
    <div class="card-header">
        <div class="card-title">Entradas ya creadas desde esta cotización</div>
    </div>
    <div class="card-body" id="listaEntradasPrevias" style="display:flex;flex-wrap:wrap;gap:8px;"></div>
</div>

{{-- Paso 1 --}}
<div id="paso1">
    <div class="card">
        <div class="card-header">
            <div class="card-title">📦 Resolver productos del catálogo</div>
            <span class="text-muted small" id="paso1Resumen"></span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-container" style="border:none;margin:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Partida cotizada</th>
                            <th>Producto catálogo</th>
                            <th class="td-center">Cant.</th>
                            <th class="td-center">Pendiente EA</th>
                            <th style="width:200px;">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="paso1Body"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap;">
        <a href="{{ route('cotizaciones.show', $cotizacion->id) }}" class="btn btn-light">← Volver a cotización</a>
        <button type="button" class="btn btn-primary" id="btnIrPaso2" onclick="irPaso2()" disabled>
            Continuar a agrupar por proveedor →
        </button>
    </div>
    <p id="paso1Aviso" class="text-muted small" style="margin-top:8px;text-align:right;"></p>
</div>

{{-- Paso 2 --}}
<div id="paso2" style="display:none;">
    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <div class="card-title">📋 Datos comunes de recepción</div>
        </div>
        <div class="card-body" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Fecha de recepción <span class="req">*</span></label>
                <input type="date" id="fechaRecepcion" value="{{ date('Y-m-d') }}" class="form-control">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Observaciones (opcionales)</label>
                <input type="text" id="observacionesEa" class="form-control" placeholder="Se agregará referencia a {{ $cotizacion->folio }}">
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
            <div class="card-title">🏭 Asignar proveedor a partidas pendientes</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                <div class="search-box" style="min-width:220px;position:relative;">
                    <input type="text" id="buscarProveedorBulk" placeholder="Proveedor para selección…" autocomplete="off" class="form-control form-control-sm">
                    <input type="hidden" id="proveedorBulkId">
                    <div id="proveedorBulkResults" class="autocomplete-results"></div>
                </div>
                <button type="button" class="btn btn-outline btn-sm" onclick="aplicarProveedorBulk()">Asignar a seleccionadas</button>
            </div>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="alert alert-warning" style="margin:16px;margin-bottom:0;">
                <span>⚠</span>
                <div>
                    El <strong>costo s/IVA</strong> es de compra (no el precio de venta).
                    Puede partir la cantidad pendiente entre varios proveedores creando varias entradas.
                </div>
            </div>
            <div class="table-container" style="border:none;margin:0;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:40px;"><input type="checkbox" id="chkTodasPendientes" title="Seleccionar todas" onchange="toggleTodasPendientes(this.checked)"></th>
                            <th>Producto</th>
                            <th class="td-center">Pend.</th>
                            <th class="td-center">Cant. esta EA</th>
                            <th class="td-right">Costo s/IVA</th>
                            <th>Proveedor</th>
                        </tr>
                    </thead>
                    <tbody id="paso2AsignacionBody"></tbody>
                </table>
            </div>
        </div>
    </div>

    <div id="gruposContainer"></div>

    <div id="paso2Vacio" class="card" style="display:none;">
        <div class="card-body text-center" style="padding:32px;">
            <p class="fw-600" style="margin-bottom:8px;">No hay cantidad pendiente por recibir</p>
            <p class="text-muted" style="margin-bottom:16px;">Todas las partidas con producto ya están cubiertas por entradas anticipadas (o faltan productos en el paso 1).</p>
            <a href="{{ route('cotizaciones.show', $cotizacion->id) }}" class="btn btn-primary">Volver a la cotización</a>
        </div>
    </div>

    <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:16px;flex-wrap:wrap;">
        <button type="button" class="btn btn-light" onclick="irPaso1()">← Volver a productos</button>
        <a href="{{ route('cotizaciones.show', $cotizacion->id) }}" class="btn btn-outline">Listo / Volver a cotización</a>
    </div>
</div>

{{-- Modal buscar/asignar producto --}}
<div id="modalAsignarProductoWizard" class="modal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title">Asignar producto</div>
            <button type="button" class="modal-close" onclick="cerrarModalAsignar()" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted small" id="modalAsignarDesc" style="margin-bottom:10px;"></p>
            <div class="form-group">
                <input type="text" id="modalBuscarProducto" placeholder="Buscar por código o nombre..." class="form-control" autocomplete="off">
            </div>
            <div id="modalProductoLista" class="table-container" style="max-height:280px;overflow-y:auto;">
                <p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>
            </div>
        </div>
        @if($puedeCrearProducto)
        <div class="modal-footer" style="flex-wrap:wrap; gap:8px; justify-content:space-between;">
            <button type="button" class="btn btn-outline" onclick="abrirModalCrearRapido()">¿Deseas crear el producto?</button>
            <button type="button" class="btn btn-light" onclick="cerrarModalAsignar()">Cerrar</button>
        </div>
        @endif
    </div>
</div>

@if($puedeCrearProducto)
<div id="modalCrearProductoRapido" class="modal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title">Creación rápida de producto</div>
            <button type="button" class="modal-close" onclick="cerrarModalCrearRapido()" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom:12px;">
                Producto con código <span class="text-mono">PSI-…</span> y validación de similitud.
                La Clave Prod./Serv. es opcional: si no la conoce, se usa <span class="text-mono">01010101</span> provisional.
            </p>
            <div class="form-group">
                <label class="form-label">Código</label>
                <input type="text" class="form-control text-mono" value="Automático (PSI-…)" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre</label>
                <input type="text" id="crearRapidoNombre" class="form-control" readonly>
            </div>
            <div class="form-group search-box" style="margin-bottom:0;">
                <label class="form-label">Clave Prod./Serv. <span class="text-muted fw-400">(opcional)</span></label>
                <input type="hidden" id="crearRapidoClaveSat" value="01010101">
                <input type="text" id="crearRapidoClaveSatInput" class="form-control text-mono"
                       value="01010101"
                       placeholder="Buscar clave SAT o dejar 01010101…"
                       autocomplete="off">
                <div id="crearRapidoClaveSatResults" class="autocomplete-results"></div>
                <p class="text-muted small mb-0" style="margin-top:6px;">Escriba 2+ caracteres para buscar en el catálogo SAT.</p>
            </div>
            <p id="crearRapidoAviso" class="text-muted small mt-2 mb-0" style="display:none;"></p>
        </div>
        <div class="modal-footer" style="flex-wrap:wrap; gap:8px; justify-content:flex-end;">
            <button type="button" class="btn btn-light" onclick="cerrarModalCrearRapido()">Cancelar</button>
            <button type="button" class="btn btn-warning" id="btnCrearRapidoForzar" style="display:none;" onclick="guardarProductoRapido(true)">Crear de todas formas</button>
            <button type="button" class="btn btn-primary" id="btnCrearRapidoGuardar" onclick="guardarProductoRapido(false)">Guardar y asignar</button>
        </div>
    </div>
</div>
@endif

{{-- Modal proveedor por línea --}}
<div id="modalProveedorLinea" class="modal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar proveedor</div>
            <button type="button" class="modal-close" onclick="cerrarModalProveedorLinea()" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted small" id="modalProvLineaDesc" style="margin-bottom:10px;"></p>
            <div class="form-group search-box" style="margin:0;">
                <input type="text" id="buscarProveedorLinea" placeholder="Buscar proveedor..." autocomplete="off" class="form-control">
                <div id="proveedorLineaResults" class="autocomplete-results"></div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="limpiarProveedorLineaActual()">Quitar proveedor</button>
            <button type="button" class="btn btn-outline" onclick="cerrarModalProveedorLinea()">Cerrar</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    let lineas = @json($lineas);
    let entradasPrevias = @json($entradasPrevias ?? []);
    const listarUrl = @json(route('cotizaciones.buscar-productos'));
    const asignarUrlTpl = @json(route('cotizaciones.detalles.asignar-producto', ['cotizacion' => $cotizacion->id, 'detalle' => '__DETALLE__']));
    const crearRapidoUrlTpl = @json(route('cotizaciones.detalles.crear-producto-rapido', ['cotizacion' => $cotizacion->id, 'detalle' => '__DETALLE__']));
    const storeGrupoUrl = @json(route('cotizaciones.store-entrada-anticipada', $cotizacion->id));
    const buscarProveedoresUrl = @json(route('entradas-anticipadas.buscar-proveedores'));
    const buscarClaveSatUrl = @json(route('productos.buscar-clave-sat'));
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const puedeCrearProducto = @json((bool) $puedeCrearProducto);
    let detalleActualIdx = null;
    let proveedorLineaIdx = null;
    let seleccionPaso2 = {};
    let timerBuscar = null;
    let timerProvBulk = null;
    let timerProvLinea = null;
    let timerClaveSat = null;
    let creandoGrupoKey = null;

    function resetClaveSatRapida() {
        const hid = document.getElementById('crearRapidoClaveSat');
        const inp = document.getElementById('crearRapidoClaveSatInput');
        const box = document.getElementById('crearRapidoClaveSatResults');
        if (hid) hid.value = '01010101';
        if (inp) inp.value = '01010101';
        if (box) box.classList.remove('show');
    }

    function claveSatDesdeInputs() {
        const hid = document.getElementById('crearRapidoClaveSat');
        const inp = document.getElementById('crearRapidoClaveSatInput');
        const fromHid = (hid && hid.value) ? String(hid.value).trim() : '';
        if (/^\d{8}$/.test(fromHid)) return fromHid;
        let raw = (inp && inp.value) ? String(inp.value).trim() : '';
        if (raw.indexOf(' - ') !== -1) raw = raw.split(' - ')[0].trim();
        return raw.replace(/\D+/g, '');
    }

    function esc(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function etiquetaIva(tasa) {
        if (tasa == null || tasa === '') return 'Exento';
        return (parseFloat(tasa) * 100).toFixed(0) + '%';
    }

    function calcLinea(cant, precio, tasa) {
        const c = parseFloat(cant) || 0;
        const p = parseFloat(precio) || 0;
        const sub = Math.round(c * p * 100) / 100;
        const iva = (tasa != null && tasa !== '') ? Math.round(sub * parseFloat(tasa) * 100) / 100 : 0;
        return { sub, iva, total: Math.round((sub + iva) * 100) / 100 };
    }

    function mostrarAlerta(msg, tipo) {
        const box = document.getElementById('alertWizard');
        const txt = document.getElementById('alertWizardMsg');
        if (!box || !txt) return;
        box.className = 'alert alert-' + (tipo === 'error' ? 'error' : 'success');
        box.style.display = 'flex';
        txt.textContent = msg;
        box.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function renderEntradasPrevias() {
        const card = document.getElementById('cardEntradasPrevias');
        const lista = document.getElementById('listaEntradasPrevias');
        if (!card || !lista) return;
        if (!entradasPrevias.length) {
            card.style.display = 'none';
            lista.innerHTML = '';
            return;
        }
        card.style.display = '';
        lista.innerHTML = entradasPrevias.map(function (e) {
            return '<a href="' + esc(e.url) + '" class="btn btn-outline btn-sm" target="_blank">'
                + esc(e.folio) + ' · ' + esc(e.proveedor || 'Sin proveedor')
                + ' <span class="badge" style="margin-left:6px;">' + esc(e.estado_etiqueta) + '</span></a>';
        }).join('');
    }

    function lineasConProductoPendiente() {
        return lineas.filter(l => l.tiene_producto && (parseFloat(l.pendiente) || 0) > 0.001);
    }

    function actualizarResumenPaso1() {
        const total = lineas.length;
        const conProd = lineas.filter(l => l.tiene_producto).length;
        const pend = lineasConProductoPendiente().length;
        const resumen = document.getElementById('paso1Resumen');
        if (resumen) resumen.textContent = conProd + '/' + total + ' con producto · ' + pend + ' con pendiente';
        const btn = document.getElementById('btnIrPaso2');
        const aviso = document.getElementById('paso1Aviso');
        const sinProducto = lineas.some(l => !l.tiene_producto);
        const puede = !sinProducto && (pend > 0 || entradasPrevias.length > 0);
        if (btn) btn.disabled = sinProducto;
        if (aviso) {
            if (sinProducto) {
                aviso.textContent = 'Vincule o cree producto en todas las partidas para continuar.';
            } else if (pend === 0) {
                aviso.textContent = 'Todas las cantidades ya están en entradas anticipadas. Puede revisarlas arriba o volver.';
            } else {
                aviso.textContent = 'Listo para agrupar ' + pend + ' partida(s) pendiente(s) por proveedor.';
            }
        }
        // Permitir ir a paso 2 si hay pendientes, o si todo está cubierto (para ver estado).
        if (btn) btn.disabled = sinProducto;
        if (btn && !sinProducto) btn.disabled = false;
    }

    function aplicarProductoALinea(idx, producto) {
        if (!producto || !lineas[idx]) return;
        lineas[idx].producto_id = producto.id;
        lineas[idx].codigo = producto.codigo;
        lineas[idx].tiene_producto = true;
        lineas[idx].precio_unitario_estimado = parseFloat(producto.costo) || 0;
        lineas[idx].costo_catalogo = parseFloat(producto.costo) || 0;
        if (producto.tasa_iva !== undefined) lineas[idx].tasa_iva = producto.tasa_iva;
        if (lineas[idx].cantidad_grupo == null || lineas[idx].cantidad_grupo === '') {
            lineas[idx].cantidad_grupo = lineas[idx].pendiente;
        }
        renderPaso1();
    }

    window.renderPaso1 = function () {
        const tbody = document.getElementById('paso1Body');
        tbody.innerHTML = lineas.map((l, i) => {
            const estado = l.tiene_producto
                ? `<div class="fw-600 text-mono">${esc(l.codigo)}</div><div class="text-muted small">Vinculado</div>`
                : `<span class="badge badge-warning">Sin producto</span>`;
            const acciones = l.tiene_producto
                ? `<button type="button" class="btn btn-outline btn-sm" onclick="abrirModalAsignar(${i})">🔍 Cambiar</button>`
                : `<button type="button" class="btn btn-primary btn-sm" onclick="abrirModalAsignar(${i})">🔍 Buscar</button>`;
            const pendBadge = (parseFloat(l.pendiente) || 0) <= 0.001 && l.tiene_producto
                ? '<span class="badge badge-success">Cubierta</span>'
                : `<span class="text-mono">${(parseFloat(l.pendiente)||0).toFixed(2)}</span>`;
            return `<tr>
                <td>
                    <div class="fw-600">${esc(l.descripcion)}</div>
                    <div class="text-muted small">${esc(l.unidad)} · venta $${(parseFloat(l.precio_venta)||0).toFixed(2)}</div>
                </td>
                <td>${estado}</td>
                <td class="td-center text-mono">${(parseFloat(l.cantidad)||0).toFixed(2)}</td>
                <td class="td-center">${pendBadge}</td>
                <td>${acciones}</td>
            </tr>`;
        }).join('') || '<tr><td colspan="5" class="text-center text-muted" style="padding:24px;">Sin partidas</td></tr>';
        actualizarResumenPaso1();
    };

    window.abrirModalAsignar = function (idx) {
        detalleActualIdx = idx;
        document.getElementById('modalAsignarDesc').textContent = lineas[idx].descripcion || '';
        document.getElementById('modalBuscarProducto').value = '';
        document.getElementById('modalProductoLista').innerHTML = '<p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>';
        document.getElementById('modalAsignarProductoWizard').classList.add('show');
        document.getElementById('modalBuscarProducto').focus();
    };
    window.cerrarModalAsignar = function () {
        document.getElementById('modalAsignarProductoWizard').classList.remove('show');
        detalleActualIdx = null;
    };
    window.abrirModalCrearRapido = function () {
        if (detalleActualIdx == null || !puedeCrearProducto) return;
        document.getElementById('crearRapidoNombre').value = lineas[detalleActualIdx].descripcion || '';
        const aviso = document.getElementById('crearRapidoAviso');
        const btnForzar = document.getElementById('btnCrearRapidoForzar');
        if (aviso) { aviso.style.display = 'none'; aviso.textContent = ''; }
        if (btnForzar) btnForzar.style.display = 'none';
        resetClaveSatRapida();
        document.getElementById('modalAsignarProductoWizard').classList.remove('show');
        document.getElementById('modalCrearProductoRapido').classList.add('show');
    };
    window.cerrarModalCrearRapido = function () {
        const modal = document.getElementById('modalCrearProductoRapido');
        if (modal) modal.classList.remove('show');
        resetClaveSatRapida();
        if (detalleActualIdx != null) document.getElementById('modalAsignarProductoWizard').classList.add('show');
    };

    window.guardarProductoRapido = function (forzar) {
        if (detalleActualIdx == null) return;
        const l = lineas[detalleActualIdx];
        const btnGuardar = document.getElementById('btnCrearRapidoGuardar');
        const btnForzar = document.getElementById('btnCrearRapidoForzar');
        const aviso = document.getElementById('crearRapidoAviso');
        const claveSat = claveSatDesdeInputs();
        if (claveSat !== '' && !/^\d{8}$/.test(claveSat)) {
            if (aviso) {
                aviso.style.display = 'block';
                aviso.textContent = 'La Clave Prod./Serv. debe tener 8 dígitos (o deje 01010101).';
            }
            return;
        }
        if (btnGuardar) btnGuardar.disabled = true;
        if (btnForzar) btnForzar.disabled = true;
        fetch(crearRapidoUrlTpl.replace('__DETALLE__', String(l.detalle_id)), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ forzar: !!forzar, clave_sat: claveSat || '01010101' })
        })
            .then(async function (r) {
                const resp = await r.json().catch(() => null);
                if (r.ok && resp && resp.success) {
                    aplicarProductoALinea(detalleActualIdx, resp.producto || { id: resp.producto_id, codigo: resp.codigo, costo: 0, tasa_iva: l.tasa_iva });
                    document.getElementById('modalCrearProductoRapido').classList.remove('show');
                    detalleActualIdx = null;
                    resetClaveSatRapida();
                    return;
                }
                if (resp && resp.needs_confirm) {
                    if (aviso) { aviso.style.display = 'block'; aviso.textContent = resp.message || 'Ya existe un producto similar.'; }
                    if (btnForzar) btnForzar.style.display = '';
                    return;
                }
                if (aviso) { aviso.style.display = 'block'; aviso.textContent = (resp && resp.message) || 'No se pudo crear el producto.'; }
            })
            .catch(() => { if (aviso) { aviso.style.display = 'block'; aviso.textContent = 'No se pudo crear el producto.'; } })
            .finally(() => {
                if (btnGuardar) btnGuardar.disabled = false;
                if (btnForzar) btnForzar.disabled = false;
            });
    };

    (function initClaveSatRapidaWizard() {
        const inp = document.getElementById('crearRapidoClaveSatInput');
        const hid = document.getElementById('crearRapidoClaveSat');
        const box = document.getElementById('crearRapidoClaveSatResults');
        if (!inp || !hid || !box) return;

        function buscarClaveSat(q) {
            fetch(buscarClaveSatUrl + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(function (data) {
                    if (!data.length) {
                        box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
                    } else {
                        box.innerHTML = data.map(function (item) {
                            const clave = String(item.clave || '').replace(/"/g, '&quot;');
                            const desc = String(item.descripcion || '').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            const etiqueta = clave + ' - ' + desc;
                            return '<div class="autocomplete-item" data-clave="' + clave + '" data-etiqueta="' + etiqueta.replace(/"/g, '&quot;') + '">' +
                                '<div class="autocomplete-item-name">' + clave + ' - ' + desc + '</div></div>';
                        }).join('');
                        box.querySelectorAll('.autocomplete-item[data-clave]').forEach(function (el) {
                            el.addEventListener('click', function () {
                                hid.value = this.getAttribute('data-clave') || '';
                                inp.value = this.getAttribute('data-etiqueta') || hid.value;
                                box.classList.remove('show');
                            });
                        });
                    }
                    box.classList.add('show');
                })
                .catch(function () {});
        }

        inp.addEventListener('input', function () {
            clearTimeout(timerClaveSat);
            const raw = this.value.trim();
            const q = raw.indexOf(' - ') !== -1 ? raw.split(' - ')[0].trim() : raw;
            const digits = q.replace(/\D+/g, '');
            if (/^\d{8}$/.test(digits)) hid.value = digits;
            else if (raw === '') hid.value = '';
            if (q.length < 2) { box.classList.remove('show'); return; }
            timerClaveSat = setTimeout(function () { buscarClaveSat(q); }, 280);
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.search-box')) box.classList.remove('show');
        });
    })();

    document.getElementById('modalBuscarProducto').addEventListener('input', function () {
        clearTimeout(timerBuscar);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('modalProductoLista').innerHTML = '<p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>';
            return;
        }
        timerBuscar = setTimeout(function () {
            fetch(listarUrl + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(function (list) {
                    const productos = (list || []).filter(x => x.tipo === 'producto');
                    const div = document.getElementById('modalProductoLista');
                    if (!productos.length) {
                        div.innerHTML = '<p class="text-muted text-center py-3">Sin resultados.</p>';
                        return;
                    }
                    div.innerHTML = '<table><thead><tr><th>Código</th><th>Nombre</th><th></th></tr></thead><tbody>' +
                        productos.map(p => '<tr><td class="text-mono">' + esc(p.codigo) + '</td><td>' + esc(p.nombre) + '</td>' +
                            '<td><button type="button" class="btn btn-primary btn-sm" data-id="' + p.id + '">Asignar</button></td></tr>').join('') +
                        '</tbody></table>';
                    div.querySelectorAll('button[data-id]').forEach(function (btn) {
                        btn.addEventListener('click', function () {
                            if (detalleActualIdx == null) return;
                            const l = lineas[detalleActualIdx];
                            btn.disabled = true;
                            fetch(asignarUrlTpl.replace('__DETALLE__', String(l.detalle_id)), {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ producto_id: btn.getAttribute('data-id') })
                            })
                                .then(r => r.json())
                                .then(function (resp) {
                                    if (!resp || !resp.success) {
                                        alert((resp && resp.message) || 'No se pudo asignar.');
                                        return;
                                    }
                                    aplicarProductoALinea(detalleActualIdx, resp.producto || { id: btn.getAttribute('data-id'), codigo: '', costo: 0 });
                                    cerrarModalAsignar();
                                })
                                .catch(() => alert('No se pudo asignar.'))
                                .finally(() => { btn.disabled = false; });
                        });
                    });
                })
                .catch(() => {
                    document.getElementById('modalProductoLista').innerHTML = '<p class="text-danger text-center py-3">Error al buscar.</p>';
                });
        }, 280);
    });

    // —— Paso 2 multi-proveedor ——
    function syncCantidadGrupoDefaults() {
        lineas.forEach(function (l) {
            const pend = parseFloat(l.pendiente) || 0;
            if (!l.tiene_producto || pend <= 0) {
                l.proveedor_id = null;
                l.proveedor_etiqueta = null;
                return;
            }
            const cg = parseFloat(l.cantidad_grupo);
            if (isNaN(cg) || cg <= 0 || cg > pend) {
                l.cantidad_grupo = pend;
            }
        });
    }

    function renderPaso2Asignacion() {
        syncCantidadGrupoDefaults();
        const tbody = document.getElementById('paso2AsignacionBody');
        const pendientes = lineasConProductoPendiente();
        const vacio = document.getElementById('paso2Vacio');
        if (!pendientes.length) {
            tbody.innerHTML = '';
            document.getElementById('gruposContainer').innerHTML = '';
            if (vacio) vacio.style.display = '';
            return;
        }
        if (vacio) vacio.style.display = 'none';

        tbody.innerHTML = lineas.map((l, i) => {
            if (!l.tiene_producto || (parseFloat(l.pendiente) || 0) <= 0.001) return '';
            const checked = seleccionPaso2[l.detalle_id] ? 'checked' : '';
            const provBtn = l.proveedor_id
                ? `<button type="button" class="btn btn-outline btn-sm" onclick="abrirModalProveedorLinea(${i})">${esc(l.proveedor_etiqueta)}</button>
                   <button type="button" class="btn btn-light btn-sm" onclick="limpiarProveedorIdx(${i})" title="Quitar">✕</button>`
                : `<button type="button" class="btn btn-primary btn-sm" onclick="abrirModalProveedorLinea(${i})">Elegir proveedor</button>`;
            return `<tr>
                <td class="td-center"><input type="checkbox" ${checked} onchange="toggleSeleccion(${l.detalle_id}, this.checked)"></td>
                <td>
                    <div class="fw-600">${esc(l.descripcion)}</div>
                    <div class="text-mono text-muted" style="font-size:12px;">${esc(l.codigo)} · IVA ${etiquetaIva(l.tasa_iva)}</div>
                </td>
                <td class="td-center text-mono">${(parseFloat(l.pendiente)||0).toFixed(2)}</td>
                <td class="td-center">
                    <input type="number" min="0.01" max="${l.pendiente}" step="0.01" value="${l.cantidad_grupo}"
                        class="form-control" style="width:90px;margin:0 auto;"
                        onchange="updCampo(${i},'cantidad_grupo',this.value)">
                </td>
                <td class="td-right">
                    <input type="number" min="0" step="0.01" value="${l.precio_unitario_estimado}"
                        class="form-control" style="width:110px;margin-left:auto;"
                        onchange="updCampo(${i},'precio_unitario_estimado',this.value)">
                    <div class="text-muted" style="font-size:11px;">Cat. $${(parseFloat(l.costo_catalogo)||0).toFixed(2)}</div>
                </td>
                <td style="white-space:nowrap;">${provBtn}</td>
            </tr>`;
        }).join('');
        renderGrupos();
    }

    window.toggleSeleccion = function (detalleId, checked) {
        seleccionPaso2[detalleId] = !!checked;
    };
    window.toggleTodasPendientes = function (checked) {
        lineasConProductoPendiente().forEach(l => { seleccionPaso2[l.detalle_id] = !!checked; });
        renderPaso2Asignacion();
    };
    window.updCampo = function (i, field, value) {
        if (field === 'cantidad_grupo') {
            const pend = parseFloat(lineas[i].pendiente) || 0;
            let v = parseFloat(value) || 0;
            if (v > pend) v = pend;
            if (v < 0) v = 0;
            lineas[i].cantidad_grupo = v;
        } else {
            lineas[i][field] = value;
        }
        renderGrupos();
    };

    window.abrirModalProveedorLinea = function (idx) {
        proveedorLineaIdx = idx;
        document.getElementById('modalProvLineaDesc').textContent = lineas[idx].descripcion || '';
        document.getElementById('buscarProveedorLinea').value = '';
        document.getElementById('proveedorLineaResults').innerHTML = '';
        document.getElementById('proveedorLineaResults').classList.remove('show');
        document.getElementById('modalProveedorLinea').classList.add('show');
        document.getElementById('buscarProveedorLinea').focus();
    };
    window.cerrarModalProveedorLinea = function () {
        document.getElementById('modalProveedorLinea').classList.remove('show');
        proveedorLineaIdx = null;
    };
    window.limpiarProveedorLineaActual = function () {
        if (proveedorLineaIdx == null) return;
        limpiarProveedorIdx(proveedorLineaIdx);
        cerrarModalProveedorLinea();
    };
    window.limpiarProveedorIdx = function (idx) {
        lineas[idx].proveedor_id = null;
        lineas[idx].proveedor_etiqueta = null;
        renderPaso2Asignacion();
    };
    window.selProvLinea = function (id, etiqueta) {
        if (proveedorLineaIdx == null) return;
        lineas[proveedorLineaIdx].proveedor_id = id;
        lineas[proveedorLineaIdx].proveedor_etiqueta = etiqueta;
        cerrarModalProveedorLinea();
        renderPaso2Asignacion();
    };

    async function buscarProveedoresEn(q, boxId, onPick) {
        const r = await fetch(buscarProveedoresUrl + '?q=' + encodeURIComponent(q));
        const data = await r.json();
        const box = document.getElementById(boxId);
        box.innerHTML = (data || []).map(p =>
            `<div class="autocomplete-item" data-id="${p.id}" data-etq="${esc(p.etiqueta)}">
                <div class="autocomplete-item-name">${esc(p.etiqueta)}</div>
            </div>`
        ).join('') || '<div class="autocomplete-item text-muted">Sin resultados</div>';
        box.classList.add('show');
        box.querySelectorAll('.autocomplete-item[data-id]').forEach(el => {
            el.addEventListener('click', function () {
                onPick(parseInt(this.getAttribute('data-id'), 10), this.getAttribute('data-etq'));
                box.classList.remove('show');
            });
        });
    }

    document.getElementById('buscarProveedorLinea').addEventListener('input', function () {
        clearTimeout(timerProvLinea);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('proveedorLineaResults').classList.remove('show');
            return;
        }
        timerProvLinea = setTimeout(() => {
            buscarProveedoresEn(q, 'proveedorLineaResults', function (id, etq) {
                // data-etq may be HTML-escaped; decode roughly
                const tmp = document.createElement('textarea');
                tmp.innerHTML = etq;
                selProvLinea(id, tmp.value);
            });
        }, 250);
    });

    document.getElementById('buscarProveedorBulk').addEventListener('input', function () {
        clearTimeout(timerProvBulk);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('proveedorBulkResults').classList.remove('show');
            return;
        }
        timerProvBulk = setTimeout(() => {
            buscarProveedoresEn(q, 'proveedorBulkResults', function (id, etq) {
                const tmp = document.createElement('textarea');
                tmp.innerHTML = etq;
                document.getElementById('proveedorBulkId').value = id;
                document.getElementById('buscarProveedorBulk').value = tmp.value;
            });
        }, 250);
    });

    window.aplicarProveedorBulk = function () {
        const id = parseInt(document.getElementById('proveedorBulkId').value, 10);
        const etq = document.getElementById('buscarProveedorBulk').value;
        if (!id) {
            alert('Seleccione un proveedor de la lista.');
            return;
        }
        const ids = Object.keys(seleccionPaso2).filter(k => seleccionPaso2[k]);
        if (!ids.length) {
            alert('Marque al menos una partida pendiente.');
            return;
        }
        lineas.forEach(l => {
            if (seleccionPaso2[l.detalle_id] && l.tiene_producto && (parseFloat(l.pendiente) || 0) > 0) {
                l.proveedor_id = id;
                l.proveedor_etiqueta = etq;
            }
        });
        renderPaso2Asignacion();
    };

    function gruposPorProveedor() {
        const map = {};
        lineas.forEach((l, idx) => {
            if (!l.tiene_producto || !l.proveedor_id) return;
            const pend = parseFloat(l.pendiente) || 0;
            const cant = parseFloat(l.cantidad_grupo) || 0;
            if (pend <= 0 || cant <= 0) return;
            const key = String(l.proveedor_id);
            if (!map[key]) {
                map[key] = {
                    proveedor_id: l.proveedor_id,
                    proveedor_etiqueta: l.proveedor_etiqueta || ('Proveedor #' + l.proveedor_id),
                    lineas: []
                };
            }
            map[key].lineas.push({ idx, linea: l, cantidad: Math.min(cant, pend) });
        });
        return Object.values(map);
    }

    function renderGrupos() {
        const cont = document.getElementById('gruposContainer');
        const grupos = gruposPorProveedor();
        if (!grupos.length) {
            cont.innerHTML = '<div class="card"><div class="card-body text-muted">Asigne proveedor a una o más partidas para formar grupos. Cada grupo genera una entrada anticipada independiente.</div></div>';
            return;
        }
        cont.innerHTML = grupos.map(function (g) {
            let sub = 0, iva = 0;
            const rows = g.lineas.map(function (item) {
                const l = item.linea;
                const imp = calcLinea(item.cantidad, l.precio_unitario_estimado, l.tasa_iva);
                sub += imp.sub;
                iva += imp.iva;
                return `<tr>
                    <td><div class="fw-600">${esc(l.descripcion)}</div><div class="text-mono text-muted" style="font-size:12px;">${esc(l.codigo)}</div></td>
                    <td class="td-center text-mono">${item.cantidad.toFixed(2)}</td>
                    <td class="td-right text-mono">$${(parseFloat(l.precio_unitario_estimado)||0).toFixed(2)}</td>
                    <td class="td-center">${etiquetaIva(l.tasa_iva)}</td>
                    <td class="td-right text-mono fw-600">$${imp.total.toFixed(2)}</td>
                </tr>`;
            }).join('');
            const total = Math.round((sub + iva) * 100) / 100;
            const key = String(g.proveedor_id);
            return `<div class="card" style="margin-bottom:16px;" data-grupo="${key}">
                <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
                    <div class="card-title">🏭 ${esc(g.proveedor_etiqueta)} · ${g.lineas.length} partida(s)</div>
                    <div class="text-mono fw-600">Total est. $${total.toFixed(2)}</div>
                </div>
                <div class="card-body" style="padding:0;">
                    <div class="table-container" style="border:none;margin:0;">
                        <table>
                            <thead><tr><th>Producto</th><th class="td-center">Cant.</th><th class="td-right">Costo</th><th class="td-center">IVA</th><th class="td-right">Total</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>
                </div>
                <div class="card-body" style="display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end;border-top:1px solid var(--color-gray-200);">
                    <button type="button" class="btn btn-outline" id="btnBorrador_${key}" onclick="crearGrupo(${g.proveedor_id}, false)">💾 Crear EA borrador</button>
                    <button type="button" class="btn btn-primary" id="btnConfirmar_${key}" onclick="crearGrupo(${g.proveedor_id}, true)">✅ Crear y confirmar recepción</button>
                </div>
            </div>`;
        }).join('');
    }

    window.crearGrupo = async function (proveedorId, confirmar) {
        const key = String(proveedorId);
        if (creandoGrupoKey) return;
        const grupo = gruposPorProveedor().find(g => String(g.proveedor_id) === key);
        if (!grupo || !grupo.lineas.length) {
            alert('El grupo no tiene partidas válidas.');
            return;
        }
        const fecha = document.getElementById('fechaRecepcion').value;
        if (!fecha) {
            alert('Indique la fecha de recepción.');
            return;
        }

        const payload = {
            proveedor_id: proveedorId,
            fecha_recepcion: fecha,
            observaciones: document.getElementById('observacionesEa').value || '',
            confirmar: !!confirmar,
            productos: grupo.lineas.map(function (item) {
                const l = item.linea;
                return {
                    detalle_id: l.detalle_id,
                    producto_id: l.producto_id,
                    descripcion: l.descripcion,
                    cantidad_recibida: item.cantidad,
                    precio_unitario_estimado: parseFloat(l.precio_unitario_estimado) || 0,
                    descuento_porcentaje: 0,
                    tasa_iva: l.tasa_iva
                };
            })
        };

        creandoGrupoKey = key;
        const btnB = document.getElementById('btnBorrador_' + key);
        const btnC = document.getElementById('btnConfirmar_' + key);
        if (btnB) btnB.disabled = true;
        if (btnC) btnC.disabled = true;

        try {
            const r = await fetch(storeGrupoUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });
            const resp = await r.json().catch(() => null);
            if (!r.ok || !resp || !resp.success) {
                mostrarAlerta((resp && resp.message) || 'No se pudo crear la entrada anticipada.', 'error');
                return;
            }

            // Preservar asignaciones de proveedor en líneas aún pendientes
            const prevProv = {};
            lineas.forEach(l => {
                if (l.proveedor_id) {
                    prevProv[l.detalle_id] = { id: l.proveedor_id, etq: l.proveedor_etiqueta, costo: l.precio_unitario_estimado };
                }
            });

            lineas = (resp.lineas || []).map(function (l) {
                const prev = prevProv[l.detalle_id];
                if (prev && (parseFloat(l.pendiente) || 0) > 0.001) {
                    l.proveedor_id = prev.id;
                    l.proveedor_etiqueta = prev.etq;
                    if (prev.costo != null) l.precio_unitario_estimado = prev.costo;
                } else {
                    l.proveedor_id = null;
                    l.proveedor_etiqueta = null;
                }
                l.cantidad_grupo = l.pendiente;
                return l;
            });
            entradasPrevias = resp.entradas_previas || entradasPrevias;
            seleccionPaso2 = {};
            const chk = document.getElementById('chkTodasPendientes');
            if (chk) chk.checked = false;

            renderEntradasPrevias();
            renderPaso2Asignacion();
            actualizarResumenPaso1();
            mostrarAlerta(resp.message + (resp.entrada && resp.entrada.url ? ' Ver ' + resp.entrada.folio + '.' : ''), 'success');
        } catch (e) {
            mostrarAlerta('Error de red al crear la entrada.', 'error');
        } finally {
            creandoGrupoKey = null;
            if (btnB) btnB.disabled = false;
            if (btnC) btnC.disabled = false;
        }
    };

    window.irPaso2 = function () {
        if (lineas.some(l => !l.tiene_producto)) return;
        document.getElementById('paso1').style.display = 'none';
        document.getElementById('paso2').style.display = 'block';
        document.getElementById('pasoBadge1').className = 'badge';
        document.getElementById('pasoBadge1').style.cssText = 'font-size:13px;padding:8px 12px;background:var(--color-gray-100);color:var(--color-gray-600);';
        document.getElementById('pasoBadge2').className = 'badge badge-info';
        document.getElementById('pasoBadge2').style.cssText = 'font-size:13px;padding:8px 12px;';
        renderPaso2Asignacion();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    window.irPaso1 = function () {
        document.getElementById('paso2').style.display = 'none';
        document.getElementById('paso1').style.display = 'block';
        document.getElementById('pasoBadge2').className = 'badge';
        document.getElementById('pasoBadge2').style.cssText = 'font-size:13px;padding:8px 12px;background:var(--color-gray-100);color:var(--color-gray-600);';
        document.getElementById('pasoBadge1').className = 'badge badge-info';
        document.getElementById('pasoBadge1').style.cssText = 'font-size:13px;padding:8px 12px;';
        renderPaso1();
        window.scrollTo({ top: 0, behavior: 'smooth' });
    };

    renderEntradasPrevias();
    renderPaso1();

    // Si ya hay productos y pendientes, opcionalmente el usuario empieza en paso 1.
    // Si todo ya tiene producto y hay pendientes, se puede saltar visualmente — no auto-saltar.
})();
</script>
@endpush
