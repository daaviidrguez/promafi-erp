@extends('layouts.app')
@section('title', 'Editar Remisión ' . $remision->folio)
@section('page-title', '✏️ Editar ' . $remision->folio)
@section('page-subtitle', $remision->cliente_nombre)

@php
$breadcrumbs = [
    ['title' => 'Remisiones', 'url' => route('remisiones.index')],
    ['title' => $remision->folio, 'url' => route('remisiones.show', $remision->id)],
    ['title' => 'Editar'],
];
@endphp

@section('content')

<form action="{{ route('remisiones.update', $remision->id) }}" method="POST" id="remisionForm">
@csrf
@method('PUT')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Datos</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Folio</label>
                    <input type="text" value="{{ $remision->folio }}" readonly class="form-control text-mono fw-bold" style="background:var(--color-gray-100);">
                </div>
                <div class="form-group">
                    <label class="form-label">Cliente</label>
                    <input type="text" value="{{ $remision->cliente_nombre }}" readonly class="form-control" style="background:var(--color-gray-100);">
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha <span class="req">*</span></label>
                    <input type="date" name="fecha" value="{{ old('fecha', $remision->fecha->format('Y-m-d')) }}" required class="form-control">
                    @error('fecha')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📍 Dirección de entrega</div></div>
            <div class="card-body">
                <textarea name="direccion_entrega" class="form-control" rows="3">{{ old('direccion_entrega', $remision->direccion_entrega) }}</textarea>
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
                            @foreach($remision->detalles as $i => $d)
                            <tr>
                                <td class="text-mono">{{ $d->codigo ?? '—' }}</td>
                                <td>
                                    <input type="hidden" name="productos[{{ $i }}][producto_id]" value="{{ $d->producto_id ?? '' }}">
                                    <input type="hidden" name="productos[{{ $i }}][descripcion]" value="{{ old('productos.'.$i.'.descripcion', $d->descripcion) }}">
                                    <div class="fw-600">{{ $d->descripcion }}</div>
                                </td>
                                <td class="td-center"><input type="number" name="productos[{{ $i }}][cantidad]" value="{{ old('productos.'.$i.'.cantidad', $d->cantidad) }}" min="0.01" step="0.01" class="form-control" style="width:70px;text-align:center;"></td>
                                <td class="td-center"><input type="text" name="productos[{{ $i }}][unidad]" value="{{ old('productos.'.$i.'.unidad', $d->unidad) }}" class="form-control" style="width:60px;text-align:center;"></td>
                                <td><button type="button" onclick="quitarFila(this)" class="btn btn-danger btn-icon btn-sm">✕</button></td>
                            </tr>
                            @endforeach
                            @if($remision->detalles->isEmpty())
                            <tr id="emptyRow"><td colspan="5" class="text-center text-muted" style="padding:24px;">Sin partidas. Busca producto o agrega línea manual.</td></tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $remision->observaciones) }}</textarea>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
        <a href="{{ route('remisiones.show', $remision->id) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">✓ Guardar cambios</button>
    </div>
</div>

</form>

@push('scripts')
<script>
let timerProducto;
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('buscarProducto').addEventListener('input', function() {
        clearTimeout(timerProducto);
        const q = this.value.trim();
        if (q.length < 2) { document.getElementById('productoResults').classList.remove('show'); return; }
        timerProducto = setTimeout(() => buscarProductos(q), 280);
    });
    document.addEventListener('click', e => {
        if (!e.target.closest('.search-box')) document.getElementById('productoResults').classList.remove('show');
    });
});

function quitarFila(btn) {
    btn.closest('tr').remove();
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
    const tbody = document.getElementById('productosBody');
    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.remove();
    const i = tbody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="text-mono">${p.codigo || '—'}</td>
        <td><input type="hidden" name="productos[${i}][producto_id]" value="${p.id}"><input type="hidden" name="productos[${i}][descripcion]" value="${(p.nombre||'').replace(/"/g,'&quot;')}"><div class="fw-600">${(p.nombre||'').replace(/</g,'&lt;')}</div></td>
        <td class="td-center"><input type="number" name="productos[${i}][cantidad]" value="1" min="0.01" step="0.01" class="form-control" style="width:70px;text-align:center;"></td>
        <td class="td-center"><input type="text" name="productos[${i}][unidad]" value="${p.unidad||'PZA'}" class="form-control" style="width:60px;text-align:center;"></td>
        <td><button type="button" onclick="quitarFila(this)" class="btn btn-danger btn-icon btn-sm">✕</button></td>
    `;
    tbody.appendChild(tr);
    document.getElementById('buscarProducto').value = '';
    document.getElementById('productoResults').classList.remove('show');
}

function agregarManual() {
    const tbody = document.getElementById('productosBody');
    const emptyRow = document.getElementById('emptyRow');
    if (emptyRow) emptyRow.remove();
    const i = tbody.querySelectorAll('tr').length;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="text-mono">—</td>
        <td><input type="hidden" name="productos[${i}][producto_id]" value=""><input type="text" name="productos[${i}][descripcion]" placeholder="Descripción" class="form-control" style="font-size:13px;" required></td>
        <td class="td-center"><input type="number" name="productos[${i}][cantidad]" value="1" min="0.01" step="0.01" class="form-control" style="width:70px;text-align:center;"></td>
        <td class="td-center"><input type="text" name="productos[${i}][unidad]" value="PZA" class="form-control" style="width:60px;text-align:center;"></td>
        <td><button type="button" onclick="quitarFila(this)" class="btn btn-danger btn-icon btn-sm">✕</button></td>
    `;
    tbody.appendChild(tr);
}

document.getElementById('remisionForm').addEventListener('submit', function(e) {
    const rows = document.getElementById('productosBody').querySelectorAll('tr');
    if (!rows.length || (rows.length === 1 && rows[0].querySelector('td[colspan]'))) {
        e.preventDefault();
        alert('Agrega al menos una partida');
    }
});
</script>
@endpush

@endsection
