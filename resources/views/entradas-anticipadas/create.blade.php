@extends('layouts.app')
@section('title', 'Nueva entrada anticipada')
@section('page-title', '📥 Nueva entrada anticipada')
@section('page-subtitle', $ordenCompra ? 'Desde orden '.$ordenCompra->folio : 'Recepción directa sin orden')

@php
$breadcrumbs = [
    ['title' => 'Entradas anticipadas', 'url' => route('entradas-anticipadas.index')],
    ['title' => 'Nueva'],
];
$lineasJson = json_encode($lineasPrecargadas);
@endphp

@section('content')

<form action="{{ route('entradas-anticipadas.store') }}" method="POST" id="eaForm">
@csrf
@if($ordenCompra)<input type="hidden" name="orden_compra_id" value="{{ $ordenCompra->id }}">@endif

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">📋 Datos</div></div>
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Folio (al guardar)</label>
                    <input type="text" value="{{ $folio }}" readonly class="form-control text-mono fw-bold" style="background:var(--color-gray-100);">
                </div>
                <div class="form-group">
                    <label class="form-label">Fecha de recepción <span class="req">*</span></label>
                    <input type="date" name="fecha_recepcion" value="{{ date('Y-m-d') }}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" rows="2" class="form-control"></textarea>
                </div>
            </div>
        </div>

        @if(!$ordenCompra)
        <div class="card card-search">
            <div class="card-header"><div class="card-title">🏭 Proveedor <span class="req">*</span></div></div>
            <div class="card-body">
                <div class="form-group search-box">
                    <input type="text" id="buscarProveedor" placeholder="Buscar proveedor..." autocomplete="off" class="form-control">
                    <input type="hidden" name="proveedor_id" id="proveedor_id" required>
                    <div id="proveedorResults" class="autocomplete-results"></div>
                </div>
                <div id="proveedorInfo" style="display:none;margin-top:12px;padding:12px;background:var(--color-gray-50);border-radius:var(--radius-md);">
                    <span class="fw-600" id="proveedorNombre"></span>
                    <button type="button" onclick="limpiarProveedor()" class="btn btn-light btn-sm" style="margin-left:8px;">Cambiar</button>
                </div>
            </div>
        </div>
        @else
        <input type="hidden" name="proveedor_id" id="proveedor_id" value="{{ $ordenCompra->proveedor_id }}">
        <div class="card">
            <div class="card-body">
                <strong>{{ $ordenCompra->proveedor_nombre }}</strong>
                <span class="text-muted text-mono"> · {{ $ordenCompra->proveedor_rfc }}</span>
                <span class="badge badge-info" style="margin-left:8px;">{{ $ordenCompra->folio }}</span>
            </div>
        </div>
        @endif

        <div class="card card-search">
            <div class="card-header">
                <div class="card-title">📦 Productos recibidos</div>
                @if(!$ordenCompra)<button type="button" onclick="agregarLinea()" class="btn btn-primary btn-sm">➕ Agregar</button>@endif
            </div>
            <div class="card-body" style="padding:0;">
                @if(!$ordenCompra)
                <div class="search-box" style="padding:16px;">
                    <input type="text" id="buscarProducto" placeholder="Buscar producto del catálogo..." autocomplete="off" class="form-control">
                    <div id="productoResults" class="autocomplete-results"></div>
                </div>
                @endif
                <div class="table-container" style="border:none;margin:0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th class="td-center">Cant.</th>
                                <th class="td-right">Costo s/IVA</th>
                                <th class="td-center">IVA</th>
                                <th class="td-right">Total</th>
                                @if(!$ordenCompra)<th></th>@endif
                            </tr>
                        </thead>
                        <tbody id="lineasBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Totales estimados</div></div>
            <div class="card-body">
                <p class="text-muted" style="font-size:12px;margin:0 0 12px;">Costo unitario <strong>sin IVA</strong>. El total incluye IVA (como el CFDI del proveedor).</p>
                <div class="totales-panel">
                    <div class="totales-row"><span>Subtotal</span><span class="monto text-mono" id="tSubtotal">$0.00</span></div>
                    <div class="totales-row descuento" id="rowDescuento" style="display:none;"><span>Descuento</span><span class="monto" id="tDescuento">−$0.00</span></div>
                    <div class="totales-row"><span>IVA</span><span class="monto text-mono" id="tIva">$0.00</span></div>
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto" id="tTotal">$0.00</span></div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <button type="submit" name="confirmar" value="0" class="btn btn-outline w-full">💾 Guardar borrador</button>
                <button type="submit" name="confirmar" value="1" class="btn btn-primary w-full">✅ Confirmar recepción</button>
                <a href="{{ $ordenCompra ? route('ordenes-compra.show', $ordenCompra->id) : route('entradas-anticipadas.index') }}" class="btn btn-light w-full">← Cancelar</a>
            </div>
        </div>
        <p class="text-muted" style="font-size:13px;line-height:1.45;">Al confirmar se registra inventario al costo unitario sin IVA. El total estimado incluye IVA para conciliar con el CFDI.</p>
    </div>
