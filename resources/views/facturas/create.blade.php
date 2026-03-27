@extends('layouts.app')

@section('title', 'Nueva Factura')
@section('page-title', '➕ Nueva Factura')
@section('page-subtitle', 'Crear comprobante fiscal CFDI 4.0')

@php
$breadcrumbs = [
    ['title' => 'Facturas', 'url' => route('facturas.index')],
    ['title' => 'Nueva Factura']
];
@endphp

@section('content')

<form method="POST" action="{{ route('facturas.store') }}" id="formFactura">
    @csrf
    @if(!empty($remisionId))
        <input type="hidden" name="remision_id" value="{{ $remisionId }}">
    @endif

    <div class="factura-create-layout responsive-grid">

        {{-- Columna izquierda --}}
        <div>

            {{-- Datos del Cliente --}}
            <div class="card card-search">
                <div class="card-header">
                    <div class="card-title">👤 Datos del Cliente</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Cliente <span class="req">*</span></label>
                        <select id="cliente_id" name="cliente_id" class="form-control" required>
                            <option value="">Seleccionar cliente...</option>
                            @foreach($clientes as $cliente)
                                <option value="{{ $cliente->id }}"
                                        data-rfc="{{ $cliente->rfc }}"
                                        data-regimen="{{ $cliente->regimen_fiscal }}"
                                        data-uso-cfdi="{{ $cliente->uso_cfdi_default }}"
                                        data-forma-pago="{{ $cliente->forma_pago ?? '03' }}"
                                        data-credito="{{ $cliente->dias_credito }}"
                                        {{ ($clientePreseleccionado && $clientePreseleccionado->id == $cliente->id) ? 'selected' : '' }}>
                                    {{ $cliente->nombre }} — {{ $cliente->rfc }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="infoCliente" style="display: none;">
                        <div style="background: var(--color-gray-50); border: 1.5px solid var(--color-gray-200); border-radius: var(--radius-md); padding: 12px 16px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                                <div>
                                    <span class="text-muted">RFC: </span>
                                    <span class="text-mono fw-600" id="infoRFC"></span>
                                </div>
                                <div>
                                    <span class="text-muted">Régimen: </span>
                                    <span id="infoRegimen"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Productos / Conceptos --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📦 Conceptos</div>
                    <button type="button" onclick="agregarProducto()" class="btn btn-primary btn-sm">
                        ➕ Agregar
                    </button>
                </div>
                <div class="card-body" style="padding: 0;">
                    <div class="table-container table-container--scroll" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0; overflow-y: visible; position: relative;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 48%;">Descripción</th>
                                    <th class="td-center" style="width: 10%;">Cantidad</th>
                                    <th class="td-right" style="width: 14%;">Precio Unit.</th>
                                    <th class="td-right" style="width: 12%;">Descuento</th>
                                    <th class="td-right" style="width: 13%;">Importe</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="productosContainer"></tbody>
                        </table>
                    </div>
                    <div id="productoResultsFlotanteFactura" class="autocomplete-results autocomplete-results-flotante" style="display:none; position:fixed; z-index:2000;"></div>

                    <div id="emptyProductos" style="padding: 40px 20px; text-align: center; color: var(--color-gray-500);">
                        <div style="font-size: 36px; margin-bottom: 10px; opacity: 0.3;">📦</div>
                        <div class="fw-600">Sin conceptos agregados</div>
                        <div style="font-size: 13px; margin-top: 4px;">Haz clic en "Agregar" para añadir productos</div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Columna derecha --}}
        <div>

            {{-- Datos de la Factura --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📋 Datos de la Factura</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Folio</label>
                        <div class="form-control" style="background: var(--color-gray-50); font-weight: 600; font-variant-numeric: tabular-nums;" id="visorFolio" readonly tabindex="-1">
                            @php
                                $metodoInicial = old('metodo_pago', ($clientePreseleccionado && ($clientePreseleccionado->dias_credito ?? 0) > 0) ? 'PPD' : 'PUE');
                            @endphp
                            {{ $metodoInicial === 'PPD' ? ($folioCredito ?? 'FB-0001') : ($folioContado ?? 'FA-0001') }}
                        </div>
                        <span class="form-hint">Según método de pago (PUE = contado, PPD = crédito)</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Orden de compra</label>
                        <input type="text"
                               name="orden_compra"
                               value="{{ old('orden_compra', $ordenCompraPreseleccionado ?? '') }}"
                               placeholder="Referencia libre (ej. OC-0001)"
                               class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de Emisión <span class="req">*</span></label>
                        <input type="date" name="fecha_emision" class="form-control"
                               value="{{ old('fecha_emision', now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pago <span class="req">*</span></label>
                        <select name="forma_pago" id="forma_pago" class="form-control" required>
                            @foreach($formasPago ?? [] as $fp)
                                <option value="{{ $fp->clave }}" {{ old('forma_pago', optional($clientePreseleccionado)->forma_pago ?? '03') == $fp->clave ? 'selected' : '' }}>{{ $fp->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Método de Pago <span class="req">*</span></label>
                        <select name="metodo_pago" id="metodo_pago" class="form-control" required>
                            @foreach($metodosPago ?? [] as $mp)
                                <option value="{{ $mp->clave }}" {{ old('metodo_pago', ($clientePreseleccionado && ($clientePreseleccionado->dias_credito ?? 0) > 0) ? 'PPD' : 'PUE') == $mp->clave ? 'selected' : '' }}>{{ $mp->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Uso de CFDI <span class="req">*</span></label>
                        <select name="uso_cfdi" id="uso_cfdi" class="form-control" required>
                            @foreach($usosCfdi ?? [] as $u)
                                <option value="{{ $u->clave }}" {{ old('uso_cfdi', 'G03') == $u->clave ? 'selected' : '' }}>{{ $u->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3"></textarea>
                    </div>
                </div>
            </div>

            {{-- Relación de CFDI (sustitución de CFDI con errores - SAT 2026) --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🔗 Relación de CFDI</div>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size: 13px; margin-bottom: 12px;">
                        Si esta factura sustituye un CFDI emitido con errores, indique el UUID del comprobante que se reemplaza (tipo de relación 04 - Sustitución).
                    </p>
                    <div class="form-group">
                        <label class="form-label">¿Sustituir un CFDI con errores?</label>
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                <input type="checkbox" id="checkSustituirCfdi" name="sustituir_cfdi" value="1" {{ old('sustituir_cfdi') ? 'checked' : '' }}>
                                <span>Sí, esta factura sustituye un CFDI previo</span>
                            </label>
                        </div>
                    </div>
                    <div id="bloqueCfdiSustituir" class="form-group" style="display: none;">
                        <label class="form-label">CFDI a sustituir (UUID)</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="inputUuidReferenciaDisplay" class="form-control" readonly placeholder="Seleccione el CFDI emitido con errores" style="flex: 1; min-width: 200px; background: var(--color-gray-50);">
                            <input type="hidden" name="uuid_referencia" id="inputUuidReferencia" value="{{ old('uuid_referencia') }}">
                            <input type="hidden" name="tipo_relacion" id="inputTipoRelacion" value="04">
                            <button type="button" class="btn btn-outline-primary" onclick="abrirModalSeleccionarCfdiSustituir()">
                                Seleccionar CFDI a sustituir
                            </button>
                            <button type="button" class="btn btn-light btn-sm" onclick="limpiarCfdiSustituir()">Quitar</button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Totales --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">💰 Totales</div>
                </div>
                <div class="card-body">
                    <div class="totales-panel">
                        <div class="totales-row">
                            <span>Subtotal</span>
                            <span class="monto" id="subtotalDisplay">$0.00</span>
                        </div>
                        <div class="totales-row descuento" id="rowDescuento" style="display: none;">
                            <span>Descuento</span>
                            <span class="monto" id="descuentoDisplay">−$0.00</span>
                        </div>
                        <div class="totales-row">
                            <span>IVA</span>
                            <span class="monto" id="ivaDisplay">$0.00</span>
                        </div>
                        <div class="totales-row grand">
                            <span>TOTAL</span>
                            <span class="monto" id="totalDisplay">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('facturas.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Factura</button>
        </div>
    </div>

</form>

{{-- Modal seleccionar CFDI a sustituir (relación tipo 04) --}}
<div id="modalSeleccionarCfdiSustituir" class="modal">
    <div class="modal-box" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar CFDI a sustituir</div>
            <button type="button" class="modal-close" onclick="cerrarModalCfdiSustituir()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom: 12px;">Elija la factura timbrada que fue emitida con errores y que esta nueva factura sustituye.</p>
            <div class="table-container" style="max-height: 320px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Serie / Folio</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="listaFacturasSustituir"></tbody>
                </table>
            </div>
            <div id="cargandoCfdiSustituir" style="text-align: center; padding: 20px; color: var(--color-gray-500);">Cargando facturas...</div>
            <div id="sinFacturasCfdiSustituir" style="display: none; text-align: center; padding: 20px; color: var(--color-gray-500);">No hay facturas timbradas para seleccionar.</div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
let productoIndex = 0;
let filaBusquedaActiva = null;
const catalogoProductos = @json($productos);
const folioContado = @json($folioContado ?? 'FA-0001');
const folioCredito = @json($folioCredito ?? 'FB-0001');

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function actualizarVisorFolio() {
    const metodo = document.getElementById('metodo_pago').value;
    document.getElementById('visorFolio').textContent = metodo === 'PPD' ? folioCredito : folioContado;
}

document.getElementById('metodo_pago').addEventListener('change', actualizarVisorFolio);
document.addEventListener('click', function (e) {
    if (!e.target.closest('.search-box') && !e.target.closest('#productoResultsFlotanteFactura')) {
        cerrarFlotanteProductosFactura();
    }
});

// Info cliente al cambiar select
document.getElementById('cliente_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const info = document.getElementById('infoCliente');
    if (this.value) {
        document.getElementById('infoRFC').textContent     = opt.dataset.rfc;
        document.getElementById('infoRegimen').textContent = opt.dataset.regimen || 'N/A';
        document.getElementById('uso_cfdi').value          = opt.dataset.usoCfdi || 'G03';
        document.getElementById('forma_pago').value        = opt.dataset.formaPago || '03';
        document.getElementById('metodo_pago').value       = parseInt(opt.dataset.credito) > 0 ? 'PPD' : 'PUE';
        info.style.display = 'block';
        actualizarVisorFolio();
    } else {
        info.style.display = 'none';
    }
});

// Prefill desde remisión (cuando se llega con `?remision_id=...`)
const remisionLineasPrefill = @json($remisionLineasJson ?? []);
const clientePreId = @json($clientePreseleccionado?->id ?? null);
const observacionesPreValue = @json($observacionesPre ?? null);

function prefillFacturaDesdeRemision() {
    if (!Array.isArray(remisionLineasPrefill) || remisionLineasPrefill.length === 0) return;

    const container = document.getElementById('productosContainer');
    const emptyProductos = document.getElementById('emptyProductos');

    if (container) container.innerHTML = '';
    // Reiniciar contador para que los ids de filas sigan la convención prod-{i}
    productoIndex = 0;
    if (emptyProductos) emptyProductos.style.display = 'none';

    remisionLineasPrefill.forEach((line) => {
        const i = productoIndex;
        agregarProducto(); // crea la fila con id prod-{i}

        const row = document.getElementById(`prod-${i}`);
        if (!row) return;
        seleccionarProductoPorId(i, line.producto_id);
        if (line.tasa_iva !== undefined) {
            const inpTasa = row.querySelector('.input-tasa-iva');
            if (inpTasa) inpTasa.value = String(line.tasa_iva ?? 0);
        }

        // Sobrescribir descripción/importe con snapshot de remisión
        const inpDesc = row.querySelector('[name*="[descripcion]"]');
        if (inpDesc && line.descripcion !== undefined) inpDesc.value = String(line.descripcion ?? '');

        const inpCant = row.querySelector('[name*="[cantidad]"]');
        if (inpCant && line.cantidad !== undefined) inpCant.value = Number(line.cantidad ?? 0);

        const inpValUnit = row.querySelector('[name*="[valor_unitario]"]');
        if (inpValUnit && line.valor_unitario !== undefined) {
            const v = parseFloat(line.valor_unitario ?? 0);
            inpValUnit.value = (Number.isFinite(v) ? v : 0).toFixed(2);
        }

        const inpDescMonto = row.querySelector('[name*="[descuento]"]');
        if (inpDescMonto) inpDescMonto.value = '0';

        calcularTotales();
    });

    // Pre-cargar cliente (y disparar change para que se actualice método/folio)
    if (clientePreId) {
        const clienteSelect = document.getElementById('cliente_id');
        if (clienteSelect) {
            clienteSelect.value = String(clientePreId);
            clienteSelect.dispatchEvent(new Event('change'));
        }
    }

    // Observaciones
    const obs = document.querySelector('textarea[name="observaciones"]');
    if (obs && observacionesPreValue) obs.value = String(observacionesPreValue ?? '');
}

document.addEventListener('DOMContentLoaded', prefillFacturaDesdeRemision);

function agregarProducto() {
    document.getElementById('emptyProductos').style.display = 'none';
    const i = productoIndex++;
    const tr = document.createElement('tr');
    tr.id = `prod-${i}`;
    tr.innerHTML = `
        <td style="min-width: 520px;">
            <div class="search-box" style="position: relative; margin-bottom: 6px;">
                <input type="text"
                       class="form-control"
                       style="font-size: 13px; width: 100%;"
                       placeholder="Buscar producto por código o nombre..."
                       oninput="buscarProductosFila(${i}, this.value)"
                       onfocus="filaBusquedaActiva=${i}"
                       autocomplete="off">
            </div>
            <input type="hidden" name="productos[${i}][producto_id]" class="input-producto-id" value="">
            <input type="hidden" class="input-tasa-iva" value="0">
            <input type="text" name="productos[${i}][descripcion]"
                   placeholder="Descripción *" class="form-control" style="font-size: 13px; width: 100%; min-width: 500px;" required>
        </td>
        <td class="td-center">
            <input type="number" name="productos[${i}][cantidad]"
                   class="form-control" style="text-align: center; width: 70px;"
                   value="1" min="0.01" step="0.01" onchange="calcularTotales()" required>
        </td>
        <td class="td-right">
            <input type="number" name="productos[${i}][valor_unitario]"
                   class="form-control" style="text-align: right; width: 100px;"
                   min="0" step="0.01" onchange="calcularTotales()" required>
        </td>
        <td class="td-right">
            <input type="number" name="productos[${i}][descuento]"
                   class="form-control" style="text-align: right; width: 80px;"
                   value="0" min="0" step="0.01" onchange="calcularTotales()">
        </td>
        <td class="td-right text-mono fw-600" id="importe-${i}">$0.00</td>
        <td class="td-center">
            <button type="button" onclick="quitarProducto(${i})"
                    style="background: none; border: none; cursor: pointer; color: var(--color-danger); font-size: 18px;">
                🗑️
            </button>
        </td>
    `;
    document.getElementById('productosContainer').appendChild(tr);
}

function buscarProductosFila(i, query) {
    filaBusquedaActiva = i;
    const flotante = document.getElementById('productoResultsFlotanteFactura');
    const row = document.getElementById(`prod-${i}`);
    const input = row ? row.querySelector('.search-box input[type="text"]') : null;
    if (!flotante || !input) return;
    const q = (query || '').trim().toLowerCase();
    if (q.length < 2) {
        cerrarFlotanteProductosFactura();
        return;
    }

    const encontrados = catalogoProductos
        .filter(p => {
            const nombre = String(p.nombre || '').toLowerCase();
            const codigo = String(p.codigo || '').toLowerCase();
            return nombre.includes(q) || codigo.includes(q);
        })
        .slice(0, 10);

    if (!encontrados.length) {
        posicionarFlotanteProductosFactura(input, flotante);
        flotante.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        flotante.classList.add('show');
        flotante.style.display = 'block';
        return;
    }

    posicionarFlotanteProductosFactura(input, flotante);
    flotante.innerHTML = encontrados.map(p => `
        <div class="autocomplete-item" onclick="seleccionarProductoPorId(${i}, ${p.id})">
            <div class="autocomplete-item-name">${escapeHtml(p.nombre)}</div>
            <div class="autocomplete-item-sub">${escapeHtml(p.codigo || '')} — ${escapeHtml(p.unidad || 'PZA')}</div>
        </div>
    `).join('');
    flotante.classList.add('show');
    flotante.style.display = 'block';
}

function seleccionarProductoPorId(i, productoId) {
    const p = catalogoProductos.find(x => Number(x.id) === Number(productoId));
    if (!p) return;
    const row = document.getElementById(`prod-${i}`);
    if (!row) return;
    const inputBuscar = row.querySelector('.search-box input[type="text"]');
    const tasa = p.tipo_factor === 'Exento' ? 0 : (parseFloat(p.tasa_iva) || 0);
    if (inputBuscar) {
        const etiquetaProducto = `${p.codigo || ''} — ${p.nombre || ''}`.trim();
        inputBuscar.value = etiquetaProducto;
        inputBuscar.title = etiquetaProducto;
    }
    row.querySelector('.input-tasa-iva').value = String(tasa);
    row.querySelector('[name*="[descripcion]"]').value        = p.nombre || '';
    row.querySelector('[name*="[valor_unitario]"]').value      = (parseFloat(p.precio_venta) || 0).toFixed(2);
    row.querySelector('.input-producto-id').value             = p.id;
    cerrarFlotanteProductosFactura();
    calcularTotales();
}

function posicionarFlotanteProductosFactura(input, flotante) {
    const rect = input.getBoundingClientRect();
    flotante.style.top = (rect.bottom + 6) + 'px';
    flotante.style.left = rect.left + 'px';
    flotante.style.width = Math.max(rect.width, 420) + 'px';
    flotante.style.minWidth = '380px';
}

function cerrarFlotanteProductosFactura() {
    const flotante = document.getElementById('productoResultsFlotanteFactura');
    if (!flotante) return;
    flotante.innerHTML = '';
    flotante.classList.remove('show');
    flotante.style.display = 'none';
}

function quitarProducto(i) {
    document.getElementById(`prod-${i}`)?.remove();
    calcularTotales();
    if (!document.querySelectorAll('#productosContainer tr').length) {
        document.getElementById('emptyProductos').style.display = 'block';
    }
}

function fmt(n) { return '$' + n.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }

function calcularTotales() {
    let subtotal = 0, descuento = 0, iva = 0;
    document.querySelectorAll('#productosContainer tr').forEach((tr) => {
        const cantidad = parseFloat(tr.querySelector('[name*="[cantidad]"]')?.value) || 0;
        const precio   = parseFloat(tr.querySelector('[name*="[valor_unitario]"]')?.value) || 0;
        const desc     = parseFloat(tr.querySelector('[name*="[descuento]"]')?.value) || 0;
        const importe  = cantidad * precio;
        subtotal  += importe;
        descuento += desc;
        const baseImpuesto = importe - desc;
        const tasa = parseFloat(tr.querySelector('.input-tasa-iva')?.value || 0) || 0;
        iva += baseImpuesto * tasa;
        const imp = tr.querySelector('[id^="importe-"]');
        if (imp) imp.textContent = fmt(importe);
    });
    const total = subtotal - descuento + iva;
    document.getElementById('subtotalDisplay').textContent  = fmt(subtotal);
    document.getElementById('descuentoDisplay').textContent = '−' + fmt(descuento);
    document.getElementById('ivaDisplay').textContent       = fmt(iva);
    document.getElementById('totalDisplay').textContent      = fmt(total);
    document.getElementById('rowDescuento').style.display     = descuento > 0 ? 'flex' : 'none';
}

document.getElementById('formFactura').addEventListener('submit', function(e) {
    if (!document.getElementById('cliente_id').value) {
        e.preventDefault();
        alert('⚠️ Selecciona un cliente antes de continuar.');
        return;
    }
    if (!document.querySelectorAll('#productosContainer tr').length) {
        e.preventDefault();
        alert('⚠️ Agrega al menos un concepto a la factura.');
    }
});

// Relación de CFDI (sustitución)
const listarParaRelacionUrl = '{{ route("facturas.listar-para-relacion") }}';
function toggleBloqueCfdiSustituir() {
    const checked = document.getElementById('checkSustituirCfdi').checked;
    document.getElementById('bloqueCfdiSustituir').style.display = checked ? 'block' : 'none';
    if (!checked) limpiarCfdiSustituir();
}
document.getElementById('checkSustituirCfdi').addEventListener('change', toggleBloqueCfdiSustituir);
if (document.getElementById('checkSustituirCfdi').checked) toggleBloqueCfdiSustituir();

function abrirModalSeleccionarCfdiSustituir() {
    document.getElementById('modalSeleccionarCfdiSustituir').classList.add('show');
    document.getElementById('cargandoCfdiSustituir').style.display = 'block';
    document.getElementById('sinFacturasCfdiSustituir').style.display = 'none';
    document.getElementById('listaFacturasSustituir').innerHTML = '';
    fetch(listarParaRelacionUrl)
        .then(r => r.json())
        .then(data => {
            document.getElementById('cargandoCfdiSustituir').style.display = 'none';
            const list = data.facturas || [];
            if (list.length === 0) {
                document.getElementById('sinFacturasCfdiSustituir').style.display = 'block';
                return;
            }
            const tbody = document.getElementById('listaFacturasSustituir');
            list.forEach(f => {
                const tr = document.createElement('tr');
                const label = (f.serie || '') + '-' + (f.folio || '') + ' ' + (f.cliente_nombre || '');
                tr.innerHTML = '<td>' + (f.serie || '') + ' ' + (f.folio || '') + '</td><td>' + (f.cliente_nombre || '') + '</td><td>' + (f.fecha_emision || '') + '</td><td>' + (f.total || 0) + '</td><td><button type="button" class="btn btn-primary btn-sm" data-uuid="' + (f.uuid || '').replace(/"/g, '&quot;') + '" data-label="' + (label || '').replace(/"/g, '&quot;') + '">Agregar</button></td>';
                tr.querySelector('button').addEventListener('click', function() {
                    const uuid = this.getAttribute('data-uuid') || '';
                    if (!uuid) return;
                    const inputHidden = document.getElementById('inputUuidReferencia');
                    const inputDisplay = document.getElementById('inputUuidReferenciaDisplay');
                    const current = (inputHidden.value || '').split(',').map(s => s.trim()).filter(Boolean);
                    if (!current.includes(uuid)) {
                        current.push(uuid);
                    }
                    inputHidden.value = current.join(', ');
                    inputDisplay.value = inputHidden.value;
                    document.getElementById('inputTipoRelacion').value = '04';
                });
                tbody.appendChild(tr);
            });
        })
        .catch(() => {
            document.getElementById('cargandoCfdiSustituir').style.display = 'none';
            document.getElementById('sinFacturasCfdiSustituir').style.display = 'block';
            document.getElementById('sinFacturasCfdiSustituir').textContent = 'Error al cargar facturas.';
        });
}
function cerrarModalCfdiSustituir() {
    document.getElementById('modalSeleccionarCfdiSustituir').classList.remove('show');
}
function limpiarCfdiSustituir() {
    document.getElementById('inputUuidReferencia').value = '';
    document.getElementById('inputUuidReferenciaDisplay').value = '';
    document.getElementById('inputTipoRelacion').value = '04';
}
</script>
@endpush