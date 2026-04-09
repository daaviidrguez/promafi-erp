@extends('layouts.app')

@section('title', 'Ventas mensuales')
@section('page-title', '💰 Ventas mensuales')
@section('page-subtitle', 'Facturas emitidas')

@php
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => route('reportes.fiscal')],
    ['title' => 'Ventas mensuales']
];
@endphp

@section('content')

{{-- Misma línea compacta que reporte Compras: período a la izquierda, selects + acciones a la derecha --}}
<div class="card">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div><strong>{{ $mesNombre ?? '' }} {{ $año ?? now()->year }}</strong></div>
        <form id="formFiltrosVentas" method="GET" action="{{ route('reportes.ventas') }}" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center;">
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
            <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('modalExportVentas').classList.add('show')">Exportar</button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Resumen</div>
    </div>
    <div class="card-body">
        <table class="table" style="max-width: 440px;">
            <tr>
                <td>Facturas</td>
                <td class="text-end fw-600">{{ $facturas->count() ?? 0 }}</td>
            </tr>
            <tr>
                <td>Subtotal</td>
                <td class="text-end">${{ number_format($subtotalVentas ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>IVA</td>
                <td class="text-end">${{ number_format($ivaVentas ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td>ISR retenido</td>
                <td class="text-end">${{ number_format($isrRetenidoVentas ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Total ventas</strong></td>
                <td class="text-end"><strong>${{ number_format($totalVentas ?? 0, 2, '.', ',') }}</strong></td>
            </tr>
        </table>
    </div>
</div>

<div class="table-container">
    <table class="table">
        <thead>
            <tr>
                <th>Serie/Folio</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($facturas ?? [] as $f)
            <tr>
                <td>{{ $f->serie ?? '' }} {{ $f->folio }}</td>
                <td>{{ $f->fecha_emision->format('d/m/Y') }}</td>
                <td>{{ $f->cliente->nombre ?? $f->nombre_receptor ?? '-' }}</td>
                <td class="text-end">${{ number_format($f->total, 2, '.', ',') }}</td>
            </tr>
            @empty
            <tr><td colspan="4" class="text-center text-muted">No hay facturas en este período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

<div id="modalExportVentas" class="modal">
    <div class="modal-box" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title">Exportar reporte</div>
            <button type="button" class="modal-close" onclick="document.getElementById('modalExportVentas').classList.remove('show')" aria-label="Cerrar">✕</button>
        </div>
        <form id="formExportVentas" method="GET" action="{{ route('reportes.ventas.export') }}">
            <input type="hidden" name="mes" value="">
            <input type="hidden" name="año" value="">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Formato</label>
                    <select name="formato" id="exportVentasFormato" class="form-control" required>
                        <option value="pdf">PDF</option>
                        <option value="xlsx">Excel</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="document.getElementById('modalExportVentas').classList.remove('show')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Descargar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var formFiltros = document.getElementById('formFiltrosVentas');
    var formExport = document.getElementById('formExportVentas');
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
