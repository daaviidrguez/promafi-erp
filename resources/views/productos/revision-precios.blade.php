@extends('layouts.app')
@section('title', 'Revisión de precios de venta')
@section('page-title', '💲 Revisión de precios')
@section('page-subtitle', 'Compra #'.$compraId)

@php
$breadcrumbs = [
    ['title' => 'Compras', 'url' => route('compras.index')],
    ['title' => 'Compra', 'url' => route('compras.show', $compraId)],
    ['title' => 'Revisión de precios'],
];
$motivos = [
    'producto_nuevo_cfdi' => 'Producto nuevo (CFDI)',
    'aumento_costo' => 'Aumento de costo ≥ 10%',
    'primer_costo' => 'Primer costo registrado',
    'precio_venta_igual_costo_compra' => 'Precio de venta = costo unitario de la compra',
];
@endphp

@section('content')

@if(session('success'))
<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
@endif
@if(session('error'))
<div class="alert alert-danger" style="margin-bottom:16px;">{{ session('error') }}</div>
@endif

<div class="card" style="margin-bottom:16px;">
    <div class="card-body" style="font-size:14px;color:var(--color-gray-600);">
        <strong>Costo unitario del CFDI</strong> vs costo de referencia del producto (promedio o costo).
        <strong>Precio sugerido</strong> = nuevo costo × (1 + margen % / 100). Usted elige el precio final y qué filas aplicar.
    </div>
</div>

