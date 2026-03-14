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
@endphp

@section('content')

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif

<form method="POST" action="{{ route('facturas.update', $factura) }}" id="formFactura">
    @csrf
    @method('PUT')

    <div class="responsive-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

        {{-- Columna izquierda --}}
        <div>

            {{-- Datos del Cliente --}}
            <div class="card">
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
                                        {{ old('cliente_id', $factura->cliente_id) == $cliente->id ? 'selected' : '' }}>
                                    {{ $cliente->nombre }} — {{ $cliente->rfc }}
                                </option>
                            @endforeach
                        </select>
                    </div>

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
                    <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 35%;">Descripción</th>
                                    <th class="td-center" style="width: 12%;">Cantidad</th>
                                    <th class="td-right" style="width: 18%;">Precio Unit.</th>
                                    <th class="td-right" style="width: 15%;">Descuento</th>
                                    <th class="td-right" style="width: 15%;">Importe</th>
                                    <th style="width: 5%;"></th>
                                </tr>
                            </thead>
                            <tbody id="productosContainer"></tbody>
                        </table>
                    </div>

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
            <a href="{{ route('facturas.show', $factura) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Cambios</button>
        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
let productoIndex = 0;
const catalogoProductos = @json($productos);
const detallesIniciales = @json($detallesIniciales);

function actualizarInfoCliente() {
    const sel = document.getElementById('cliente_id');
    const opt = sel.options[sel.selectedIndex];
    const info = document.getElementById('infoCliente');
    if (sel.value) {
        document.getElementById('infoRFC').textContent = opt.dataset.rfc;
        document.getElementById('infoRegimen').textContent = opt.dataset.regimen || 'N/A';
        document.getElementById('uso_cfdi').value = opt.dataset.usoCfdi || 'G03';
        document.getElementById('forma_pago').value = opt.dataset.formaPago || '03';
        document.getElementById('metodo_pago').value = parseInt(opt.dataset.credito) > 0 ? 'PPD' : 'PUE';
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
    }
}

document.getElementById('cliente_id').addEventListener('change', actualizarInfoCliente);

function agregarProducto(datos = null) {
    document.getElementById('emptyProductos').style.display = 'none';
    const i = productoIndex++;
    const opciones = catalogoProductos.map(p => {
        const tasa = p.tipo_factor === 'Exento' ? 0 : (parseFloat(p.tasa_iva) || 0);
        return `<option value="${p.id}" data-precio="${p.precio_venta}" data-nombre="${p.nombre}" data-tasa-iva="${tasa}">${p.codigo} — ${p.nombre}</option>`;
    }).join('');

    const desc = datos ? datos.nombre : '';
    const prodId = datos ? (datos.producto_id || '') : '';
    const cant = datos ? datos.cantidad : 1;
    const precio = datos ? datos.valor_unitario : '';
    const descuento = datos ? datos.descuento : 0;
    let selectedProd = '';
    if (prodId && catalogoProductos.some(p => p.id == prodId)) {
        selectedProd = `value="${prodId}"`;
    }

    const tr = document.createElement('tr');
    tr.id = `prod-${i}`;
    tr.innerHTML = `
        <td>
            <select class="form-control form-control-producto" style="font-size: 13px; margin-bottom: 6px;" data-row="${i}"
                    onchange="seleccionarProducto(${i}, this)">
                <option value="">Seleccionar del catálogo...</option>
                ${opciones}
            </select>
            <input type="hidden" name="productos[${i}][producto_id]" class="input-producto-id" value="${prodId}">
            <input type="text" name="productos[${i}][descripcion]"
                   placeholder="Descripción *" class="form-control" style="font-size: 13px;" value="${desc}" required>
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

    if (prodId) {
        const sel = tr.querySelector('select.form-control-producto');
        sel.value = prodId;
    }
    calcularTotales();
}

function seleccionarProducto(i, select) {
    if (!select.value) return;
    const opt = select.options[select.selectedIndex];
    const row = document.getElementById(`prod-${i}`);
    row.querySelector('[name*="[descripcion]"]').value = opt.dataset.nombre;
    row.querySelector('[name*="[valor_unitario]"]').value = parseFloat(opt.dataset.precio).toFixed(2);
    row.querySelector('.input-producto-id').value = select.value;
    calcularTotales();
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
        const sel = tr.querySelector('select.form-control-producto');
        const tasa = (sel && sel.value && sel.options[sel.selectedIndex]) ? parseFloat(sel.options[sel.selectedIndex].dataset.tasaIva || 0) : 0;
        iva += baseImpuesto * tasa;
        const imp = tr.querySelector('[id^="importe-"]');
        if (imp) imp.textContent = fmt(importe);
    });
    document.getElementById('subtotalDisplay').textContent = fmt(subtotal);
    document.getElementById('descuentoDisplay').textContent = '−' + fmt(descuento);
    document.getElementById('ivaDisplay').textContent = fmt(iva);
    document.getElementById('totalDisplay').textContent = fmt(subtotal - descuento + iva);
    document.getElementById('rowDescuento').style.display = descuento > 0 ? 'flex' : 'none';
}

document.addEventListener('DOMContentLoaded', function() {
    if (detallesIniciales && detallesIniciales.length > 0) {
        detallesIniciales.forEach(d => agregarProducto(d));
    }
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
</script>
@endpush