</div>
</form>

@endsection

@push('scripts')
<script>
let lineas = @json($lineasPrecargadas);
const desdeOrden = {{ $ordenCompra ? 'true' : 'false' }};
const proveedorPrecargado = @json($proveedorPrecargado);

function calcLinea(l) {
    const cant = parseFloat(l.cantidad_recibida) || 0;
    const precio = parseFloat(l.precio_unitario_estimado) || 0;
    const descPct = parseFloat(l.descuento_porcentaje) || 0;
    const sub = Math.round(cant * precio * 100) / 100;
    const desc = Math.round(sub * (descPct / 100) * 100) / 100;
    const base = Math.round((sub - desc) * 100) / 100;
    const iva = l.tasa_iva != null && l.tasa_iva !== '' ? Math.round(base * parseFloat(l.tasa_iva) * 100) / 100 : 0;
    const total = Math.round((base + iva) * 100) / 100;
    return { sub, desc, base, iva, total };
}

function etiquetaIva(tasa) {
    if (tasa == null || tasa === '') return 'Exento';
    return (parseFloat(tasa) * 100).toFixed(0) + '%';
}

function renderLineas() {
    const tbody = document.getElementById('lineasBody');
    if (!lineas.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted" style="padding:24px;">Sin líneas</td></tr>';
        calcTotales();
        return;
    }
    tbody.innerHTML = lineas.map((l, i) => {
        const imp = calcLinea(l);
        const pend = l.pendiente != null ? `<div class="text-muted" style="font-size:11px;">Pend. OC: ${l.pendiente}</div>` : '';
        const tasaHidden = `<input type="hidden" name="productos[${i}][tasa_iva]" value="${l.tasa_iva != null ? l.tasa_iva : ''}">`;
        return `<tr>
            <td>
                <input type="hidden" name="productos[${i}][producto_id]" value="${l.producto_id}">
                <input type="hidden" name="productos[${i}][orden_compra_detalle_id]" value="${l.orden_compra_detalle_id||''}">
                <input type="hidden" name="productos[${i}][descripcion]" value="${(l.descripcion||'').replace(/"/g,'&quot;')}">
                <input type="hidden" name="productos[${i}][codigo_proveedor]" value="${l.codigo_proveedor||''}">
                <input type="hidden" name="productos[${i}][descuento_porcentaje]" value="${l.descuento_porcentaje||0}">
                ${tasaHidden}
                <div class="fw-600">${l.descripcion||''}</div>
                <div class="text-mono text-muted" style="font-size:12px;">${l.codigo||''}</div>${pend}
            </td>
            <td class="td-center">
                <input type="number" name="productos[${i}][cantidad_recibida]" value="${l.cantidad_recibida}" min="0.01" step="0.01" class="form-control" style="width:90px;margin:0 auto;" onchange="upd(${i},'cantidad_recibida',this.value)">
            </td>
            <td class="td-right">
                ${desdeOrden ? `<input type="hidden" name="productos[${i}][precio_unitario_estimado]" value="${l.precio_unitario_estimado}"><span class="text-mono">$${parseFloat(l.precio_unitario_estimado).toFixed(2)}</span>` :
                `<input type="number" name="productos[${i}][precio_unitario_estimado]" value="${l.precio_unitario_estimado}" min="0" step="0.01" class="form-control" style="width:100px;margin-left:auto;" onchange="upd(${i},'precio_unitario_estimado',this.value)">`}
            </td>
            <td class="td-center"><span class="fw-600" style="font-size:13px;">${etiquetaIva(l.tasa_iva)}</span></td>
            <td class="td-right text-mono fw-600">$${imp.total.toFixed(2)}</td>
            ${!desdeOrden ? `<td><button type="button" class="btn btn-light btn-sm" onclick="quitar(${i})">✕</button></td>` : ''}
        </tr>`;
    }).join('');
    calcTotales();
}

