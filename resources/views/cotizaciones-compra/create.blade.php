@extends('layouts.app')
{{-- resources/views/cotizaciones-compra/create.blade.php --}}

@section('title', isset($cotizacion) ? 'Editar Cotización de Compra' : 'Nueva Cotización de Compra')
@section('page-title', isset($cotizacion) ? '✏️ Editar Cotización de Compra' : '📝 Nueva Cotización de Compra')
@section('page-subtitle', isset($cotizacion)
    ? 'Modifica los datos del presupuesto de compra'
    : 'Crear presupuesto de compra con tu proveedor')

@php
$isEdit = isset($cotizacion);

$detallesIniciales = [];
if ($isEdit && $cotizacion->detalles->count() > 0) {
    $detallesIniciales = $cotizacion->detalles->sortBy('orden')->values()->map(function ($d) {
        return [
            'id'        => $d->producto_id,
            'codigo'    => $d->codigo ?? 'MANUAL',
            'nombre'    => $d->descripcion ?? '',
            'cantidad'  => (float) $d->cantidad,
            'precio'    => (float) $d->precio_unitario,
            'descuento' => (float) ($d->descuento_porcentaje ?? 0),
            'tasa_iva'  => $d->tasa_iva !== null ? (float) $d->tasa_iva : null,
            'manual'    => (bool) $d->es_producto_manual,
        ];
    })->all();
}

$breadcrumbs = [
    ['title' => 'Cotizaciones de Compra', 'url' => route('cotizaciones-compra.index')],
    [
        'title' => $isEdit
            ? 'Editar Cotización'
            : 'Nueva Cotización'
    ]
];
@endphp

@section('content')

<form action="{{ route('cotizaciones-compra.store') }}" method="POST" id="cotizacionForm">
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
                        <label class="form-label">Fecha <span class="req">*</span></label>
                        <input type="date"
                               name="fecha"
                               value="{{ $isEdit ? $cotizacion->fecha->format('Y-m-d') : date('Y-m-d') }}"
                               required class="form-control">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Fecha vigencia <span class="req">*</span></label>
                        <input type="date"
                               name="fecha_vencimiento"
                               value="{{ $isEdit && $cotizacion->fecha_vencimiento ? $cotizacion->fecha_vencimiento->format('Y-m-d') : date('Y-m-d', strtotime('+15 days')) }}"
                               required class="form-control">
                    </div>
                </div>

            </div>
        </div>


        {{-- Proveedor --}}
        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">👤 Proveedor</div>
            </div>
            <div class="card-body">

                <div class="form-group search-box">
                    <label class="form-label">Buscar Proveedor <span class="req">*</span></label>
                    <input type="text"
                           id="buscarProveedor"
                           value="{{ $isEdit ? $cotizacion->proveedor->nombre : '' }}"
                           placeholder="Escribe nombre o RFC..."
                           autocomplete="off"
                           class="form-control">

                    <input type="hidden" name="proveedor_id" id="proveedor_id"
                           value="{{ $isEdit ? $cotizacion->proveedor_id : '' }}" required>

                    <div id="proveedorResults" class="autocomplete-results"></div>
                </div>

                <div id="proveedorInfo" style="display:none; margin-top:14px;">
                    <div style="background: var(--color-gray-50);
                                border:1.5px solid var(--color-gray-200);
                                border-radius: var(--radius-md);
                                padding:12px 16px;
                                display:flex;
                                justify-content:space-between;
                                align-items:center;">
                        <div>
                            <div class="fw-bold" id="proveedorNombre" style="font-size:14px;"></div>
                            <div class="text-muted mt-4" id="proveedorRfc" style="font-size:13px;"></div>
                        </div>
                        <button type="button" onclick="limpiarProveedor()" class="btn btn-light btn-sm">
                            Cambiar
                        </button>
                    </div>
                </div>

            </div>
        </div>


        {{-- Productos --}}
        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">📦 Productos</div>
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
                                <th style="width:32%;">Descripción</th>
                                <th class="td-center" style="width:10%;">Cantidad</th>
                                <th class="td-right" style="width:15%;">Precio</th>
                                <th class="td-center" style="width:10%;">Desc %</th>
                                <th class="td-center" style="width:10%;">IVA</th>
                                <th class="td-right" style="width:13%;">Subtotal</th>
                                <th class="td-right" style="width:13%;">Total</th>
                                <th style="width:5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <tr id="emptyRow">
                                <td colspan="8">
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

            </div>
        </div>

        <div class="form-group">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="2">{{ $isEdit ? $cotizacion->observaciones : '' }}</textarea>
        </div>

    </div>


    {{-- ========================= --}}
    {{-- COLUMNA DERECHA --}}
    {{-- ========================= --}}
    <div>

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
        <a href="{{ route('cotizaciones-compra.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">
            ✓ Guardar Cotización
        </button>
    </div>
</div>

</form>

@endsection

