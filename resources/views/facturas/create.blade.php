@extends('layouts.app')

@section('title', 'Nueva Factura')
@section('page-title', '➕ Nueva Factura')
@section('page-subtitle', 'Crear comprobante fiscal CFDI 4.0')

@php
$breadcrumbs = [
    ['title' => 'Facturas', 'url' => route('facturas.index')],
    ['title' => 'Nueva Factura']
];
@endphp

@section('content')

<form method="POST" action="{{ route('facturas.store') }}" id="formFactura">
    @csrf

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

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
                                        data-credito="{{ $cliente->dias_credito }}"
                                        {{ ($clientePreseleccionado && $clientePreseleccionado->id == $cliente->id) ? 'selected' : '' }}>
                                    {{ $cliente->nombre }} — {{ $cliente->rfc }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div id="infoCliente" style="display: none;">
                        <div style="background: var(--color-gray-50); border: 1.5px solid var(--color-gray-200); border-radius: var(--radius-md); padding: 12px 16px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                                <div>
                                    <span class="text-muted">RFC: </span>
                                    <span class="text-mono fw-600" id="infoRFC"></span>
                                </div>
                                <div>
                                    <span class="text-muted">Régimen: </span>
                                    <span id="infoRegimen"></span>
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
                        <label class="form-label">Fecha de Emisión <span class="req">*</span></label>
                        <input type="date" name="fecha_emision" class="form-control"
                               value="{{ old('fecha_emision', now()->format('Y-m-d')) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Forma de Pago <span class="req">*</span></label>
                        <select name="forma_pago" class="form-control" required>
                            <option value="01">01 - Efectivo</option>
                            <option value="02">02 - Cheque nominativo</option>
                            <option value="03" selected>03 - Transferencia electrónica</option>
                            <option value="04">04 - Tarjeta de crédito</option>
                            <option value="28">28 - Tarjeta de débito</option>
                            <option value="99">99 - Por definir</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Método de Pago <span class="req">*</span></label>
                        <select name="metodo_pago" id="metodo_pago" class="form-control" required>
                            <option value="PUE" selected>PUE - Pago en una sola exhibición</option>
                            <option value="PPD">PPD - Pago en parcialidades o diferido</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Uso de CFDI <span class="req">*</span></label>
                        <select name="uso_cfdi" id="uso_cfdi" class="form-control" required>
                            <option value="G03">G03 - Gastos en general</option>
                            <option value="P01">P01 - Por definir</option>
                            <option value="S01">S01 - Sin efectos fiscales</option>
                            <option value="D01">D01 - Honorarios médicos</option>
                            <option value="D02">D02 - Gastos médicos</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="3"></textarea>
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
                            <span>IVA (16%)</span>
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
            <a href="{{ route('facturas.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Factura</button>
        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
let productoIndex = 0;
const catalogoProductos = @json($productos);

// Info cliente al cambiar select
document.getElementById('cliente_id').addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const info = document.getElementById('infoCliente');
    if (this.value) {
        document.getElementById('infoRFC').textContent     = opt.dataset.rfc;
        document.getElementById('infoRegimen').textContent = opt.dataset.regimen || 'N/A';
        document.getElementById('uso_cfdi').value          = opt.dataset.usoCfdi || 'G03';
        document.getElementById('metodo_pago').value       = parseInt(opt.dataset.credito) > 0 ? 'PPD' : 'PUE';
        info.style.display = 'block';
    } else {
        info.style.display = 'none';
    }
});

function agregarProducto() {
    document.getElementById('emptyProductos').style.display = 'none';
    const i = productoIndex++;
    const opciones = catalogoProductos.map(p =>
        `<option value="${p.id}" data-precio="${p.precio_venta}" data-nombre="${p.nombre}">${p.codigo} — ${p.nombre}</option>`
    ).join('');

    const tr = document.createElement('tr');
    tr.id = `prod-${i}`;
    tr.innerHTML = `
        <td>
            <select class="form-control" style="font-size: 13px; margin-bottom: 6px;"
                    onchange="seleccionarProducto(${i}, this)">
                <option value="">Seleccionar del catálogo...</option>
                ${opciones}
            </select>
            <input type="text" name="productos[${i}][descripcion]"
                   placeholder="Descripción *" class="form-control" style="font-size: 13px;" required>
        </td>
        <td class="td-center">
            <input type="number" name="productos[${i}][cantidad]"
                   class="form-control" style="text-align: center; width: 70px;"
                   value="1" min="0.01" step="0.01" onchange="calcularTotales()" required>
        </td>
        <td class="td-right">
            <input type="number" name="productos[${i}][valor_unitario]"
                   class="form-control" style="text-align: right; width: 100px;"
                   min="0" step="0.01" onchange="calcularTotales()" required>
        </td>
        <td class="td-right">
            <input type="number" name="productos[${i}][descuento]"
                   class="form-control" style="text-align: right; width: 80px;"
                   value="0" min="0" step="0.01" onchange="calcularTotales()">
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
}

function seleccionarProducto(i, select) {
    if (!select.value) return;
    const opt = select.options[select.selectedIndex];
    const row = document.getElementById(`prod-${i}`);
    row.querySelector('[name*="[descripcion]"]').value      = opt.dataset.nombre;
    row.querySelector('[name*="[valor_unitario]"]').value   = parseFloat(opt.dataset.precio).toFixed(2);
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
    let subtotal = 0, descuento = 0;
    document.querySelectorAll('#productosContainer tr').forEach((tr, idx) => {
        const cantidad = parseFloat(tr.querySelector('[name*="[cantidad]"]')?.value) || 0;
        const precio   = parseFloat(tr.querySelector('[name*="[valor_unitario]"]')?.value) || 0;
        const desc     = parseFloat(tr.querySelector('[name*="[descuento]"]')?.value) || 0;
        const importe  = cantidad * precio;
        subtotal  += importe;
        descuento += desc;
        const imp = tr.querySelector('[id^="importe-"]');
        if (imp) imp.textContent = fmt(importe);
    });
    const iva   = (subtotal - descuento) * 0.16;
    const total = subtotal - descuento + iva;
    document.getElementById('subtotalDisplay').textContent  = fmt(subtotal);
    document.getElementById('descuentoDisplay').textContent = '−' + fmt(descuento);
    document.getElementById('ivaDisplay').textContent       = fmt(iva);
    document.getElementById('totalDisplay').textContent     = fmt(total);
    document.getElementById('rowDescuento').style.display   = descuento > 0 ? 'flex' : 'none';
}

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