function calcTotales() {
    let sub = 0, desc = 0, iva = 0;
    lineas.forEach(l => {
        const imp = calcLinea(l);
        sub += imp.sub;
        desc += imp.desc;
        iva += imp.iva;
    });
    document.getElementById('tSubtotal').textContent = '$' + sub.toFixed(2);
    document.getElementById('tDescuento').textContent = '−$' + desc.toFixed(2);
    document.getElementById('tIva').textContent = '$' + iva.toFixed(2);
    document.getElementById('tTotal').textContent = '$' + ((sub - desc) + iva).toFixed(2);
    document.getElementById('rowDescuento').style.display = desc > 0 ? 'flex' : 'none';
}

function upd(i, f, v) { lineas[i][f] = v; renderLineas(); }
function quitar(i) { lineas.splice(i,1); renderLineas(); }
function agregarLinea() {
    lineas.push({ producto_id:'', descripcion:'', cantidad_recibida:1, precio_unitario_estimado:0, descuento_porcentaje:0, tasa_iva:0.16 });
    renderLineas();
}

async function buscarProveedores(q) {
    const r = await fetch(`{{ route('entradas-anticipadas.buscar-proveedores') }}?q=${encodeURIComponent(q)}`);
    const data = await r.json();
    const box = document.getElementById('proveedorResults');
    box.innerHTML = data.map(p => `<div class="autocomplete-item" onclick="selProv(${p.id},'${p.etiqueta.replace(/'/g,"\\'")}')"><div class="autocomplete-item-name">${p.etiqueta}</div></div>`).join('') || '<div class="autocomplete-item text-muted">Sin resultados</div>';
    box.classList.add('show');
}
function selProv(id, etiqueta) {
    document.getElementById('proveedor_id').value = id;
    document.getElementById('buscarProveedor').value = etiqueta;
    document.getElementById('proveedorNombre').textContent = etiqueta;
    document.getElementById('proveedorInfo').style.display = 'block';
    document.getElementById('proveedorResults').classList.remove('show');
}
function limpiarProveedor() {
    document.getElementById('proveedor_id').value = '';
    document.getElementById('buscarProveedor').value = '';
    document.getElementById('proveedorInfo').style.display = 'none';
}

async function buscarProductos(q) {
    const pid = document.getElementById('proveedor_id').value;
    const r = await fetch(`{{ route('entradas-anticipadas.buscar-productos') }}?q=${encodeURIComponent(q)}&proveedor_id=${pid}`);
    const data = await r.json();
    const box = document.getElementById('productoResults');
    box.innerHTML = data.map(p => `<div class="autocomplete-item" onclick='addProd(${JSON.stringify(p)})'><div class="autocomplete-item-name">${p.nombre}</div><div class="autocomplete-item-sub text-mono">${p.codigo}</div></div>`).join('') || '<div class="autocomplete-item text-muted">Sin resultados</div>';
    box.classList.add('show');
}
function addProd(p) {
    if (lineas.some(l => l.producto_id == p.id)) { alert('Ya está en la lista'); return; }
    lineas.push({
        producto_id: p.id,
        codigo: p.codigo,
        descripcion: p.nombre,
        codigo_proveedor: p.codigo_proveedor,
        cantidad_recibida: 1,
        precio_unitario_estimado: p.precio || 0,
        descuento_porcentaje: 0,
        tasa_iva: p.tasa_iva != null ? p.tasa_iva : null
    });
    document.getElementById('buscarProducto').value = '';
    document.getElementById('productoResults').classList.remove('show');
    renderLineas();
}

document.addEventListener('DOMContentLoaded', () => {
    renderLineas();
    if (proveedorPrecargado) selProv(proveedorPrecargado.id, proveedorPrecargado.nombre);
    const bp = document.getElementById('buscarProveedor');
    if (bp) bp.addEventListener('input', e => { if (e.target.value.length>=2) buscarProveedores(e.target.value); });
    const bpr = document.getElementById('buscarProducto');
    if (bpr) bpr.addEventListener('input', e => { if (e.target.value.length>=2) buscarProductos(e.target.value); });
});
</script>
@endpush
