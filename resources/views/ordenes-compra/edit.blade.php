@extends('layouts.app')
@section('title', 'Editar Orden ' . $ordenCompra->folio)
@section('page-title', '✏️ Editar Orden ' . $ordenCompra->folio)
@section('page-subtitle', $ordenCompra->proveedor_nombre)

@php
$breadcrumbs = [
    ['title' => 'Órdenes de Compra', 'url' => route('ordenes-compra.index')],
    ['title' => $ordenCompra->folio, 'url' => route('ordenes-compra.show', $ordenCompra->id)],
    ['title' => 'Editar'],
];
$proveedorPrecargado = [
    'id' => $ordenCompra->proveedor_id,
    'nombre' => $ordenCompra->proveedor_nombre,
    'rfc' => $ordenCompra->proveedor_rfc ?? '',
    'dias_credito' => $ordenCompra->dias_credito ?? 0,
];
$productosIniciales = $ordenCompra->detalles->map(function ($d) {
    return [
        'id' => $d->producto_id,
        'codigo' => $d->codigo ?? 'MANUAL',
        'nombre' => $d->descripcion,
        'cantidad' => (float) $d->cantidad,
        'precio' => (float) $d->precio_unitario,
        'descuento' => (float) ($d->descuento_porcentaje ?? 0),
        'tasa_iva' => $d->tasa_iva !== null ? (float) $d->tasa_iva : null,
        'manual' => (bool) ($d->es_producto_manual ?? false),
    ];
})->values()->toArray();
@endphp

@section('content')

<form action="{{ route('ordenes-compra.update', $ordenCompra->id) }}" method="POST" id="ordenForm">
@csrf
@method('PUT')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Datos</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Folio</label>
                    <input type="text" value="{{ $ordenCompra->folio }}" readonly class="form-control text-mono fw-bold" style="background:var(--color-gray-100);">
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div class="form-group">
                        <label class="form-label">Fecha <span class="req">*</span></label>
                        <input type="date" name="fecha" value="{{ $ordenCompra->fecha->format('Y-m-d') }}" required class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Entrega estimada</label>
                        <input type="date" name="fecha_entrega_estimada" value="{{ $ordenCompra->fecha_entrega_estimada?->format('Y-m-d') ?? '' }}" class="form-control">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-search">
            <div class="card-header"><div class="card-title">🏭 Proveedor</div></div>
            <div class="card-body">
                <input type="hidden" name="proveedor_id" id="proveedor_id" value="{{ $ordenCompra->proveedor_id }}">
                <div id="proveedorInfo" style="padding:12px;background:var(--color-gray-50);border-radius:var(--radius-md);">
                    <div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">
                        <span class="fw-600" id="proveedorNombre">{{ $ordenCompra->proveedor_nombre }}</span>
                        <span class="text-muted" id="proveedorRfc">@if($ordenCompra->proveedor_rfc) RFC: {{ $ordenCompra->proveedor_rfc }} @endif</span>
                    </div>
                    <div style="margin-top:12px;">
                        <label class="form-label">Días de crédito</label>
                        <input type="number" name="dias_credito" id="dias_credito" min="0" value="{{ $ordenCompra->dias_credito ?? 0 }}" class="form-control" style="width:120px;">
                    </div>
                </div>
            </div>
        </div>

        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">📦 Productos</div>
                <button type="button" onclick="agregarManual()" class="btn btn-primary btn-sm">➕ Agregar</button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="search-box" style="padding:16px;">
                    <input type="text" id="buscarProducto" placeholder="Buscar producto..." autocomplete="off" class="form-control">
                    <div id="productoResults" class="autocomplete-results"></div>
                </div>
                <div class="table-container" style="border:none;margin:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Descripción</th>
                                <th class="td-center">Cant.</th>
                                <th class="td-right">Costo</th>
                                <th class="td-center">Desc %</th>
                                <th class="td-center">IVA</th>
                                <th class="td-right">Total</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <tr id="emptyRow"><td colspan="7" class="text-center text-muted" style="padding:24px;">Sin productos.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="2">{{ $ordenCompra->observaciones }}</textarea>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">💰 Totales</div></div>
            <div class="card-body">
                <div class="totales-panel">
                    <div class="totales-row"><span>Subtotal</span><span class="monto text-mono" id="tSubtotal">$0.00</span></div>
                    <div class="totales-row descuento" id="rowDescuento" style="display:none;"><span>Descuento</span><span class="monto" id="tDescuento">−$0.00</span></div>
                    <div class="totales-row"><span>IVA</span><span class="monto text-mono" id="tIva">$0.00</span></div>
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto" id="tTotal">$0.00</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="{{ route('ordenes-compra.show', $ordenCompra->id) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">✓ Guardar Cambios</button>
    </div>
</div>

</form>

@push('scripts')
<script>
let productos = @json($productosIniciales);
let timerProducto;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('buscarProducto').addEventListener('input', function() {
        clearTimeout(timerProducto);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('productoResults').classList.remove('show'); return; }
        timerProducto = setTimeout(() => buscarProductos(q), 280);
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box')) {
            document.getElementById('productoResults').classList.remove('show');
        }
    });
    renderProductos();
});