<form method="POST" action="{{ route('productos.revision-precios.aplicar') }}" id="formRevisionPrecios">
    @csrf
    <input type="hidden" name="factura_compra_id" value="{{ $compraId }}">

    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <div class="card-title">Opciones al guardar</div>
        </div>
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:16px;align-items:center;">
            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                <input type="checkbox" name="actualizar_ultimo_costo" value="1" checked>
                Actualizar «último costo» del producto con el costo unitario del CFDI
            </label>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px;">
        <div class="card-header">
            <div class="card-title">Cambio masivo de margen</div>
        </div>
        <div class="card-body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div>
                <label class="info-label" for="margenMasivo">Margen % (sobre nuevo costo)</label>
                <input type="number" step="0.01" id="margenMasivo" class="form-control" style="width:140px;" placeholder="p. ej. 35">
            </div>
            <button type="button" class="btn btn-light" id="btnMargenSeleccionados">Aplicar margen a filas marcadas</button>
            <button type="button" class="btn btn-light" id="btnMargenTodos">Aplicar margen a todas las filas</button>
        </div>
    </div>

    <div class="card">
        <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;">
            <div class="card-title">Productos</div>
            <a href="{{ route('compras.show', $compraId) }}" class="btn btn-light btn-sm">← Volver a la compra</a>
        </div>
        <div class="table-container" style="border:none;box-shadow:none;">
            <table>
                <thead>
                    <tr>
                        <th class="td-center" style="width:40px;">Aplicar</th>
                        <th class="td-center" style="width:40px;">Masivo</th>
                        <th>Producto</th>
                        <th class="td-right">Costo ant.</th>
                        <th class="td-right">Nuevo costo</th>
                        <th class="td-right">P. venta actual</th>
                        <th class="td-right">Margen %</th>
                        <th class="td-right">P. sugerido</th>
                        <th class="td-right">P. final</th>
                        <th>Motivo</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($items as $idx => $row)
                    @php
                        $pid = (int) $row['producto_id'];
                        $nuevo = (float) $row['nuevo_costo'];
                        $margen = (float) $row['margen_porcentaje'];
                        $sugerido = $nuevo > 0 ? round($nuevo * (1 + $margen / 100), 2) : 0;
                    @endphp
                    <tr data-row-index="{{ $idx }}" data-nuevo="{{ $nuevo }}">
                        <td class="td-center">
                            <input type="checkbox" name="filas[{{ $idx }}][aplicar]" value="1">
                        </td>
                        <td class="td-center">
                            <input type="checkbox" class="chk-masivo" data-row="{{ $idx }}">
                        </td>
                        <td>
                            <input type="hidden" name="filas[{{ $idx }}][producto_id]" value="{{ $pid }}">
                            <span style="font-weight:700;">{{ $row['codigo'] }}</span>
                            <div style="font-size:13px;color:var(--color-gray-600);">{{ $row['nombre'] }}</div>
                        </td>
                        <td class="td-right text-mono">{{ number_format($row['costo_anterior'], 4) }}</td>
                        <td class="td-right text-mono">{{ number_format($nuevo, 4) }}</td>
                        <td class="td-right text-mono">{{ number_format($row['precio_venta_actual'], 2) }}</td>
                        <td class="td-right">
                            <input type="number" step="0.01" class="form-control input-margen" data-row="{{ $idx }}" value="{{ $margen }}" style="width:100px;text-align:right;margin-left:auto;">
                        </td>
                        <td class="td-right text-mono celda-sugerido" data-row="{{ $idx }}">{{ number_format($sugerido, 2) }}</td>
                        <td class="td-right">
                            <input type="number" step="0.01" min="0" class="form-control input-precio-final" data-row="{{ $idx }}" name="filas[{{ $idx }}][precio_final]" value="{{ $sugerido }}" style="width:120px;text-align:right;margin-left:auto;">
                        </td>
                        <td style="font-size:13px;">{{ $motivos[$row['motivo']] ?? $row['motivo'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body" style="display:flex;gap:12px;flex-wrap:wrap;">
            <button type="submit" class="btn btn-primary">Guardar cambios en productos marcados</button>
            <a href="{{ route('compras.show', $compraId) }}" class="btn btn-light">Volver sin guardar precios</a>
        </div>
    </div>
</form>

@push('scripts')
<script>
(function () {
    function parseNum(el) {
        const v = parseFloat(String(el.value).replace(',', '.'));
        return Number.isFinite(v) ? v : 0;
    }
    function round2(n) {
        return Math.round(n * 100) / 100;
    }
    function recalcRow(idx) {
        const tr = document.querySelector('tr[data-row-index="' + idx + '"]');
        if (!tr) return;
        const nuevo = parseFloat(tr.getAttribute('data-nuevo')) || 0;
        const margenInput = tr.querySelector('.input-margen[data-row="' + idx + '"]');
        const precioInput = tr.querySelector('.input-precio-final[data-row="' + idx + '"]');
        const sugeridoCell = document.querySelector('.celda-sugerido[data-row="' + idx + '"]');
        if (!margenInput || !precioInput || !sugeridoCell) return;
        const m = parseNum(margenInput);
        const sugerido = nuevo > 0 ? round2(nuevo * (1 + m / 100)) : 0;
        sugeridoCell.textContent = sugerido.toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        precioInput.value = String(sugerido.toFixed(2));
    }
    document.querySelectorAll('.input-margen').forEach(function (inp) {
        inp.addEventListener('input', function () {
            recalcRow(inp.getAttribute('data-row'));
        });
    });
    function aplicarMargenMasivo(soloMarcados) {
        const margenEl = document.getElementById('margenMasivo');
        const m = parseNum(margenEl);
        if (!margenEl || margenEl.value === '') {
            alert('Indique el margen % a aplicar.');
            return;
        }
        document.querySelectorAll('tr[data-row-index]').forEach(function (tr) {
            const idx = tr.getAttribute('data-row-index');
            if (soloMarcados) {
                const chk = tr.querySelector('.chk-masivo[data-row="' + idx + '"]');
                if (!chk || !chk.checked) return;
            }
            const margenInput = tr.querySelector('.input-margen[data-row="' + idx + '"]');
            if (margenInput) {
                margenInput.value = String(m.toFixed(2));
                recalcRow(idx);
            }
        });
    }
    const btnSel = document.getElementById('btnMargenSeleccionados');
    const btnTodos = document.getElementById('btnMargenTodos');
    if (btnSel) btnSel.addEventListener('click', function () { aplicarMargenMasivo(true); });
    if (btnTodos) btnTodos.addEventListener('click', function () { aplicarMargenMasivo(false); });
})();
</script>
@endpush
@endsection
