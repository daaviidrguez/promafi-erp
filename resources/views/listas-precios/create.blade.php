@extends('layouts.app')

@section('title', 'Nueva Lista de Precios')
@section('page-title', '➕ Nueva Lista de Precios')
@section('page-subtitle', 'Configura precios por producto: Markup (factor) o Margen (utilidad real)')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Listas de Precios', 'url' => route('listas-precios.index')],
    ['title' => 'Nueva']
];
@endphp

@section('content')

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form action="{{ route('listas-precios.store') }}" method="POST" id="formLista">
@csrf

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Configuración</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Nombre <span class="req">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre') }}" required maxlength="120" placeholder="Ej. Lista Mayoreo Cliente X">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2" maxlength="500">{{ old('descripcion') }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Cliente asignado</label>
                    <select name="cliente_id" class="form-control">
                        <option value="">— Sin asignar (lista general) —</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}" {{ old('cliente_id') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    <span class="form-hint">Si asignas un cliente, esta lista aparecerá al crear cotizaciones para ese cliente.</span>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}>
                        Lista activa
                    </label>
                </div>
            </div>
        </div>

        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">📦 Productos</div>
                <button type="button" onclick="document.getElementById('buscarProducto').focus()" class="btn btn-primary btn-sm">➕ Agregar</button>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="search-box" style="padding:16px;">
                    <input type="text" id="buscarProducto" placeholder="Buscar producto por código o nombre..." autocomplete="off" class="form-control">
                    <div id="productoResults" class="autocomplete-results"></div>
                </div>
                <div class="table-container" style="border:none;box-shadow:none;margin:0;">
                    <table>
                        <thead>
                            <tr>
                                <th style="width:24%;">Producto</th>
                                <th class="td-right" style="width:10%;">Costo</th>
                                <th class="td-center" style="width:16%;">Tipo utilidad</th>
                                <th class="td-right" style="width:10%;">Valor %</th>
                                <th class="td-right" style="width:10%;">Precio</th>
                                <th class="td-center" style="width:8%;">Activo</th>
                                <th style="width:5%;"></th>
                            </tr>
                        </thead>
                        <tbody id="productosBody"></tbody>
                    </table>
                </div>
                <div id="emptyProductos" style="padding:32px 20px;text-align:center;color:var(--color-gray-500);">
                    <div style="font-size:32px;margin-bottom:8px;opacity:0.3;">📦</div>
                    <div class="fw-600">Sin productos</div>
                    <div style="font-size:13px;margin-top:4px;">Busca y agrega productos. El costo viene del producto.</div>
                </div>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
                <button type="submit" class="btn btn-primary">✓ Guardar Lista</button>
                <a href="{{ route('listas-precios.index') }}" class="btn btn-light">Cancelar</a>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">ℹ️ Ayuda</div></div>
            <div class="card-body" style="font-size:13px;">
                <p><strong>Factorizado (Markup):</strong> costo × (1 + %). Ej: 30% → costo × 1.30</p>
                <p><strong>Utilidad Real (Margen):</strong> costo ÷ (1 − %). Ej: 30% → costo ÷ 0.70</p>
                <p class="text-muted mt-2">Valor: 1% a 99% (no usar 100%).</p>
                <p class="text-muted mt-3">El costo se toma del producto (costo promedio o costo base). La información fiscal viene del producto.</p>
            </div>
        </div>
    </div>
</div>

</form>

@endsection

@php
    $catalogoProductos = $productos->map(function ($p) {
        return [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'unidad' => $p->unidad ?? 'PZA',
            'costo' => (float)($p->costo_promedio_mostrar ?? $p->costo ?? 0),
            'tasa_iva' => ($p->tipo_factor ?? 'Tasa') === 'Exento' ? null : (float)($p->tasa_iva ?? 0),
            'tipo_factor' => $p->tipo_factor ?? 'Tasa',
            'objeto_impuesto' => $p->objeto_impuesto ?? '02',
            'tipo_impuesto' => $p->tipo_impuesto ?? '002',
        ];
    })->values();
@endphp
@push('scripts')
<script>
const catalogoProductos = @json($catalogoProductos);
let productos = [];
let timerProducto;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('buscarProducto').addEventListener('input', function() {
        clearTimeout(timerProducto);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('productoResults').classList.remove('show'); return; }
        timerProducto = setTimeout(() => filtrarProductos(q), 200);
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box')) document.getElementById('productoResults').classList.remove('show');
    });
});

