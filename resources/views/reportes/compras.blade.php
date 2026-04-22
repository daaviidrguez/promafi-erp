@extends('layouts.app')

@section('title', 'Reporte compras')
@section('page-title', '🛒 Compras')
@section('page-subtitle', 'Facturas de compra del período')

@php
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => route('reportes.fiscal')],
    ['title' => 'Compras']
];
$mesNombre = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$mes ?? 1];
@endphp

@section('content')

<div class="card">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div><strong>{{ $mesNombre }} {{ $año ?? now()->year }}</strong></div>
        <form id="formFiltrosCompras" method="GET" action="{{ route('reportes.compras') }}" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
            <select name="mes" class="form-control" style="width: auto; min-width: 0;">
                @foreach([
                    1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril', 5 => 'Mayo', 6 => 'Junio',
                    7 => 'Julio', 8 => 'Agosto', 9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre'
                ] as $m => $nombre)
                    <option value="{{ $m }}" {{ ($mes ?? now()->month) == $m ? 'selected' : '' }}>{{ $nombre }}</option>
                @endforeach
            </select>
            <select name="año" class="form-control" style="width: auto; min-width: 0;">
                @for($y = now()->year; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}" {{ ($año ?? now()->year) == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('modalExportCompras').classList.add('show')">Exportar</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Resumen</div>
    </div>
    <div class="card-body">
        <table class="table" style="max-width: 400px;">
            <tr>
                <td><strong>Total compras</strong></td>
                <td class="text-end">${{ number_format($totalCompras ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>Subtotal</td>
                <td class="text-end">${{ number_format($subtotalCompras ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>IVA acreditable</td>
                <td class="text-end">${{ number_format($ivaCompras ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>Facturas de compra</td>
                <td class="text-end">{{ $facturasCompra->count() ?? 0 }}</td>
            </tr>
        </table>
    </div>
</div>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Folio</th>
                <th>Fecha</th>
                <th>Proveedor</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($facturasCompra ?? [] as $fc)
            <tr>
                <td class="text-mono">
                    @php $fol = $fc->folioListadoReferencias(); $folPartes = explode(' · ', $fol); @endphp
                    <a href="{{ route('compras.show', $fc->id) }}"><span class="fw-600">{{ $folPartes[0] }}</span>@if(count($folPartes) > 1)<span class="text-muted" style="font-size:11px;font-weight:500;"> · {{ implode(' · ', array_slice($folPartes, 1)) }}</span>@endif</a>
                </td>
                <td>{{ $fc->fecha_emision->format('d/m/Y') }}</td>
                <td>{{ $fc->proveedor->nombre ?? $fc->nombre_emisor ?? '—' }}</td>
                <td class="text-end">${{ number_format($fc->total ?? 0, 2, '.', ',') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted">No hay facturas de compra en este período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="modalExportCompras" class="modal">
    <div class="modal-box" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title">Exportar reporte</div>
            <button type="button" class="modal-close" onclick="document.getElementById('modalExportCompras').classList.remove('show')" aria-label="Cerrar">✕</button>
        </div>
        <form id="formExportCompras" method="GET" action="{{ route('reportes.compras.export') }}">
            <input type="hidden" name="mes" value="">
            <input type="hidden" name="año" value="">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Formato</label>
                    <select name="formato" id="exportComprasFormato" class="form-control" required>
                        <option value="pdf">PDF</option>
                        <option value="xlsx">Excel</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="document.getElementById('modalExportCompras').classList.remove('show')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Descargar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var formFiltros = document.getElementById('formFiltrosCompras');
    var formExport = document.getElementById('formExportCompras');
    if (!formFiltros || !formExport) return;

    function val(name) {
        var el = formFiltros.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    }

    formExport.addEventListener('submit', function () {
        formExport.querySelector('[name="mes"]').value = val('mes');
        formExport.querySelector('[name="año"]').value = val('año');
    });
})();
</script>
@endpush

@endsection
