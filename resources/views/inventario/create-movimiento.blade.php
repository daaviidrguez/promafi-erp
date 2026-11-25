@extends('layouts.app')
@section('title', 'Entrada / Salida manual')
@section('page-title', '➕ Entrada o salida manual')
@section('page-subtitle', 'Registrar movimiento de inventario')

@php
$breadcrumbs = [['title' => 'Inventario', 'url' => route('inventario.index')], ['title' => 'Movimientos', 'url' => route('inventario.movimientos')], ['title' => 'Nuevo']];
$productoInicial = null;
if ($productoSeleccionado) {
    $productoInicial = [
        'id' => $productoSeleccionado->id,
        'codigo' => $productoSeleccionado->codigo,
        'nombre' => $productoSeleccionado->nombre,
        'stock' => (float) $productoSeleccionado->stock,
        'costo' => (float) ($productoSeleccionado->costo ?? 0),
        'costo_promedio' => \App\Models\InventarioMovimiento::costoValuacionProducto($productoSeleccionado),
    ];
}
@endphp

@section('content')
<form method="POST" action="{{ route('inventario.store-movimiento') }}" id="formMovimientoManual">
    @csrf
    <div class="card">
        <div class="card-header"><div class="card-title">📦 Movimiento</div></div>
        <div class="card-body">
            @include('partials.producto-search-field', [
                'required' => true,
                'showStock' => false,
                'productoIdValue' => old('producto_id', $productoId ?? ''),
                'productoNombreValue' => $productoSeleccionado
                    ? $productoSeleccionado->codigo . ' — ' . $productoSeleccionado->nombre
                    : '',
            ])
            @error('producto_id')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror

            <div id="visorProducto" class="card" style="margin:16px 0 0;background:var(--color-gray-50, #f8fafc);border:1px solid var(--color-gray-200, #e2e8f0);display:none;">
                <div class="card-body" style="padding:14px 16px;">
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:12px;">
                        <div>
                            <div class="form-hint" style="margin:0;">Existencia actual</div>
                            <div id="visorStock" class="text-mono fw-600" style="font-size:1.15rem;">—</div>
                        </div>
                        <div>
                            <div class="form-hint" style="margin:0;">Costo promedio actual</div>
                            <div id="visorCostoPromedio" class="text-mono fw-600" style="font-size:1.15rem;">—</div>
                        </div>
                        <div>
                            <div class="form-hint" style="margin:0;">Existencia resultante</div>
                            <div id="visorStockResultante" class="text-mono fw-600" style="font-size:1.15rem;">—</div>
                        </div>
                        <div>
                            <div class="form-hint" style="margin:0;" id="visorCostoResultanteLabel">Costo promedio resultante</div>
                            <div id="visorCostoResultante" class="text-mono fw-600" style="font-size:1.15rem;">—</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="movimiento-manual-campos" style="display:grid;grid-template-columns:1.2fr 0.9fr 1fr;gap:16px;margin-top:16px;align-items:start;">
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Tipo <span class="req">*</span></label>
                    <select name="tipo" id="tipoMovimiento" class="form-control" required>
                        <option value="entrada_manual" {{ old('tipo', 'entrada_manual') == 'entrada_manual' ? 'selected' : '' }}>Entrada manual</option>
                        <option value="salida_manual" {{ old('tipo') == 'salida_manual' ? 'selected' : '' }}>Salida manual</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Cantidad <span class="req">*</span></label>
                    <input type="number" name="cantidad" id="cantidadMovimiento" class="form-control" value="{{ old('cantidad') }}" min="0.01" step="0.01" required>
                    @error('cantidad')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
                <div class="form-group" id="grupoCostoUnitario" style="margin-bottom:0;">
                    <label class="form-label" id="labelCostoUnitario">Costo unitario <span class="req">*</span></label>
                    <input type="number" name="costo_unitario" id="costoUnitario" class="form-control" value="{{ old('costo_unitario') }}" min="0" step="0.01" placeholder="0.00">
                    @error('costo_unitario')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
            </div>
            <span class="form-hint" id="hintCostoUnitario" style="display:block;margin-top:8px;">Actualiza el costo promedio (no el costo de catálogo). Misma lógica que una recepción de compra.</span>

            <style>
                @media (max-width: 768px) {
                    .movimiento-manual-campos {
                        grid-template-columns: 1fr !important;
                    }
                }
            </style>

            <div class="form-group">
                <label class="form-label">Observaciones</label>
                <input type="text" name="observaciones" class="form-control" value="{{ old('observaciones') }}" placeholder="Opcional" maxlength="500">
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="{{ route('inventario.movimientos') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Registrar movimiento</button>
        </div>
    </div>