function filtrarProductos(q) {
    const ql = q.toLowerCase();
    const list = catalogoProductos.filter(p =>
        (p.nombre || '').toLowerCase().includes(ql) || (p.codigo || '').toLowerCase().includes(ql)
    ).slice(0, 12);
    const box = document.getElementById('productoResults');
    box.innerHTML = list.length ? list.map(p => `
        <div class="autocomplete-item" onclick='agregarDesdeCatalogo(${JSON.stringify(p)})'>
            <div class="autocomplete-item-name">${p.nombre}</div>
            <div class="autocomplete-item-sub">${p.codigo} — Costo $${p.costo.toFixed(2)}</div>
        </div>
    `).join('') : '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
    box.classList.add('show');
}

function agregarDesdeCatalogo(p) {
    if (productos.some(x => x.id === p.id)) { alert('Ya está en la lista'); return; }
    productos.push({
        id: p.id, codigo: p.codigo, nombre: p.nombre, unidad: p.unidad,
        costo: p.costo, tasa_iva: p.tasa_iva, tipo_factor: p.tipo_factor,
        objeto_impuesto: p.objeto_impuesto, tipo_impuesto: p.tipo_impuesto,
        tipo_utilidad: 'margen', valor_utilidad: 30, activo: true,
    });
    document.getElementById('buscarProducto').value = '';
    document.getElementById('productoResults').classList.remove('show');
    renderProductos();
}

function quitarProducto(i) {
    productos.splice(i, 1);
    renderProductos();
}

function calcPrecio(p) {
    const costo = p.costo || 0;
    let v = Math.max(1, Math.min(99, parseFloat(p.valor_utilidad) || 30)) / 100;
    if (p.tipo_utilidad === 'factorizado') {
        return Math.round(costo * (1 + v) * 100) / 100;
    }
    return v >= 1 ? costo : Math.round((costo / (1 - v)) * 100) / 100;
}

function renderProductos() {
    const tbody = document.getElementById('productosBody');
    const empty = document.getElementById('emptyProductos');
    if (!productos.length) {
        tbody.innerHTML = '';
        empty.style.display = 'block';
        return;
    }
    empty.style.display = 'none';
    tbody.innerHTML = productos.map((p, i) => {
        const precio = calcPrecio(p);
        return `<tr>
            <td>
                <div class="fw-600" style="font-size:13px;">${p.nombre || '—'}</div>
                <span class="text-muted" style="font-size:12px;">${p.codigo || ''}</span>
                <input type="hidden" name="productos[${i}][producto_id]" value="${p.id || ''}">
            </td>
            <td class="td-right text-mono">$${(p.costo||0).toFixed(2)}</td>
            <td class="td-center">
                <select name="productos[${i}][tipo_utilidad]" onchange="upd(${i},'tipo_utilidad',this.value)" class="form-control" style="font-size:12px;">
                    <option value="factorizado" ${p.tipo_utilidad==='factorizado'?'selected':''}>Factorizado (Markup)</option>
                    <option value="margen" ${p.tipo_utilidad==='margen'?'selected':''}>Utilidad Real (Margen)</option>
                </select>
            </td>
            <td class="td-right">
                <div style="display:flex;align-items:center;gap:4px;justify-content:flex-end;">
                    <input type="number" name="productos[${i}][valor_utilidad]" value="${Math.max(1,Math.min(99,p.valor_utilidad||30))}" min="1" max="99" step="1"
                           onchange="upd(${i},'valor_utilidad',Math.max(1,Math.min(99,+this.value)))" class="form-control" style="text-align:right;width:70px;">
                    <span>%</span>
                </div>
            </td>
            <td class="td-right text-mono fw-600" style="color:var(--color-primary);">$${precio.toFixed(2)}</td>
            <td class="td-center">
                <label style="display:flex;align-items:center;justify-content:center;cursor:pointer;">
                    <input type="hidden" name="productos[${i}][activo]" value="0">
                    <input type="checkbox" name="productos[${i}][activo]" value="1" ${(p.activo!==false)?'checked':''} onchange="upd(${i},'activo',this.checked)">
                </label>
            </td>
            <td><button type="button" onclick="quitarProducto(${i})" class="btn btn-danger btn-icon btn-sm" title="Eliminar">✕</button></td>
        </tr>`;
    }).join('');
}

function upd(i, field, val) {
    productos[i][field] = val;
    renderProductos();
}

document.getElementById('formLista').addEventListener('submit', function(e) {
    if (!productos.length) {
        e.preventDefault();
        alert('Agrega al menos un producto a la lista.');
        return;
    }
    const invalidos = productos.filter(p => {
        const v = parseFloat(p.valor_utilidad);
        return isNaN(v) || v < 1 || v > 99;
    });
    if (invalidos.length) {
        e.preventDefault();
        alert('El valor debe estar entre 1% y 99% en todos los productos.');
    }
});
</script>
@endpush
