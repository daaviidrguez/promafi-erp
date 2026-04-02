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

<div class="tablero-anual-page">

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

<div class="tablero-anual-meses-grid">
    @foreach($meses as $num => $datos)
    <div class="card">
        <div class="card-header">
            <div class="card-title">{{ $datos['nombre'] }}</div>
        </div>
        <div class="card-body">
            <ul class="tablero-anual-list">
                <li>
                    <span class="tablero-anual-list-label">Total ventas</span>
                    <span class="tablero-anual-list-value text-mono">${{ number_format($datos['total_ventas'], 2, '.', ',') }}</span>
                </li>
                <li>
                    <span class="tablero-anual-list-label">Subtotal</span>
                    <span class="tablero-anual-list-value text-mono">${{ number_format($datos['subtotal'], 2, '.', ',') }}</span>
                </li>
                <li>
                    <span class="tablero-anual-list-label">Ingresos cobrados @if($aplicaResico)<span class="text-muted" style="font-size: 12px;">(base ISR)</span>@endif</span>
                    <span class="tablero-anual-list-value text-mono">${{ number_format($datos['ventas_sin_iva'], 2, '.', ',') }}</span>
                </li>
                <li>
                    <span class="tablero-anual-list-label">IVA traslado</span>
                    <span class="tablero-anual-list-value text-mono">${{ number_format($datos['iva_traslado'], 2, '.', ',') }}</span>
                </li>
                <li>
                    <span class="tablero-anual-list-label">IVA acreditable</span>
                    <span class="tablero-anual-list-value text-mono">${{ number_format($datos['iva_acreditable'], 2, '.', ',') }}</span>
                </li>
                <li>
                    <span class="tablero-anual-list-label">IVA a pagar</span>
                    <span class="tablero-anual-list-value text-mono">${{ number_format($datos['iva_a_pagar'], 2, '.', ',') }}</span>
                </li>
                @if($aplicaResico)
                <li>
                    <span class="tablero-anual-list-label">ISR estimado RESICO</span>
                    <span class="tablero-anual-list-value text-mono">${{ number_format($datos['isr_estimado_resico'], 2, '.', ',') }}</span>
                </li>
                @endif
                <li>
                    <span class="tablero-anual-list-label">Utilidad</span>
                    <span class="tablero-anual-list-value text-mono" style="color: {{ $datos['utilidad'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">${{ number_format($datos['utilidad'], 2, '.', ',') }}</span>
                </li>
            </ul>
        </div>
    </div>
    @endforeach
</div>

@if($aplicaResico)
<p class="text-muted small mt-3">
    <strong>RESICO (626):</strong> El ISR estimado se calcula sobre los <strong>ingresos cobrados</strong> del mes (base sin IVA) según la tabla de tasas por rangos de ingreso mensual. En RESICO el impuesto se paga sobre ingresos, no sobre utilidad. La <strong>Utilidad</strong> mostrada es la diferencia entre el precio de venta de la factura y el costo del producto, considerando solo las facturas ya cobradas en el mes (no forma parte del cálculo del ISR).
</p>
@else
<p class="text-muted small mt-3">
    Este tablero está diseñado para contribuyentes en régimen 626 (Régimen Simplificado de Confianza – RESICO). Para ver el ISR estimado RESICO, configura la empresa como <strong>persona física</strong> con <strong>régimen 626</strong> en Configuración → Datos fiscales.
</p>
@endif

</div>

@endsection
