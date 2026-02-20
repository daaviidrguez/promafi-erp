@extends('layouts.app')
@section('title', 'Nueva Remisión')
@section('page-title', '🚚 Nueva Remisión')
@section('page-subtitle', 'Documento de entrega de mercancía')

@php
$breadcrumbs = [
    ['title' => 'Remisiones', 'url' => route('remisiones.index')],
    ['title' => 'Nueva'],
];
@endphp

@section('content')

<form action="{{ route('remisiones.store') }}" method="POST" id="remisionForm">
@csrf

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Datos</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Folio</label>
                    <input type="text" value="{{ $folio }}" readonly class="form-control text-mono fw-bold" style="background:var(--color-gray-100);">
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha <span class="req">*</span></label>
                    <input type="date" name="fecha" value="{{ old('fecha', date('Y-m-d')) }}" required class="form-control">
                    @error('fecha')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        <div class="card card-search">
            <div class="card-header"><div class="card-title">👥 Cliente <span class="req">*</span></div></div>
            <div class="card-body">
                <div class="form-group search-box">
                    <input type="text" id="buscarCliente" placeholder="Buscar cliente por nombre, RFC..." autocomplete="off" class="form-control">
                    <input type="hidden" name="cliente_id" id="cliente_id" required>
                    <div id="clienteResults" class="autocomplete-results"></div>
                </div>
                <div id="clienteInfo" style="display:none;margin-top:12px;padding:12px;background:var(--color-gray-50);border-radius:var(--radius-md);">
                    <span class="fw-600" id="clienteNombre"></span>
                    <span class="text-muted" id="clienteRfc"></span>
                    <button type="button" onclick="limpiarCliente()" class="btn btn-light btn-sm" style="margin-left:8px;">Cambiar</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📍 Dirección de entrega</div></div>
            <div class="card-body">
                <textarea name="direccion_entrega" class="form-control" rows="3" placeholder="Opcional. Si se deja vacío se puede usar el domicilio fiscal del cliente.">{{ old('direccion_entrega') }}</textarea>
                @error('direccion_entrega')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
            </div>
        </div>

        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">📦 Productos / Partidas</div>
                <button type="button" onclick="agregarManual()" class="btn btn-primary btn-sm">➕ Agregar línea</button>
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
                                <th>Código</th>
                                <th>Descripción</th>
                                <th class="td-center">Cant.</th>
                                <th class="td-center">Unidad</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="productosBody">
                            <tr id="emptyRow"><td colspan="5" class="text-center text-muted" style="padding:24px;">Sin partidas. Busca producto o agrega línea manual.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones') }}</textarea>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">ℹ️ Resumen</div></div>
            <div class="card-body">
                <p class="text-muted" style="font-size:13px;">La remisión documenta la salida de mercancía hacia el cliente. Puedes marcar después como <strong>Enviada</strong> y luego <strong>Entregada</strong>.</p>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="{{ route('remisiones.index') }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">✓ Guardar Remisión</button>
    </div>
</div>

</form>

@push('scripts')
<script>
let productos = [];
let timerCliente, timerProducto;

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('buscarCliente').addEventListener('input', function() {
        clearTimeout(timerCliente);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('clienteResults').classList.remove('show'); return; }
        timerCliente = setTimeout(() => buscarClientes(q), 280);
    });
    document.getElementById('buscarProducto').addEventListener('input', function() {
        clearTimeout(timerProducto);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('productoResults').classList.remove('show'); return; }
        timerProducto = setTimeout(() => buscarProductos(q), 280);
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box')) {
            document.getElementById('clienteResults').classList.remove('show');
            document.getElementById('productoResults').classList.remove('show');
        }
    });
});

function closeDropdown(id) { document.getElementById(id).classList.remove('show'); }

async function buscarClientes(q) {
    try {
        const r = await fetch(`{{ route('remisiones.buscar-clientes') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const box = document.getElementById('clienteResults');
        box.innerHTML = data.length ? data.map(c => `<div class="autocomplete-item" onclick='seleccionarCliente(${JSON.stringify(c).replace(/'/g, "\\'")})'><div class="autocomplete-item-name">${c.nombre}</div><div class="autocomplete-item-sub">${c.rfc || ''}</div></div>`).join('') : '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function seleccionarCliente(c) {
    document.getElementById('cliente_id').value = c.id;
    document.getElementById('buscarCliente').value = c.nombre;
    document.getElementById('clienteNombre').textContent = c.nombre;
    document.getElementById('clienteRfc').textContent = c.rfc ? ' RFC: ' + c.rfc : '';
    document.getElementById('clienteInfo').style.display = 'block';
    closeDropdown('clienteResults');
}

