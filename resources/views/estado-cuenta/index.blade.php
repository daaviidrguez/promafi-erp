@extends('layouts.app')

@section('title', 'Estado de Cuenta')
@section('page-title', '📋 Estado de Cuenta')
@section('page-subtitle', 'Movimientos y saldo por cliente')

@php
$breadcrumbs = [
    ['title' => 'Estado de Cuenta']
];
@endphp

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title">Filtros</div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('estado-cuenta.ver') }}"
              style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; align-items: end;">
            <div class="form-group">
                <label class="form-label">Cliente <span class="req">*</span></label>
                <select name="cliente_id" class="form-control" required>
                    <option value="">Seleccionar cliente</option>
                    @foreach($clientes as $c)
                        <option value="{{ $c->id }}" {{ ($clienteId ?? '') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Tipo de reporte</label>
                <select name="tipo_reporte" class="form-control">
                    <option value="estado_cuenta" {{ ($tipoReporte ?? 'estado_cuenta') == 'estado_cuenta' ? 'selected' : '' }}>Estado de Cuenta (todas las transacciones)</option>
                    <option value="reporte_cobranza" {{ ($tipoReporte ?? '') == 'reporte_cobranza' ? 'selected' : '' }}>Reporte de Cobranza (solo facturas a crédito con pendiente)</option>
                </select>
                <span class="form-hint">Reporte de Cobranza: solo facturas con saldo pendiente y sus pagos/NC.</span>
            </div>
            <div class="form-group">
                <label class="form-label">Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ $fechaDesde ?? '' }}">
            </div>
            <div class="form-group">
                <label class="form-label">Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ $fechaHasta ?? '' }}">
            </div>
            <div style="grid-column: 1 / -1; display: flex; gap: 12px;">
                <button type="submit" class="btn btn-primary">Ver Estado de Cuenta</button>
                <a href="{{ route('estado-cuenta.index') }}" class="btn btn-light">Limpiar</a>
            </div>
        </form>
    </div>
</div>

<p class="text-muted small mt-3">
    El <strong>Estado de Cuenta</strong> muestra todas las transacciones del cliente (facturas, pagos y notas de crédito) con saldo acumulado.
    El <strong>Reporte de Cobranza</strong> filtra únicamente las facturas a crédito que tienen saldo pendiente y los movimientos asociados.
</p>

@endsection
