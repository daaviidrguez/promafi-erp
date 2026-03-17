@extends('layouts.app')

@section('title', 'Tablero anual')
@section('page-title', '📅 Tablero anual')
@section('page-subtitle', 'Tablero anual para RESICO (626): ingresos cobrados, IVA, ISR estimado y utilidad por mes')

@php
$breadcrumbs = [
    ['title' => 'Tablero', 'url' => route('tablero.index')],
    ['title' => 'Tablero anual']
];
@endphp

@section('content')

<div class="card mb-4">
    <div class="card-body d-flex flex-wrap gap-3 align-items-center">
        <label for="año" class="mb-0 fw-semibold">Año:</label>
        <form method="GET" action="{{ route('tablero-anual.index') }}" class="d-flex gap-2 align-items-center flex-wrap">
            <select name="año" id="año" class="form-control" style="width: auto;">
                @for($y = now()->year; $y >= now()->year - 5; $y--)
                    <option value="{{ $y }}" {{ $año == $y ? 'selected' : '' }}>{{ $y }}</option>
                @endfor
            </select>
            <button type="submit" class="btn btn-primary">Ver año</button>
        </form>
    </div>
</div>

<div class="tablero-anual-grid">
    @foreach($meses as $num => $datos)
    <div class="card tablero-anual-card">
        <div class="card-header py-2">
            <div class="card-title mb-0">{{ $datos['nombre'] }}</div>
        </div>
        <div class="card-body py-3">
            <table class="table table-sm table-borderless mb-0 tablero-anual-tabla">
                <tr>
                    <td class="text-muted small">Total ventas</td>
                    <td class="text-end fw-semibold">${{ number_format($datos['total_ventas'], 2, '.', ',') }}</td>
                </tr>
                <tr>
                    <td class="text-muted small">Subtotal</td>
                    <td class="text-end">${{ number_format($datos['subtotal'], 2, '.', ',') }}</td>
                </tr>
                <tr>
                    <td class="text-muted small">Ingresos cobrados @if($aplicaResico)<span class="text-muted" style="font-size: 0.85em;">(base ISR)</span>@endif</td>
                    <td class="text-end">${{ number_format($datos['ventas_sin_iva'], 2, '.', ',') }}</td>
                </tr>
                <tr>
                    <td class="text-muted small">IVA traslado</td>
                    <td class="text-end">${{ number_format($datos['iva_traslado'], 2, '.', ',') }}</td>
                </tr>
                <tr>
                    <td class="text-muted small">IVA acreditable</td>
                    <td class="text-end">${{ number_format($datos['iva_acreditable'], 2, '.', ',') }}</td>
                </tr>
                <tr>
                    <td class="text-muted small">IVA a pagar</td>
                    <td class="text-end">${{ number_format($datos['iva_a_pagar'], 2, '.', ',') }}</td>
                </tr>
                @if($aplicaResico)
                <tr>
                    <td class="text-muted small">ISR estimado RESICO</td>
                    <td class="text-end">${{ number_format($datos['isr_estimado_resico'], 2, '.', ',') }}</td>
                </tr>
                @endif
                <tr>
                    <td class="text-muted small">Utilidad</td>
                    <td class="text-end fw-semibold {{ $datos['utilidad'] >= 0 ? 'text-success' : 'text-danger' }}">
                        ${{ number_format($datos['utilidad'], 2, '.', ',') }}
                    </td>
                </tr>
            </table>
        </div>
    </div>
    @endforeach
</div>

@if($aplicaResico)
<p class="text-muted small mt-3">
    <strong>RESICO (626):</strong> El ISR estimado se calcula sobre los <strong>ingresos cobrados</strong> del mes (base sin IVA) según la tabla de tasas por rangos de ingreso mensual. En RESICO el impuesto se paga sobre ingresos, no sobre utilidad; la Utilidad mostrada es solo informativa para el negocio.
</p>
@else
<p class="text-muted small mt-3">
    Este tablero está diseñado para contribuyentes en régimen 626 (Régimen Simplificado de Confianza – RESICO). Para ver el ISR estimado RESICO, configura la empresa como <strong>persona física</strong> con <strong>régimen 626</strong> en Configuración → Datos fiscales.
</p>
@endif

<style>
.tablero-anual-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
.tablero-anual-card {
    min-width: 0;
}
.tablero-anual-tabla td {
    padding: 0.2rem 0;
    font-size: 0.9rem;
}
@media (max-width: 1200px) {
    .tablero-anual-grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 768px) {
    .tablero-anual-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 480px) {
    .tablero-anual-grid { grid-template-columns: 1fr; }
}
</style>

@endsection