function limpiarCliente() {
    document.getElementById('cliente_id').value = '';
    document.getElementById('buscarCliente').value = '';
    document.getElementById('clienteInfo').style.display = 'none';
}

async function buscarProductos(q) {
    try {
        const r = await fetch(`{{ route('remisiones.buscar-productos') }}?q=${encodeURIComponent(q)}`);
        const data = await r.json();
        const box = document.getElementById('productoResults');
        box.innerHTML = data.length ? data.map(p => `<div class="autocomplete-item" onclick='agregarProducto(${JSON.stringify(p).replace(/'/g, "\\'")})'><div class="autocomplete-item-name">${p.nombre}</div><div class="autocomplete-item-sub">${p.codigo || ''} — ${p.unidad}</div></div>`).join('') : '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
        box.classList.add('show');
    } catch(e) { console.error(e); }
}

function agregarProducto(p) {
    if (productos.some(x => x.id && x.id === p.id)) { alert('Ya está en la lista'); return; }
    productos.push({ id: p.id, codigo: p.codigo || '', nombre: p.nombre, cantidad: 1, unidad: p.unidad || 'PZA', manual: false });
    document.getElementById('buscarProducto').value = '';
    closeDropdown('productoResults');
    renderProductos();
}

function agregarManual() {
    productos.push({ id: null, codigo: '', nombre: '', cantidad: 1, unidad: 'PZA', manual: true });
    renderProductos();
}

function upd(i, field, val) {
    productos[i][field] = val;
    if (field === 'nombre') {
        const inp = document.querySelector(`input[name="productos[${i}][descripcion]"]`);
        if (inp) inp.value = val;
    }
    renderProductos();
}

function quitarProducto(i) { productos.splice(i, 1); renderProductos(); }

function renderProductos() {
    const tbody = document.getElementById('productosBody');
    if (!productos.length) {
        tbody.innerHTML = '<tr id="emptyRow"><td colspan="5" class="text-center text-muted" style="padding:24px;">Sin partidas. Busca producto o agrega línea manual.</td></tr>';
        return;
    }
    tbody.innerHTML = productos.map((p, i) => `
        <tr>
            <td class="text-mono">${p.manual ? '<input type="text" class="form-control" style="font-size:13px;min-width:80px;" placeholder="Código" name="productos['+i+'][codigo]" value="'+(p.codigo||'')+'" readonly>' : (p.codigo || '—')}
                <input type="hidden" name="productos[${i}][producto_id]" value="${p.id || ''}">
            </td>
            <td>${p.manual ? '<input type="text" value="'+(p.nombre||'').replace(/"/g,'&quot;')+'" onchange="upd('+i+',\'nombre\',this.value)" placeholder="Descripción" class="form-control" style="font-size:13px;" name="productos['+i+'][descripcion]" required>' : '<div class="fw-600">'+(p.nombre||'').replace(/</g,'&lt;')+'</div><input type="hidden" name="productos['+i+'][descripcion]" value="'+(p.nombre||'').replace(/"/g,'&quot;')+'">'}</td>
            <td class="td-center"><input type="number" name="productos[${i}][cantidad]" value="${p.cantidad}" min="0.01" step="0.01" onchange="upd(${i},'cantidad',+this.value)" class="form-control" style="width:70px;text-align:center;"></td>
            <td class="td-center"><input type="text" name="productos[${i}][unidad]" value="${p.unidad}" onchange="upd(${i},'unidad',this.value)" class="form-control" style="width:60px;text-align:center;"></td>
            <td><button type="button" onclick="quitarProducto(${i})" class="btn btn-danger btn-icon btn-sm">✕</button></td>
        </tr>
    `).join('');
}

document.getElementById('remisionForm').addEventListener('submit', function(e) {
    if (!document.getElementById('cliente_id').value) { e.preventDefault(); alert('Selecciona un cliente'); return; }
    if (!productos.length) { e.preventDefault(); alert('Agrega al menos una partida'); return; }
});
</script>
@endpush

@endsection