</form>
@endsection

@push('scripts')
@include('partials.producto-search-js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const money = (n) => '$' + Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const qty = (n) => Number(n || 0).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    const visor = document.getElementById('visorProducto');
    const visorStock = document.getElementById('visorStock');
    const visorCostoPromedio = document.getElementById('visorCostoPromedio');
    const visorStockResultante = document.getElementById('visorStockResultante');
    const visorCostoResultante = document.getElementById('visorCostoResultante');
    const visorCostoResultanteLabel = document.getElementById('visorCostoResultanteLabel');
    const tipoEl = document.getElementById('tipoMovimiento');
    const cantidadEl = document.getElementById('cantidadMovimiento');
    const costoEl = document.getElementById('costoUnitario');
    const labelCosto = document.getElementById('labelCostoUnitario');
    const hintCosto = document.getElementById('hintCostoUnitario');

    let productoActual = null;

    function esEntrada() {
        return tipoEl.value === 'entrada_manual';
    }

    function syncTipoUi() {
        if (esEntrada()) {
            labelCosto.innerHTML = 'Costo unitario <span class="req">*</span>';
            hintCosto.textContent = 'Actualiza el costo promedio (no el costo de catálogo). Misma lógica que una recepción de compra.';
            costoEl.readOnly = false;
            costoEl.required = true;
            visorCostoResultanteLabel.textContent = 'Costo promedio resultante';
            if (productoActual && (costoEl.value === '' || costoEl.value == null)) {
                costoEl.value = Number(productoActual.costo_promedio || 0).toFixed(2);
            }
        } else {
            labelCosto.innerHTML = 'Costo unitario';
            hintCosto.textContent = 'Solo referencia: la salida se valúa al promedio vigente; el costo promedio del producto no cambia.';
            costoEl.readOnly = true;
            costoEl.required = false;
            visorCostoResultanteLabel.textContent = 'Costo promedio (sin cambio)';
            if (productoActual) {
                costoEl.value = Number(productoActual.costo_promedio || 0).toFixed(2);
            }
        }
        actualizarVisor();
    }

    function actualizarVisor() {
        if (!productoActual) {
            visor.style.display = 'none';
            return;
        }

        visor.style.display = 'block';
        const stock = Number(productoActual.stock || 0);
        const promedio = Number(productoActual.costo_promedio || 0);
        const cantidad = Number(cantidadEl.value || 0);
        const costoUnitario = Number(costoEl.value || 0);

        visorStock.textContent = qty(stock);
        visorCostoPromedio.textContent = money(promedio);

        let stockResultante = stock;
        if (cantidad > 0) {
            stockResultante = esEntrada() ? stock + cantidad : stock - cantidad;
        }
        visorStockResultante.textContent = qty(stockResultante);
        if (stockResultante < 0) {
            visorStockResultante.style.color = 'var(--color-danger)';
        } else {
            visorStockResultante.style.color = '';
        }

        if (esEntrada() && cantidad > 0) {
            const den = stock + cantidad;
            const nuevo = den > 0
                ? ((stock * promedio) + (cantidad * costoUnitario)) / den
                : costoUnitario;
            visorCostoResultante.textContent = money(nuevo);
        } else {
            visorCostoResultante.textContent = money(promedio);
        }
    }

    function onProductoSeleccionado(p) {
        productoActual = {
            id: p.id,
            codigo: p.codigo,
            nombre: p.nombre,
            stock: Number(p.stock || 0),
            costo: Number(p.costo || 0),
            costo_promedio: Number(p.costo_promedio != null ? p.costo_promedio : (p.costo || 0)),
        };
        // Sugerir promedio vigente al seleccionar (entrada) o fijarlo (salida).
        costoEl.value = Number(productoActual.costo_promedio || 0).toFixed(2);
        syncTipoUi();
    }

    function onProductoLimpiado() {
        productoActual = null;
        if (!costoEl.readOnly) {
            costoEl.value = '';
        }
        actualizarVisor();
    }

    const oldCosto = @json(old('costo_unitario'));

    ProductoSearch.init({
        showStock: false,
        onSelect: onProductoSeleccionado,
        onClear: onProductoLimpiado,
        initial: @json($productoInicial),
        applyInitial: true,
    });

    tipoEl.addEventListener('change', function () {
        if (productoActual) {
            costoEl.value = Number(productoActual.costo_promedio || 0).toFixed(2);
        }
        syncTipoUi();
    });
    cantidadEl.addEventListener('input', actualizarVisor);
    costoEl.addEventListener('input', actualizarVisor);

    if (oldCosto !== null && oldCosto !== '') {
        costoEl.value = oldCosto;
    }

    syncTipoUi();
});
</script>
@endpush
