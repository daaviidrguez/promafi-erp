@extends('layouts.app')

@section('title', ($es_reporte_cobranza ? 'Reporte de Cobranza' : 'Estado de Cuenta') . ' - ' . $cliente->nombre)
@section('page-title', $es_reporte_cobranza ? '📊 Reporte de Cobranza' : '📋 Estado de Cuenta')
@section('page-subtitle', $cliente->nombre)

@php
$breadcrumbs = [
    ['title' => 'Estado de Cuenta', 'url' => route('estado-cuenta.index')],
    ['title' => $cliente->nombre]
];
@endphp

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title">Datos del cliente</div>
        <div style="display: flex; gap: 8px;">
            <a href="{{ route('clientes.show', $cliente->id) }}" class="btn btn-light btn-sm">Ver cliente</a>
            <a href="{{ route('estado-cuenta.pdf') . '?' . http_build_query(request()->query()) }}" class="btn btn-primary btn-sm" target="_blank">📄 Descargar PDF</a>
        </div>
    </div>
    <div class="card-body">
        <div class="info-grid-2">
            <div class="info-row">
                <div class="info-label">Nombre</div>
                <div class="info-value">{{ $cliente->nombre }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">RFC</div>
                <div class="info-value text-mono">{{ $cliente->rfc ?? '—' }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Período</div>
                <div class="info-value">
                    @if($fecha_desde && $fecha_hasta)
                        {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }} – {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}
                    @elseif($fecha_desde)
                        Desde {{ \Carbon\Carbon::parse($fecha_desde)->format('d/m/Y') }}
                    @elseif($fecha_hasta)
                        Hasta {{ \Carbon\Carbon::parse($fecha_hasta)->format('d/m/Y') }}
                    @else
                        Todas las fechas
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Tipo de reporte</div>
                <div class="info-value">
                    @if($es_reporte_cobranza)
                        <span class="badge badge-warning">Reporte de Cobranza</span>
                    @else
                        <span class="badge badge-info">Estado de Cuenta</span>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Movimientos</div>
    </div>
    @if(count($movimientos) > 0)
    <div class="table-container" style="border: none; margin: 0;">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Tipo</th>
                    <th>Referencia / Descripción</th>
                    <th class="td-right">Cargo</th>
                    <th class="td-right">Abono</th>
                    <th class="td-right">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @foreach($movimientos as $m)
                <tr>
                    <td>{{ $m['fecha']->format('d/m/Y') }}</td>
                    <td>
                        @if($m['tipo'] === 'Factura')
                            <span class="badge badge-info">Factura</span>
                        @elseif($m['tipo'] === 'Nota de Crédito')
                            <span class="badge badge-warning">NC</span>
                        @else
                            <span class="badge badge-success">Pago</span>
                        @endif
                    </td>
                    <td>
                        <span class="text-mono fw-600">{{ $m['referencia'] }}</span>
                        @if($m['tipo'] !== 'Factura' && isset($m['descripcion']) && $m['descripcion'] !== $m['referencia'])
                            <div class="text-muted" style="font-size: 12px;">{{ $m['descripcion'] }}</div>
                        @endif
                    </td>
                    <td class="td-right text-mono">
                        @if($m['cargo'] > 0)
                            ${{ number_format($m['cargo'], 2, '.', ',') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="td-right text-mono">
                        @if($m['abono'] > 0)
                            ${{ number_format($m['abono'], 2, '.', ',') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="td-right text-mono fw-600" style="color: {{ $m['saldo'] >= 0 ? 'var(--color-gray-800)' : 'var(--color-success)' }};">
                        ${{ number_format($m['saldo'], 2, '.', ',') }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body" style="border-top: 2px solid var(--color-gray-200); background: var(--color-gray-50);">
        <div style="display: flex; justify-content: flex-end; gap: 24px; flex-wrap: wrap;">
            <div class="text-mono">
                <span class="text-muted">Total cargos:</span>
                <strong> ${{ number_format($total_cargos, 2, '.', ',') }}</strong>
            </div>
            <div class="text-mono">
                <span class="text-muted">Total abonos:</span>
                <strong> ${{ number_format($total_abonos, 2, '.', ',') }}</strong>
            </div>
            <div class="text-mono fw-600" style="font-size: 16px; color: {{ $saldo_final >= 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
                Saldo final: ${{ number_format($saldo_final, 2, '.', ',') }}
            </div>
        </div>
    </div>
    @else
    <div class="card-body">
        <div class="empty-state">
            <div class="empty-state-icon">📋</div>
            <div class="empty-state-title">Sin movimientos</div>
            <div class="empty-state-text">
                @if($es_reporte_cobranza)
                    Este cliente no tiene facturas a crédito con saldo pendiente en el período seleccionado.
                @else
                    No hay transacciones para el cliente en el período seleccionado.
                @endif
            </div>
        </div>
    </div>
    @endif
</div>

<div style="margin-top: 16px;">
    <a href="{{ route('estado-cuenta.index') }}" class="btn btn-light">← Nuevo reporte</a>
</div>

@endsection
