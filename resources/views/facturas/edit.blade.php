@extends('layouts.app')

@section('title', 'Editar Factura ' . $factura->folio_completo)
@section('page-title', '✏️ Editar Factura')
@section('page-subtitle', $factura->folio_completo)

@php
$breadcrumbs = [
    ['title' => 'Facturas', 'url' => route('facturas.index')],
    ['title' => $factura->folio_completo, 'url' => route('facturas.show', $factura)],
    ['title' => 'Editar']
];
$detallesIniciales = $factura->detalles->map(fn($d) => [
    'producto_id' => $d->producto_id,
    'codigo' => $d->producto->codigo ?? '',
    'nombre' => $d->descripcion,
    'cantidad' => (float) $d->cantidad,
    'valor_unitario' => (float) $d->valor_unitario,
    'descuento' => (float) ($d->descuento ?? 0),
    'tasa_iva' => $d->producto ? (($d->producto->tipo_factor ?? 'Tasa') === 'Exento' ? 0 : (float)($d->producto->tasa_iva ?? 0)) : 0,
])->values()->all();
$clienteFacturaJson = $factura->cliente ? [
    'id' => $factura->cliente->id,
    'nombre' => $factura->cliente->nombre,
    'rfc' => $factura->cliente->rfc,
    'regimen_fiscal' => $factura->cliente->regimen_fiscal,
    'tipo_persona' => $factura->cliente->tipo_persona ?? 'fisica',
    'uso_cfdi_default' => $factura->cliente->uso_cfdi_default,
    'forma_pago' => $factura->cliente->forma_pago ?? '03',
    'dias_credito' => $factura->cliente->dias_credito,
] : null;
@endphp