@push('scripts')
<script>
let productos = [];
let timerProveedor, timerProducto;
const cotizacionDetallesIniciales = @json($detallesIniciales);

document.addEventListener('DOMContentLoaded', () => {

    if (Array.isArray(cotizacionDetallesIniciales) && cotizacionDetallesIniciales.length > 0) {
        productos = cotizacionDetallesIniciales.map(function (d) {
            return {
                id: d.id,
                codigo: d.codigo,
                nombre: d.nombre,
                cantidad: d.cantidad,
                precio: d.precio,
                descuento: d.descuento,
                tasa_iva: d.tasa_iva,
                manual: d.manual,
            };
        });
        renderProductos();
    }

    document.getElementById('buscarProveedor').addEventListener('input', function() {
        clearTimeout(timerProveedor);
        const q = this.value.trim();
        if (q.length < 2) { closeDropdown('proveedorResults'); return; }
        timerProveedor = setTimeout(() => buscarProveedores(q), 280);
    });

    document.getElementById('buscarProducto').addEventListener('input', function() {
        clearTimeout(timerProducto);
        const q = this.value.trim();
        if (q.length < 2) { closeDropdown('productoResults'); return; }
        timerProducto = setTimeout(() => buscarProductos(q), 280);
    });

    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box')) {
            closeDropdown('proveedorResults');
            closeDropdown('productoResults');
        }
    });

    @if($isEdit)
    document.getElementById('proveedorInfo').style.display = 'block';
    document.getElementById('proveedorNombre').textContent = '{{ $cotizacion->proveedor->nombre }}';
    document.getElementById('proveedorRfc').textContent = 'RFC: {{ $cotizacion->proveedor->rfc }}';
    @endif
});

function closeDropdown(id) {
    const el = document.getElementById(id);
    if (el) el.classList.remove('show');
}

async function buscarProveedores(q) {
    try {
        const r = await fetch(`{{ route('cotizaciones-compra.buscar-proveedores') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const box = document.getElementById('proveedorResults');
        if (!data.length) {
            box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        } else {
            box.innerHTML = data.map(c => `
                <div class="autocomplete-item" onclick='seleccionarProveedor(${JSON.stringify(c)})'>
                    <div class="autocomplete-item-name">${c.nombre}</div>
                    <div class="autocomplete-item-sub">RFC: ${c.rfc || ''}</div>
                </div>
            `).join('');
        }
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function seleccionarProveedor(provider) {
    document.getElementById('proveedor_id').value = provider.id;
    document.getElementById('buscarProveedor').value = provider.nombre;
    document.getElementById('proveedorNombre').textContent = provider.nombre;
    document.getElementById('proveedorRfc').textContent = 'RFC: ' + (provider.rfc || '');
    document.getElementById('proveedorInfo').style.display = 'block';
    closeDropdown('proveedorResults');
}

function limpiarProveedor() {
    document.getElementById('proveedor_id').value = '';
    document.getElementById('buscarProveedor').value = '';
    document.getElementById('proveedorInfo').style.display = 'none';
    document.getElementById('buscarProveedor').focus();
}

async function buscarProductos(q) {
    try {
        const r = await fetch(`{{ route('cotizaciones-compra.buscar-productos') }}?q=${encodeURIComponent(q)}`);
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
        cantidad: 1, precio: parseFloat(p.precio_venta),
        descuento: 0, tasa_iva: p.tasa_iva, manual: false,
    });
    document.getElementById('buscarProducto').value = '';
    closeDropdown('productoResults');
    renderProductos();
}

function agregarManual() {
    productos.push({ id: null, codigo: 'MANUAL', nombre: '', cantidad: 1, precio: 0, descuento: 0, tasa_iva: 0.16, manual: true });
    renderProductos();
}

function renderProductos() {
    const tbody = document.getElementById('productosBody');
    if (!productos.length) {
        tbody.innerHTML = `<tr id="emptyRow"><td colspan="8">
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
                    ? `<input type="text" value="${p.nombre.replace(/"/g, '&quot;')}" onchange="upd(${i},'nombre',this.value)" placeholder="Descripción..." class="form-control" style="font-size:13px;">
                       <input type="hidden" name="productos[${i}][es_producto_manual]" value="1">`
                    : `<div class="fw-600" style="font-size:13.5px;">${p.nombre}</div>
                       <span class="producto-row-code">${p.codigo}</span>`}
                <input type="hidden" name="productos[${i}][producto_id]" value="${p.id || ''}">
                <input type="hidden" name="productos[${i}][descripcion]" value="${p.nombre.replace(/"/g, '&quot;')}">
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][cantidad]" value="${p.cantidad}" min="0.01" step="0.01"
                       onchange="upd(${i},'cantidad',+this.value)" class="form-control" style="text-align:center; width:80px;">
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
    if (!document.getElementById('proveedor_id').value) {
        e.preventDefault();
        alert('⚠️ Selecciona un proveedor');
        document.getElementById('buscarProveedor').focus();
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
