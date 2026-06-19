@extends('layouts.app')
@section('title', 'Editar '.$entrada->folio)
@section('page-title', '✏️ '.$entrada->folio)

@php
$breadcrumbs = [
    ['title' => 'Entradas anticipadas', 'url' => route('entradas-anticipadas.index')],
    ['title' => $entrada->folio, 'url' => route('entradas-anticipadas.show', $entrada->id)],
    ['title' => 'Editar'],
];
$lineasPrecargadas = $entrada->detalles->map(fn ($d) => [
    'orden_compra_detalle_id' => $d->orden_compra_detalle_id,
    'producto_id' => $d->producto_id,
    'codigo' => $d->producto?->codigo,
    'codigo_proveedor' => $d->codigo_proveedor,
    'descripcion' => $d->descripcion,
    'cantidad_recibida' => (float) $d->cantidad_recibida,
    'precio_unitario_estimado' => (float) $d->precio_unitario_estimado,
    'descuento_porcentaje' => (float) $d->descuento_porcentaje,
    'tasa_iva' => $d->tasa_iva,
])->values()->all();
$ordenCompra = $entrada->ordenCompra;
$proveedorPrecargado = $entrada->proveedor?->only(['id', 'nombre']);
@endphp

@section('content')

<form action="{{ route('entradas-anticipadas.update', $entrada->id) }}" method="POST" id="eaForm">
@csrf
@method('PUT')
@if($ordenCompra)<input type="hidden" name="orden_compra_id" value="{{ $ordenCompra->id }}">@endif
<input type="hidden" name="proveedor_id" value="{{ $entrada->proveedor_id }}">

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label class="form-label">Fecha de recepción</label>
                    <input type="date" name="fecha_recepcion" value="{{ $entrada->fecha_recepcion->format('Y-m-d') }}" required class="form-control">
                </div>
                <div class="form-group">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" rows="2" class="form-control">{{ $entrada->observaciones }}</textarea>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="table-container" style="border:none;">
                <table>
                    <thead><tr><th>Producto</th><th class="td-center">Cant.</th><th class="td-right">Costo</th></tr></thead>
                    <tbody id="lineasBody"></tbody>
                </table>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <button type="submit" class="btn btn-primary w-full">💾 Guardar cambios</button>
                <a href="{{ route('entradas-anticipadas.show', $entrada->id) }}" class="btn btn-light w-full">← Cancelar</a>
            </div>
        </div>
    </div>
</div>
</form>

@endsection

@push('scripts')
<script>
let lineas = @json($lineasPrecargadas);
const desdeOrden = {{ $ordenCompra ? 'true' : 'false' }};

function renderLineas() {
    const tbody = document.getElementById('lineasBody');
    tbody.innerHTML = lineas.map((l, i) => `<tr>
        <td>${l.descripcion}<input type="hidden" name="productos[${i}][producto_id]" value="${l.producto_id}">
        <input type="hidden" name="productos[${i}][orden_compra_detalle_id]" value="${l.orden_compra_detalle_id||''}">
        <input type="hidden" name="productos[${i}][descripcion]" value="${l.descripcion}">
        <input type="hidden" name="productos[${i}][codigo_proveedor]" value="${l.codigo_proveedor||''}">
        <input type="hidden" name="productos[${i}][descuento_porcentaje]" value="${l.descuento_porcentaje||0}">
        <input type="hidden" name="productos[${i}][tasa_iva]" value="${l.tasa_iva??''}"></td>
        <td class="td-center"><input type="number" name="productos[${i}][cantidad_recibida]" value="${l.cantidad_recibida}" min="0.01" step="0.01" class="form-control" style="width:90px;"></td>
        <td class="td-right"><input type="number" name="productos[${i}][precio_unitario_estimado]" value="${l.precio_unitario_estimado}" min="0" step="0.01" class="form-control" style="width:100px;margin-left:auto;"></td>
    </tr>`).join('');
}
document.addEventListener('DOMContentLoaded', renderLineas);
</script>
@endpush