function closeDropdown(id) { document.getElementById(id).classList.remove('show'); }

async function buscarProductos(q) {
    try {
        const r = await fetch(`{{ route('cotizaciones-compra.buscar-productos') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const box = document.getElementById('productoResults');
        box.innerHTML = data.length ? data.map(p => `<div class="autocomplete-item" onclick='agregarProducto(${JSON.stringify(p)})'><div class="autocomplete-item-name">${p.nombre}</div><div class="autocomplete-item-sub">${p.codigo} — Costo $${(parseFloat(p.costo) || 0).toFixed(2)}</div></div>`).join('') : '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function agregarProducto(p) {
    if (productos.some(x => x.id && x.id === p.id)) { alert('Ya está en la lista'); return; }
    productos.push({ id: p.id, codigo: p.codigo, nombre: p.nombre, cantidad: 1, precio: parseFloat(p.costo) || 0, descuento: 0, tasa_iva: p.tasa_iva != null ? p.tasa_iva : null, manual: false });
    document.getElementById('buscarProducto').value = '';
    closeDropdown('productoResults');
    renderProductos();
}

function agregarManual() {
    productos.push({ id: null, codigo: 'MANUAL', nombre: '', cantidad: 1, precio: 0, descuento: 0, tasa_iva: 0.16, manual: true });
    renderProductos();
}

function upd(i, field, val) {
    productos[i][field] = val;
    if (field === 'nombre') document.querySelectorAll(`input[name="productos[${i}][descripcion]"]`).forEach(el => el.value = val);
    else renderProductos();
}

function quitarProducto(i) { productos.splice(i, 1); renderProductos(); }

function renderProductos() {
    const tbody = document.getElementById('productosBody');
    if (!productos.length) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="7" class="text-center text-muted" style="padding:24px;">Sin productos. Busca o agrega manual.</td></tr>';
        calcTotales(); return;
    }
    tbody.innerHTML = productos.map((p, i) => {
        const sub = p.cantidad * p.precio;
        const desc = sub * (p.descuento / 100);
        const base = sub - desc;
        const iva = p.tasa_iva != null ? base * p.tasa_iva : 0;
        const total = base + iva;
        return `<tr>
            <td>${p.manual ? `<input type="text" value="${(p.nombre||'').replace(/"/g,'&quot;')}" onchange="upd(${i},'nombre',this.value)" placeholder="Descripción" class="form-control" style="font-size:13px;"><input type="hidden" name="productos[${i}][es_producto_manual]" value="1">` : `<div class="fw-600">${(p.nombre||'').replace(/</g,'&lt;')}</div><span class="text-muted" style="font-size:12px;">${p.codigo}</span>`}
                <input type="hidden" name="productos[${i}][producto_id]" value="${p.id||''}">
                <input type="hidden" name="productos[${i}][descripcion]" value="${(p.nombre||'').replace(/"/g,'&quot;')}">
            </td>
            <td class="td-center"><input type="number" name="productos[${i}][cantidad]" value="${p.cantidad}" min="0.01" step="0.01" onchange="upd(${i},'cantidad',+this.value)" class="form-control" style="width:70px;text-align:center;"></td>
            <td class="td-right"><input type="number" name="productos[${i}][precio_unitario]" value="${p.precio.toFixed(2)}" min="0" step="0.01" onchange="upd(${i},'precio',+this.value)" class="form-control" style="width:90px;text-align:right;"></td>
            <td class="td-center"><input type="number" name="productos[${i}][descuento_porcentaje]" value="${p.descuento}" min="0" max="100" onchange="upd(${i},'descuento',+this.value)" class="form-control" style="width:60px;text-align:center;"></td>
            <td class="td-center">${p.manual ? `<select name="productos[${i}][tasa_iva]" onchange="upd(${i},'tasa_iva',this.value===''?null:+this.value)" class="form-control" style="width:70px;"><option value="0.16" ${p.tasa_iva==0.16?'selected':''}>16%</option><option value="0" ${p.tasa_iva==0?'selected':''}>0%</option><option value="" ${p.tasa_iva==null?'selected':''}>Exento</option></select>` : `<span class="fw-600" style="font-size:13px;">${p.tasa_iva==null?'Exento':(p.tasa_iva*100)+'%'}</span><input type="hidden" name="productos[${i}][tasa_iva]" value="${p.tasa_iva!=null?p.tasa_iva:''}">`}</td>
            <td class="td-right text-mono fw-600">$${total.toFixed(2)}</td>
            <td><button type="button" onclick="quitarProducto(${i})" class="btn btn-danger btn-icon btn-sm">✕</button></td>
        </tr>`;
    }).join('');
    calcTotales();
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

document.getElementById('ordenForm').addEventListener('submit', function(e) {
    if (!productos.length) { e.preventDefault(); alert('Agrega al menos un producto'); return; }
});
</script>
@endpush

@endsection
