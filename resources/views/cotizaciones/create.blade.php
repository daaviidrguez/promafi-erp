@extends('layouts.app')
{{-- resources/views/cotizaciones/create.blade.php --}}

@section('title', isset($cotizacion) ? 'Editar Cotización' : 'Nueva Cotización')
@section('page-title', isset($cotizacion) ? '✏️ Editar Cotización' : '📝 Nueva Cotización')
@section('page-subtitle', isset($cotizacion) 
    ? 'Modifica los datos del presupuesto'
    : 'Crear presupuesto para tu cliente')

@php
$isEdit = isset($cotizacion);

$breadcrumbs = [
    ['title' => 'Cotizaciones', 'url' => route('cotizaciones.index')],
    [
        'title' => $isEdit
            ? 'Editar Cotización'
            : 'Nueva Cotización'
    ]
];
@endphp

@section('content')

<form action="{{ route('cotizaciones.store') }}" method="POST" id="cotizacionForm">
@csrf
@if($isEdit)
    <input type="hidden" name="cotizacion_id" value="{{ $cotizacion->id }}">
@endif

<div style="display:grid; grid-template-columns:2fr 1fr; gap:20px;">

    {{-- ========================= --}}
    {{-- COLUMNA IZQUIERDA --}}
    {{-- ========================= --}}
    <div>

        {{-- Información General --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información General</div>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label class="form-label">Folio</label>
                    <input type="text"
                           value="{{ $isEdit ? $cotizacion->folio : $folio }}"
                           readonly
                           class="form-control text-mono fw-bold"
                           style="background: var(--color-gray-100);">
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Fecha de Emisión <span class="req">*</span></label>
                        <input type="date"
                               name="fecha"
                               value="{{ $isEdit ? $cotizacion->fecha->format('Y-m-d') : date('Y-m-d') }}"
                               required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Válida Hasta <span class="req">*</span></label>
                        <input type="date"
                               name="fecha_vencimiento"
                               value="{{ $isEdit ? $cotizacion->fecha_vencimiento->format('Y-m-d') : date('Y-m-d', strtotime('+15 days')) }}"
                               required class="form-control">
                    </div>
                </div>

            </div>
        </div>


        {{-- Cliente --}}
        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">👤 Cliente</div>
            </div>
            <div class="card-body">

                <div class="form-group search-box">
                    <label class="form-label">Buscar Cliente <span class="req">*</span></label>
                    <input type="text"
                           id="buscarCliente"
                           value="{{ $isEdit ? $cotizacion->cliente->nombre : '' }}"
                           placeholder="Escribe nombre o RFC..."
                           autocomplete="off"
                           class="form-control">

                    <input type="hidden" name="cliente_id" id="cliente_id"
                           value="{{ $isEdit ? $cotizacion->cliente_id : '' }}" required>

                    <div id="clienteResults" class="autocomplete-results"></div>
                </div>

                <div id="clienteInfo" style="display:none; margin-top:14px;">
                    <div style="background: var(--color-gray-50);
                                border:1.5px solid var(--color-gray-200);
                                border-radius: var(--radius-md);
                                padding:12px 16px;
                                display:flex;
                                justify-content:space-between;
                                align-items:center;">
                        <div>
                            <div class="fw-bold" id="clienteNombre" style="font-size:14px;"></div>
                            <div class="text-muted mt-4" id="clienteRfc" style="font-size:13px;"></div>
                        </div>
                        <button type="button" onclick="limpiarCliente()" class="btn btn-light btn-sm">
                            Cambiar
                        </button>
                    </div>
                </div>

            </div>
        </div>


        {{-- Productos --}}
        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">📦 Productos y Servicios</div>
                <button type="button" onclick="agregarManual()" class="btn btn-primary btn-sm">
                    ➕ Agregar
                </button>
            </div>

            <div class="card-body" style="padding:0;">

                <div class="search-box" style="padding:16px;">
                    <input type="text"
                           id="buscarProducto"
                           placeholder="Buscar por código o nombre..."
                           autocomplete="off"
                           class="form-control">
                    <div id="productoResults" class="autocomplete-results"></div>
                </div>

                <div class="table-container" style="border:none; box-shadow:none; border-radius:0; margin-bottom:0;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:28%;">Descripción</th>
                                <th class="td-center" style="width:8%;">Cant.</th>
                                <th class="td-center" style="width:8%;">Unidad</th>
                                <th class="td-right" style="width:12%;">Precio</th>
                                <th class="td-center" style="width:8%;">Desc %</th>
                                <th class="td-center" style="width:8%;">IVA</th>
                                <th class="td-right" style="width:12%;">Subtotal</th>
                                <th class="td-right" style="width:12%;">Total</th>
                                <th style="width:4%;"></th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <tr id="emptyRow">
                                <td colspan="9">
                                    <div style="padding:40px 20px; text-align:center; color:var(--color-gray-500);">
                                        <div style="font-size:36px; margin-bottom:10px; opacity:0.3;">📦</div>
                                        <div class="fw-600">Sin productos agregados</div>
                                        <div style="font-size:13px; margin-top:4px;">
                                            Usa el buscador para añadir productos
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                {{-- Dropdown de sugerencias posicionado fuera de la tabla para que se vea encima (igual que cliente/producto) --}}
                <div id="sugerenciaResultsFlotante" class="autocomplete-results autocomplete-results-flotante" style="display:none; position:fixed; z-index:2000;"></div>

            </div>
        </div>

    </div>


    {{-- ========================= --}}
    {{-- COLUMNA DERECHA --}}
    {{-- ========================= --}}
    <div>

        {{-- Condiciones de Venta --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">💳 Condiciones</div>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label class="form-label">Tipo de Venta <span class="req">*</span></label>
                    <select name="tipo_venta" id="tipoVenta"
                            onchange="onTipoVentaChange()" class="form-control">
                        <option value="contado" {{ ($isEdit && $cotizacion->tipo_venta === 'contado') ? 'selected' : '' }}>
                            Contado
                        </option>
                        <option value="credito" {{ ($isEdit && $cotizacion->tipo_venta === 'credito') ? 'selected' : '' }}>
                            Crédito
                        </option>
                    </select>
                </div>

                <div class="form-group" id="diasCreditoGroup" style="display:none;">
                    <label class="form-label">Días de Crédito</label>
                    <input type="number"
                        name="dias_credito"
                        id="diasCredito"
                        value="{{ $isEdit ? $cotizacion->dias_credito_aplicados : '' }}"
                        min="1"
                        class="form-control">
                </div>

            </div>
        </div>


        {{-- 📄 Condiciones y Observaciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📄 Condiciones y Observaciones</div>
            </div>
            <div class="card-body">

                <div class="form-group">
                    <label class="form-label">Condiciones Comerciales</label>
                    <textarea name="condiciones_pago" class="form-control" rows="3">
    {{ $isEdit ? $cotizacion->condiciones_pago : 'Precios más IVA. Vigencia 15 días.' }}
                    </textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3">
    {{ $isEdit ? $cotizacion->observaciones : '' }}
                    </textarea>
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
                        <span class="monto text-mono" id="tSubtotal">$0.00</span>
                    </div>

                    <div class="totales-row descuento" id="rowDescuento" style="display:none;">
                        <span>Descuento</span>
                        <span class="monto text-mono" id="tDescuento">−$0.00</span>
                    </div>

                    <div class="totales-row">
                        <span>IVA</span>
                        <span class="monto text-mono" id="tIva">$0.00</span>
                    </div>

                    <div class="totales-row grand">
                        <span>TOTAL</span>
                        <span class="monto" id="tTotal">$0.00</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Botones --}}
<div class="card">
    <div class="card-body" style="display:flex; gap:12px; justify-content:flex-end;">
        <a href="{{ route('cotizaciones.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            ✓ Guardar Cotización
        </button>
    </div>
</div>

</form>

@endsection

@php
    $detallesIniciales = [];
    if (isset($cotizacion) && $cotizacion->detalles->count() > 0) {
        $detallesIniciales = $cotizacion->detalles->sortBy('orden')->values()->map(function ($d) {
            return [
                'id'        => $d->producto_id,
                'codigo'    => ($d->codigo === 'MANUAL' ? '-' : $d->codigo) ?? '-',
                'nombre'    => $d->descripcion ?? '',
                'cantidad'  => (float) $d->cantidad,
                'unidad'    => $d->unidad ?? $d->producto->unidad ?? 'PZA',
                'precio'    => (float) $d->precio_unitario,
                'descuento' => (float) ($d->descuento_porcentaje ?? 0),
                'tasa_iva'  => $d->tasa_iva !== null ? (float) $d->tasa_iva : null,
                'manual'    => (bool) $d->es_producto_manual,
                'sugerencia_id' => $d->sugerencia_id,
            ];
        })->all();
    }
@endphp

@push('scripts')
<script>
let productos = [];
let timerCliente, timerProducto, timerSugerencia = {};
let lastSugerenciaRowIndex = 0;
const cotizacionDetallesIniciales = @json($detallesIniciales);

document.addEventListener('DOMContentLoaded', () => {

    // Cargar productos existentes al editar (misma coherencia que al crear)
    if (Array.isArray(cotizacionDetallesIniciales) && cotizacionDetallesIniciales.length > 0) {
        productos = cotizacionDetallesIniciales.map(function (d) {
            return {
                id: d.id,
                codigo: d.codigo,
                nombre: d.nombre,
                cantidad: d.cantidad,
                unidad: d.unidad || 'PZA',
                precio: d.precio,
                descuento: d.descuento,
                tasa_iva: d.tasa_iva,
                manual: d.manual,
                sugerencia_id: d.sugerencia_id || null,
            };
        });
        renderProductos();
    }

    @if($isEdit && isset($cotizacion) && $cotizacion->tipo_venta === 'credito')
    document.getElementById('diasCreditoGroup').style.display = 'block';
    @endif

    // Buscador cliente
    document.getElementById('buscarCliente').addEventListener('input', function() {
        clearTimeout(timerCliente);
        const q = this.value.trim();
        if (q.length < 2) { closeDropdown('clienteResults'); return; }
        timerCliente = setTimeout(() => buscarClientes(q), 280);
    });

    // Buscador producto
    document.getElementById('buscarProducto').addEventListener('input', function() {
        clearTimeout(timerProducto);
        const q = this.value.trim();
        if (q.length < 2) { closeDropdown('productoResults'); return; }
        timerProducto = setTimeout(() => buscarProductos(q), 280);
    });

    // Cerrar dropdowns al hacer click fuera (no cerrar sugerencias si el clic es dentro del flotante)
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box') && !e.target.closest('#sugerenciaResultsFlotante')) {
            closeDropdown('clienteResults');
            closeDropdown('productoResults');
            closeSugerenciaFlotante();
        }
    });

    @if($isEdit)
    document.getElementById('clienteInfo').style.display = 'block';
    document.getElementById('clienteNombre').textContent = '{{ $cotizacion->cliente->nombre }}';
    document.getElementById('clienteRfc').textContent = 'RFC: {{ $cotizacion->cliente->rfc }}';
    @endif
});

function closeDropdown(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

async function buscarClientes(q) {
    try {
        const r = await fetch(`{{ route('cotizaciones.buscar-clientes') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const box = document.getElementById('clienteResults');
        if (!data.length) {
            box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        } else {
            box.innerHTML = data.map(c => `
                <div class="autocomplete-item" onclick='seleccionarCliente(${JSON.stringify(c)})'>
                    <div class="autocomplete-item-name">${c.nombre}</div>
                    <div class="autocomplete-item-sub">RFC: ${c.rfc}</div>
                </div>
            `).join('');
        }
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function seleccionarCliente(c) {
    document.getElementById('cliente_id').value = c.id;
    document.getElementById('buscarCliente').value = c.nombre;
    document.getElementById('clienteNombre').textContent = c.nombre;
    document.getElementById('clienteRfc').textContent = `RFC: ${c.rfc}`;
    document.getElementById('clienteInfo').style.display = 'block';
    closeDropdown('clienteResults');
    // Auto-configurar crédito
    if (c.dias_credito > 0) {
        document.getElementById('tipoVenta').value = 'credito';
        document.getElementById('diasCredito').value = c.dias_credito;
        document.getElementById('diasCreditoGroup').style.display = 'block';
    }
}

function limpiarCliente() {
    document.getElementById('cliente_id').value = '';
    document.getElementById('buscarCliente').value = '';
    document.getElementById('clienteInfo').style.display = 'none';
    document.getElementById('buscarCliente').focus();
}

function onTipoVentaChange() {
    const tipo = document.getElementById('tipoVenta').value;
    document.getElementById('diasCreditoGroup').style.display = tipo === 'credito' ? 'block' : 'none';
}

async function buscarProductos(q) {
    try {
        const r = await fetch(`{{ route('cotizaciones.buscar-productos') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const box = document.getElementById('productoResults');
        if (!data.length) {
            box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        } else {
            box.innerHTML = data.map(p => `
                <div class="autocomplete-item" onclick='agregarProducto(${JSON.stringify(p)})'>
                    <div class="autocomplete-item-name">${p.nombre}</div>
                    <div class="autocomplete-item-sub">${p.codigo} — $${parseFloat(p.precio_venta).toFixed(2)}</div>
                </div>
            `).join('');
        }
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function agregarProducto(p) {
    if (productos.find(x => x.id === p.id)) { alert('Este producto ya está en la lista'); return; }
    productos.push({
        id: p.id, codigo: p.codigo, nombre: p.nombre,
        cantidad: 1, unidad: p.unidad || 'PZA', precio: parseFloat(p.precio_venta),
        descuento: 0, tasa_iva: p.tasa_iva, manual: false,
    });
    document.getElementById('buscarProducto').value = '';
    closeDropdown('productoResults');
    renderProductos();
}

function agregarManual() {
    productos.push({ id: null, codigo: '-', nombre: '', cantidad: 1, unidad: 'PZA', precio: 0, descuento: 0, tasa_iva: 0.16, manual: true, sugerencia_id: null });
    renderProductos();
}

function renderProductos() {
    const tbody = document.getElementById('productosBody');
    if (!productos.length) {
        tbody.innerHTML = `<tr id="emptyRow"><td colspan="9">
            <div class="empty-state" style="padding:28px 20px;">
                <div class="empty-state-icon">📦</div>
                <div class="empty-state-title">Sin productos</div>
                <div class="empty-state-text">Usa el buscador para agregar</div>
            </div></td></tr>`;
        calcTotales(); return;
    }
    tbody.innerHTML = productos.map((p, i) => {
        const sub = p.cantidad * p.precio;
        const desc = sub * (p.descuento / 100);
        const base = sub - desc;
        const iva = p.tasa_iva != null ? base * p.tasa_iva : 0;
        const total = base + iva;
        return `<tr>
            <td>
                ${p.manual
                    ? `<div class="search-box search-box-manual">
                       <input type="text" id="manualDesc_${i}" value="${(p.nombre||'').replace(/"/g,'&quot;')}" onchange="upd(${i},'nombre',this.value)" oninput="onManualDescInput(${i},this.value)" onkeydown="onManualDescKeydown(${i},event)" onfocus="lastSugerenciaRowIndex=${i}" placeholder="Descripción o código (3+ caracteres)..." class="form-control" style="font-size:13px;" autocomplete="off" data-row="${i}">
                       </div>
                       <input type="hidden" name="productos[${i}][es_producto_manual]" value="1">
                       <input type="hidden" name="productos[${i}][sugerencia_id]" value="${p.sugerencia_id || ''}">`
                    : `<div class="fw-600" style="font-size:13.5px;">${p.nombre}</div>
                       <span class="producto-row-code">${p.codigo}</span>`}
                <input type="hidden" name="productos[${i}][producto_id]" value="${p.id || ''}">
                <input type="hidden" name="productos[${i}][descripcion]" value="${(p.nombre||'').replace(/"/g,'&quot;')}">
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][cantidad]" value="${p.cantidad}" min="0.01" step="0.01"
                       onchange="upd(${i},'cantidad',+this.value)" class="form-control" style="text-align:center; width:80px;">
            </td>
            <td class="td-center">
                <input type="text" name="productos[${i}][unidad]" value="${(p.unidad || 'PZA').replace(/"/g, '&quot;')}" 
                       onchange="upd(${i},'unidad',this.value)" class="form-control" style="text-align:center; width:70px;" placeholder="PZA" maxlength="10">
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][precio_unitario]" value="${p.precio.toFixed(2)}" min="0" step="0.01"
                       onchange="upd(${i},'precio',+this.value)" class="form-control" style="text-align:right; width:110px;">
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][descuento_porcentaje]" value="${p.descuento}" min="0" max="100"
                       onchange="upd(${i},'descuento',+this.value)" class="form-control" style="text-align:center; width:70px;">
            </td>
            <td class="td-center">
                ${p.manual
                    ? `<select name="productos[${i}][tasa_iva]" onchange="upd(${i},'tasa_iva',this.value===''?null:+this.value)" class="form-control" style="width:80px;">
                           <option value="0.16" ${p.tasa_iva==0.16?'selected':''}>16%</option>
                           <option value="0"    ${p.tasa_iva==0?'selected':''}>0%</option>
                           <option value=""     ${p.tasa_iva==null?'selected':''}>Exento</option>
                       </select>`
                    : `<span class="fw-600" style="font-size:13px;">${p.tasa_iva == null ? 'Exento' : (p.tasa_iva*100)+'%'}</span>
                       <input type="hidden" name="productos[${i}][tasa_iva]" value="${p.tasa_iva!=null?p.tasa_iva:''}">`}
            </td>
            <td class="td-right text-mono" style="font-size:13px;">$${sub.toFixed(2)}</td>
            <td class="td-right text-mono fw-bold" style="color: var(--color-secondary); font-size:13.5px;">$${total.toFixed(2)}</td>
            <td class="td-center">
                <button type="button" onclick="quitarProducto(${i})" class="btn btn-danger btn-icon btn-sm">✕</button>
            </td>
        </tr>`;
    }).join('');
    calcTotales();
}

function upd(i, field, val) {
    productos[i][field] = val;
    if (field === 'nombre') {
        document.querySelectorAll(`input[name="productos[${i}][descripcion]"]`).forEach(el => el.value = val);
    } else {
        renderProductos();
    }
}

function onManualDescInput(rowIndex, value) {
    upd(rowIndex, 'nombre', value);
    clearTimeout(timerSugerencia[rowIndex]);
    const q = (value || '').trim();
    if (q.length < 3) {
        closeSugerenciaFlotante();
        return;
    }
    timerSugerencia[rowIndex] = setTimeout(() => buscarSugerencias(rowIndex, q), 280);
}

function closeSugerenciaFlotante() {
    const flotante = document.getElementById('sugerenciaResultsFlotante');
    if (flotante) { flotante.innerHTML = ''; flotante.classList.remove('show'); flotante.style.display = 'none'; }
}

function onManualDescKeydown(rowIndex, e) {
    if (e.key !== 'Enter') return;
    const flotante = document.getElementById('sugerenciaResultsFlotante');
    if (!flotante || !flotante.classList.contains('show')) return;
    const first = flotante.querySelector('.autocomplete-item');
    if (first) { first.click(); e.preventDefault(); }
}

async function buscarSugerencias(rowIndex, q) {
    try {
        const r = await fetch(`{{ route('sugerencias.buscar') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const input = document.getElementById('manualDesc_' + rowIndex);
        const flotante = document.getElementById('sugerenciaResultsFlotante');
        if (!input || !flotante) return;
        closeSugerenciaFlotante();
        const rect = input.getBoundingClientRect();
        flotante.style.top = (rect.bottom + 6) + 'px';
        flotante.style.left = rect.left + 'px';
        flotante.style.width = Math.max(rect.width, 320) + 'px';
        flotante.style.minWidth = '280px';
        if (!data.length) {
            flotante.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin sugerencias</div></div>';
        } else {
            flotante.innerHTML = data.map(s => {
                const desc = (s.descripcion || '').substring(0, 50) + ((s.descripcion || '').length > 50 ? '…' : '');
                const label = (s.codigo ? s.codigo + ' — ' : '') + desc;
                return `<div class="autocomplete-item" data-id="${s.id}" data-desc="${(s.descripcion||'').replace(/"/g,'&quot;')}" data-unidad="${(s.unidad||'PZA').replace(/"/g,'&quot;')}" data-precio="${s.precio_unitario}" onclick="aplicarSugerencia(${rowIndex}, this)">
                    <div class="autocomplete-item-name">${label.replace(/</g,'&lt;')}</div>
                    <div class="autocomplete-item-sub">${(s.unidad||'PZA')} — $${parseFloat(s.precio_unitario).toFixed(2)}</div>
                </div>`;
            }).join('');
        }
        flotante.classList.add('show');
        flotante.style.display = 'block';
    } catch (e) { console.error(e); }
}

function aplicarSugerencia(rowIndex, el) {
    if (!el || !el.dataset) return;
    const id = el.dataset.id, desc = el.dataset.desc || '', unidad = el.dataset.unidad || 'PZA', precio = parseFloat(el.dataset.precio) || 0;
    if (!id) return;
    productos[rowIndex].nombre = desc;
    productos[rowIndex].unidad = unidad;
    productos[rowIndex].precio = precio;
    productos[rowIndex].sugerencia_id = id;
    closeSugerenciaFlotante();
    renderProductos();
}

function quitarProducto(i) {
    productos.splice(i, 1);
    renderProductos();
}

function calcTotales() {
    let sub = 0, desc = 0, iva = 0;
    productos.forEach(p => {
        const s = p.cantidad * p.precio;
        const d = s * (p.descuento / 100);
        sub += s; desc += d;
        if (p.tasa_iva != null) iva += (s - d) * p.tasa_iva;
    });
    document.getElementById('tSubtotal').textContent = '$' + sub.toFixed(2);
    document.getElementById('tDescuento').textContent = '−$' + desc.toFixed(2);
    document.getElementById('tIva').textContent = '$' + iva.toFixed(2);
    document.getElementById('tTotal').textContent = '$' + ((sub - desc) + iva).toFixed(2);
    document.getElementById('rowDescuento').style.display = desc > 0 ? 'flex' : 'none';
}

document.getElementById('cotizacionForm').addEventListener('submit', e => {
    if (!document.getElementById('cliente_id').value) {
        e.preventDefault();
        alert('⚠️ Selecciona un cliente');
        document.getElementById('buscarCliente').focus();
        return;
    }
    if (!productos.length) {
        e.preventDefault();
        alert('⚠️ Agrega al menos un producto');
        document.getElementById('buscarProducto').focus();
    }
});
</script>
@endpush