@section('content')

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form method="POST" action="{{ route('facturas.update', $factura) }}" id="formFactura">
    @csrf
    @method('PUT')

    <div class="factura-create-layout responsive-grid">

        {{-- Columna izquierda --}}
        <div>

            {{-- Datos del Cliente --}}
            <div class="card card-search">
                <div class="card-header">
                    <div class="card-title">👤 Datos del Cliente</div>
                </div>
                <div class="card-body">
                    @include('partials.cliente-search-field', [
                        'clienteIdValue' => old('cliente_id', $factura->cliente_id),
                        'clienteNombreValue' => $factura->cliente->nombre ?? '',
                    ])

                    <div id="infoCliente" style="display: {{ $factura->cliente_id ? 'block' : 'none' }};">
                        <div style="background: var(--color-gray-50); border: 1.5px solid var(--color-gray-200); border-radius: var(--radius-md); padding: 12px 16px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                                <div>
                                    <span class="text-muted">RFC: </span>
                                    <span class="text-mono fw-600" id="infoRFC">{{ $factura->cliente->rfc ?? '' }}</span>
                                </div>
                                <div>
                                    <span class="text-muted">Régimen: </span>
                                    <span id="infoRegimen">{{ $factura->cliente->regimen_fiscal ?? 'N/A' }}</span>
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
                        <div class="form-control" style="background: var(--color-gray-50); font-weight: 600; font-variant-numeric: tabular-nums;" readonly tabindex="-1">
                            {{ $factura->folio_completo }}
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Orden de compra</label>
                        <input type="text"
                               name="orden_compra"
                               value="{{ old('orden_compra', $factura->orden_compra) }}"
                               placeholder="Referencia libre (ej. OC-0001)"
                               class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Fecha de Emisión <span class="req">*</span></label>
                        <input type="date" name="fecha_emision" class="form-control"
                               value="{{ old('fecha_emision', $factura->fecha_emision->format('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pago <span class="req">*</span></label>
                        <select name="forma_pago" id="forma_pago" class="form-control" required>
                            @foreach($formasPago ?? [] as $fp)
                                <option value="{{ $fp->clave }}" {{ old('forma_pago', $factura->forma_pago) == $fp->clave ? 'selected' : '' }}>{{ $fp->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Método de Pago <span class="req">*</span></label>
                        <select name="metodo_pago" id="metodo_pago" class="form-control" required>
                            @foreach($metodosPago ?? [] as $mp)
                                <option value="{{ $mp->clave }}" {{ old('metodo_pago', $factura->metodo_pago) == $mp->clave ? 'selected' : '' }}>{{ $mp->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Uso de CFDI <span class="req">*</span></label>
                        <select name="uso_cfdi" id="uso_cfdi" class="form-control" required>
                            @foreach($usosCfdi ?? [] as $u)
                                <option value="{{ $u->clave }}" {{ old('uso_cfdi', $factura->uso_cfdi) == $u->clave ? 'selected' : '' }}>{{ $u->etiqueta }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3">{{ old('observaciones', $factura->observaciones) }}</textarea>
                    </div>
                </div>
            </div>

            @if($factura->estado === 'borrador')
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
                                <input type="checkbox" id="checkSustituirCfdi" name="sustituir_cfdi" value="1"
                                       {{ old('sustituir_cfdi', $factura->uuid_referencia ? 1 : 0) ? 'checked' : '' }}>
                                <span>Sí, esta factura sustituye un CFDI previo</span>
                            </label>
                        </div>
                    </div>
                    <div id="bloqueCfdiSustituir" class="form-group" style="display: none;">
                        <label class="form-label">CFDI a sustituir (UUID)</label>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <input type="text" id="inputUuidReferenciaDisplay" class="form-control" readonly
                                   placeholder="Seleccione el CFDI emitido con errores"
                                   value="{{ old('uuid_referencia', $factura->uuid_referencia) }}"
                                   style="flex: 1; min-width: 200px; background: var(--color-gray-50);">
                            <input type="hidden" name="uuid_referencia" id="inputUuidReferencia"
                                   value="{{ old('uuid_referencia', $factura->uuid_referencia) }}">
                            <input type="hidden" name="tipo_relacion" id="inputTipoRelacion"
                                   value="{{ old('tipo_relacion', $factura->tipo_relacion ?? '04') }}">
                            <button type="button" class="btn btn-outline-primary" onclick="abrirModalSeleccionarCfdiSustituir()">
                                Seleccionar CFDI a sustituir
                            </button>
                            <button type="button" class="btn btn-light btn-sm" onclick="limpiarCfdiSustituir()">Quitar</button>
                        </div>
                    </div>
                </div>
            </div>
            @endif

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
                        <div class="totales-row descuento" id="rowIsrRetenido" style="display: none;">
                            <span>ISR retenido</span>
                            <span class="monto" id="isrRetenidoDisplay">−$0.00</span>
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
            <a href="{{ route('facturas.show', $factura) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Cambios</button>
        </div>
    </div>

</form>

@endsection

@push('scripts')
@include('partials.cliente-search-js')
<script>
let productoIndex = 0;
let filaBusquedaActiva = null;
let clienteTipoPersonaActual = @json($factura->cliente->tipo_persona ?? 'fisica');
const catalogoProductos = @json($productos);
const detallesIniciales = @json($detallesIniciales);
const empresaIsrConfig = {
    tipo_persona: @json($empresa->tipo_persona ?? 'moral'),
    regimen_fiscal: @json($empresa->regimen_fiscal ?? ''),
    regimen_resico: @json(config('isr_resico.regimen_clave', '626')),
    tasa_retencion: @json((float) config('isr_resico.tasa_retencion_pm_a_resico', 0.0125)),
};

function getClienteTipoPersona() {
    return clienteTipoPersonaActual || 'fisica';
}

function aplicaRetencionIsrPm(clienteTipoPersona) {
    const esResico = empresaIsrConfig.tipo_persona === 'fisica'
        && empresaIsrConfig.regimen_fiscal === empresaIsrConfig.regimen_resico;
    return esResico && clienteTipoPersona === 'moral';
}

function calcularRetencionIsrPm(subtotal, descuento) {
    const base = Math.max(0, subtotal - descuento);
    return Math.round(base * empresaIsrConfig.tasa_retencion * 100) / 100;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('.search-box') && !e.target.closest('#productoResultsFlotanteFactura')) {
        cerrarFlotanteProductosFactura();
    }
});

function aplicarClienteFactura(c) {
    const info = document.getElementById('infoCliente');
    if (!c || !c.id) {
        clienteTipoPersonaActual = 'fisica';
        if (info) info.style.display = 'none';
        return;
    }
    clienteTipoPersonaActual = c.tipo_persona || 'fisica';
    document.getElementById('infoRFC').textContent = c.rfc || '';
    document.getElementById('infoRegimen').textContent = c.regimen_fiscal || 'N/A';
    document.getElementById('uso_cfdi').value = c.uso_cfdi_default || 'G03';
    document.getElementById('forma_pago').value = c.forma_pago || '03';
    document.getElementById('metodo_pago').value = parseInt(c.dias_credito, 10) > 0 ? 'PPD' : 'PUE';
    if (info) info.style.display = 'block';
    calcularTotales();
}

function actualizarInfoCliente() {
    // Compatibilidad: la info del cliente se actualiza al seleccionar en búsqueda.
    calcularTotales();
}

function agregarProducto(datos = null) {
    document.getElementById('emptyProductos').style.display = 'none';
    const i = productoIndex++;

    const desc = datos ? (datos.nombre || '') : '';
    const prodId = datos ? (datos.producto_id || '') : '';
    const cant = datos ? datos.cantidad : 1;
    const precio = datos ? datos.valor_unitario : '';
    const descuento = datos ? datos.descuento : 0;
    const tasaIva = datos ? (datos.tasa_iva ?? 0) : 0;

    let buscarValor = '';
    if (prodId) {
        const p = catalogoProductos.find(x => Number(x.id) === Number(prodId));
        if (p) {
            buscarValor = `${p.codigo || ''} — ${p.nombre || ''}`.trim();
        } else if (datos?.codigo) {
            buscarValor = `${datos.codigo} — ${desc}`.trim();
        }
    }

    const tr = document.createElement('tr');
    tr.id = `prod-${i}`;
    tr.innerHTML = `
        <td style="min-width: 520px;">
            <div class="search-box" style="position: relative; margin-bottom: 6px;">
                <input type="text"
                       class="form-control"
                       style="font-size: 13px; width: 100%;"
                       placeholder="Buscar producto por código o nombre..."
                       value="${escapeHtml(buscarValor)}"
                       oninput="buscarProductosFila(${i}, this.value)"
                       onfocus="filaBusquedaActiva=${i}"
                       autocomplete="off">
            </div>
            <input type="hidden" name="productos[${i}][producto_id]" class="input-producto-id" value="${prodId}">
            <input type="hidden" class="input-tasa-iva" value="${tasaIva}">
            <input type="text" name="productos[${i}][descripcion]"
                   placeholder="Descripción *" class="form-control" style="font-size: 13px; width: 100%; min-width: 500px;" value="${escapeHtml(desc)}" required>
        </td>
        <td class="td-center">
            <input type="number" name="productos[${i}][cantidad]"
                   class="form-control" style="text-align: center; width: 70px;"
                   value="${cant}" min="0.01" step="0.01" onchange="calcularTotales()" required>
        </td>
        <td class="td-right">
            <input type="number" name="productos[${i}][valor_unitario]"
                   class="form-control" style="text-align: right; width: 100px;"
                   value="${precio}" min="0" step="0.01" onchange="calcularTotales()" required>
        </td>
        <td class="td-right">
            <input type="number" name="productos[${i}][descuento]"
                   class="form-control" style="text-align: right; width: 80px;"
                   value="${descuento}" min="0" step="0.01" onchange="calcularTotales()">
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
    calcularTotales();
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
    row.querySelector('[name*="[descripcion]"]').value = p.nombre || '';
    row.querySelector('[name*="[valor_unitario]"]').value = (parseFloat(p.precio_venta) || 0).toFixed(2);
    row.querySelector('.input-producto-id').value = p.id;
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
        const precio = parseFloat(tr.querySelector('[name*="[valor_unitario]"]')?.value) || 0;
        const desc = parseFloat(tr.querySelector('[name*="[descuento]"]')?.value) || 0;
        const importe = cantidad * precio;
        subtotal += importe;
        descuento += desc;
        const baseImpuesto = importe - desc;
        const tasa = parseFloat(tr.querySelector('.input-tasa-iva')?.value || 0) || 0;
        iva += baseImpuesto * tasa;
        const imp = tr.querySelector('[id^="importe-"]');
        if (imp) imp.textContent = fmt(importe);
    });
    const retencionIsr = aplicaRetencionIsrPm(getClienteTipoPersona())
        ? calcularRetencionIsrPm(subtotal, descuento)
        : 0;
    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('descuentoDisplay').textContent = '−' + fmt(descuento);
    document.getElementById('ivaDisplay').textContent = fmt(iva);
    document.getElementById('isrRetenidoDisplay').textContent = '−' + fmt(retencionIsr);
    document.getElementById('totalDisplay').textContent = fmt(subtotal - descuento + iva - retencionIsr);
    document.getElementById('rowDescuento').style.display = descuento > 0 ? 'flex' : 'none';
    document.getElementById('rowIsrRetenido').style.display = retencionIsr !== 0 ? 'flex' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    ClienteSearch.init({
        applyInitial: false,
        onSelect: aplicarClienteFactura,
        onClear: function () {
            clienteTipoPersonaActual = 'fisica';
            document.getElementById('infoCliente').style.display = 'none';
            calcularTotales();
        },
        initial: @json($clienteFacturaJson),
    });

    if (detallesIniciales && detallesIniciales.length > 0) {
        detallesIniciales.forEach(d => agregarProducto(d));
    }
    @if($factura->estado === 'borrador')
    // Inicializar bloque Relación CFDI según si ya hay uuid_referencia
    if (document.getElementById('checkSustituirCfdi')) {
        const check = document.getElementById('checkSustituirCfdi');
        const bloque = document.getElementById('bloqueCfdiSustituir');
        function toggleBloqueCfdiSustituir() {
            const checked = check.checked;
            bloque.style.display = checked ? 'block' : 'none';
            if (!checked) limpiarCfdiSustituir();
        }
        check.addEventListener('change', toggleBloqueCfdiSustituir);
        if (check.checked || document.getElementById('inputUuidReferencia').value) {
            bloque.style.display = 'block';
        }
    }
    @endif
});

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
@if($factura->estado === 'borrador')
// Relación de CFDI (sustitución)
const listarParaRelacionUrl = '{{ route("facturas.listar-para-relacion") }}';
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
                tr.innerHTML = '<td>' + (f.serie || '') + ' ' + (f.folio || '') + '</td><td>' + (f.cliente_nombre || '') + '</td><td>' + (f.fecha_emision || '') + '</td><td>' + (f.total || 0) + '</td><td><button type="button" class="btn btn-primary btn-sm" data-uuid=\"' + (f.uuid || '').replace(/\"/g, '&quot;') + '\" data-label=\"' + (label || '').replace(/\"/g, '&quot;') + '\">Agregar</button></td>';
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
@endif
</script>
@endpush

@if($factura->estado === 'borrador')
{{-- Modal seleccionar CFDI a sustituir (relación tipo 04) --}}
<div id="modalSeleccionarCfdiSustituir" class="modal">
    <div class="modal-box" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar CFDI a sustituir</div>
            <button type="button" class="modal-close" onclick="cerrarModalCfdiSustituir()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom: 12px;">Elija la factura timbrada que fue emitida con errores y que esta factura en borrador sustituye.</p>
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
            <div id="sinFacturasCfdiSustituir" style="display: none; text-align: center; padding: 20px; color: var(--color-gray-500);">No hay facturas disponibles (timbradas o canceladas administrativamente pendientes de PAC).</div>
        </div>
    </div>
</div>
@endif
