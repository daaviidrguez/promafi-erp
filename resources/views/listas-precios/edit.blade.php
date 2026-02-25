@extends('layouts.app')

@section('title', 'Editar Lista de Precios')
@section('page-title', '✏️ Editar Lista de Precios')
@section('page-subtitle', $listaPrecio->nombre)

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Listas de Precios', 'url' => route('listas-precios.index')],
    ['title' => 'Editar']
];
$detallesIniciales = $listaPrecio->detalles->map(fn($d) => [
    'id' => $d->producto_id,
    'codigo' => $d->producto->codigo ?? '',
    'nombre' => $d->producto->nombre ?? '',
    'unidad' => $d->producto->unidad ?? 'PZA',
    'costo' => (float)($d->producto->costo_promedio_mostrar ?? $d->producto->costo ?? 0),
    'tasa_iva' => ($d->producto->tipo_factor ?? 'Tasa') === 'Exento' ? null : (float)($d->producto->tasa_iva ?? 0),
    'tipo_factor' => $d->producto->tipo_factor ?? 'Tasa',
    'objeto_impuesto' => $d->producto->objeto_impuesto ?? '02',
    'tipo_impuesto' => $d->producto->tipo_impuesto ?? '002',
    'tipo_utilidad' => $d->tipo_utilidad,
    'valor_utilidad' => (float)$d->valor_utilidad,
    'activo' => (bool)($d->activo ?? true),
])->values()->all();
@endphp

@section('content')

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form action="{{ route('listas-precios.update', $listaPrecio) }}" method="POST" id="formLista">
@csrf
@method('PUT')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">

    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Configuración</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Nombre <span class="req">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $listaPrecio->nombre) }}" required maxlength="120">
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <textarea name="descripcion" class="form-control" rows="2" maxlength="500">{{ old('descripcion', $listaPrecio->descripcion) }}</textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Cliente asignado</label>
                    <select name="cliente_id" class="form-control">
                        <option value="">— Sin asignar —</option>
                        @foreach($clientes as $c)
                            <option value="{{ $c->id }}" {{ old('cliente_id', $listaPrecio->cliente_id) == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', $listaPrecio->activo) ? 'checked' : '' }}>
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
                    <input type="text" id="buscarProducto" placeholder="Buscar producto..." autocomplete="off" class="form-control">
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
                <div id="emptyProductos" style="padding:32px 20px;text-align:center;color:var(--color-gray-500);"></div>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:12px;">
                <a href="{{ route('listas-precios.show', $listaPrecio) }}" class="btn btn-light">Cancelar</a>
                <button type="submit" class="btn btn-primary">✓ Guardar Cambios</button>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">ℹ️ Ayuda</div></div>
            <div class="card-body" style="font-size:13px;">
                <p><strong>Factorizado (Markup):</strong> costo × (1 + %). Ej: 30% → costo × 1.30</p>
                <p><strong>Utilidad Real (Margen):</strong> costo ÷ (1 − %). Ej: 30% → costo ÷ 0.70</p>
                <p class="text-muted mt-2">Valor: 1% a 99% (no usar 100%).</p>
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
let productos = @json($detallesIniciales);
let timerProducto;

document.addEventListener('DOMContentLoaded', () => {
    renderProductos();
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
        empty.innerHTML = '<div style="font-size:32px;margin-bottom:8px;opacity:0.3;">📦</div><div class="fw-600">Sin productos</div>';
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
                    <input type="checkbox" name="productos[${i}][activo]" value="1" ${p.activo!==false?'checked':''} onchange="upd(${i},'activo',this.checked)">
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
        alert('Agrega al menos un producto.');
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
