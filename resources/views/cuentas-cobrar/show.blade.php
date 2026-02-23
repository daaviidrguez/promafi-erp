@extends('layouts.app')

@section('title', 'Cuenta por Cobrar')
@section('page-title', '💰 Cuenta por Cobrar')
@section('page-subtitle', 'Factura ' . $cuentaPorCobrar->factura->folio_completo)

@php
$breadcrumbs = [
    ['title' => 'Cuentas por Cobrar', 'url' => route('cuentas-cobrar.index')],
    ['title' => $cuentaPorCobrar->factura->folio_completo]
];
@endphp

@section('content')

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

    {{-- Columna izquierda --}}
    <div>

        {{-- Cliente --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">👤 Cliente</div>
                <a href="{{ route('clientes.show', $cuentaPorCobrar->cliente->id) }}"
                   class="btn btn-light btn-sm">Ver perfil</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Nombre</div>
                        <div class="info-value">{{ $cuentaPorCobrar->cliente->nombre }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RFC</div>
                        <div class="info-value text-mono">{{ $cuentaPorCobrar->cliente->rfc }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value-sm">{{ $cuentaPorCobrar->cliente->email ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value-sm">{{ $cuentaPorCobrar->cliente->telefono ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Detalle de la Factura --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🧾 Detalle de la Factura</div>
                <a href="{{ route('facturas.show', $cuentaPorCobrar->factura_id) }}"
                   class="btn btn-light btn-sm">Ver factura</a>
            </div>
            <div class="table-container" style="border: none; box-shadow: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="td-center">Cantidad</th>
                            <th class="td-right">P. Unit.</th>
                            <th class="td-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cuentaPorCobrar->factura->detalles as $detalle)
                        <tr>
                            <td>
                                <div class="fw-600">{{ $detalle->descripcion }}</div>
                                <div class="text-muted" style="font-size: 11px;">{{ $detalle->unidad }}</div>
                            </td>
                            <td class="td-center">{{ number_format($detalle->cantidad, 2) }}</td>
                            <td class="td-right text-mono">
                                ${{ number_format($detalle->valor_unitario, 2, '.', ',') }}
                            </td>
                            <td class="td-right text-mono fw-600">
                                ${{ number_format($detalle->importe, 2, '.', ',') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totales de la factura --}}
            <div class="card-body" style="display: flex; justify-content: flex-end;">
                <div class="totales-panel" style="min-width: 260px;">
                    <div class="totales-row">
                        <span>Subtotal</span>
                        <span class="monto">
                            ${{ number_format($cuentaPorCobrar->factura->subtotal, 2, '.', ',') }}
                        </span>
                    </div>
                    <div class="totales-row">
                        <span>IVA</span>
                        <span class="monto">
                            ${{ number_format($cuentaPorCobrar->factura->calcularIVA(), 2, '.', ',') }}
                        </span>
                    </div>
                    <div class="totales-row grand">
                        <span>Total</span>
                        <span class="monto">
                            ${{ number_format($cuentaPorCobrar->factura->total, 2, '.', ',') }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Historial de Pagos (complementos timbrados aplicados a esta factura) --}}
        @if($cuentaPorCobrar->factura->documentosRelacionadosPago && $cuentaPorCobrar->factura->documentosRelacionadosPago->count() > 0)
        <div class="card">
            <div class="card-header">
                <div class="card-title">Historial de Pagos</div>
            </div>
            <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Forma</th>
                            <th>Complemento</th>
                            <th class="td-right">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cuentaPorCobrar->factura->documentosRelacionadosPago as $doc)
                        <tr>
                            <td>{{ $doc->pagoRecibido->fecha_pago->format('d/m/Y') }}</td>
                            <td>{{ $doc->pagoRecibido->forma_pago }}</td>
                            <td>
                                @if($doc->pagoRecibido->complementoPago)
                                <a href="{{ route('complementos.show', $doc->pagoRecibido->complementoPago->id) }}" class="text-mono" style="color: var(--color-primary);">
                                    {{ $doc->pagoRecibido->complementoPago->folio_completo }}
                                </a>
                                @else
                                —
                                @endif
                            </td>
                            <td class="td-right text-mono fw-600" style="color: var(--color-success);">
                                ${{ number_format($doc->monto_pagado, 2, '.', ',') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

    </div>

    {{-- Columna derecha --}}
    <div>

        {{-- Estado de la Cuenta --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📊 Estado de la Cuenta</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @if($cuentaPorCobrar->estado === 'pagada')
                            <span class="badge badge-success">✅ Pagada</span>
                        @elseif($cuentaPorCobrar->estado === 'vencida')
                            <span class="badge badge-danger">⚠️ Vencida</span>
                        @elseif($cuentaPorCobrar->estado === 'parcial')
                            <span class="badge badge-warning">📊 Parcial</span>
                        @else
                            <span class="badge badge-info">⏳ Pendiente</span>
                        @endif
                    </div>
                </div>

                <div class="totales-panel" style="margin-top: 16px;">
                    <div class="totales-row">
                        <span>Monto Total</span>
                        <span class="monto">
                            ${{ number_format($cuentaPorCobrar->monto_total, 2, '.', ',') }}
                        </span>
                    </div>
                    <div class="totales-row" style="color: var(--color-success);">
                        <span>Monto Pagado</span>
                        <span class="monto">
                            ${{ number_format($cuentaPorCobrar->monto_pagado, 2, '.', ',') }}
                        </span>
                    </div>
                    <div class="totales-row grand" style="font-size: 16px;">
                        <span>Pendiente</span>
                        <span class="monto"
                              style="color: {{ $cuentaPorCobrar->monto_pendiente > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
                            ${{ number_format($cuentaPorCobrar->monto_pendiente, 2, '.', ',') }}
                        </span>
                    </div>
                </div>

                <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-100);">
                    <div class="info-row">
                        <div class="info-label">Fecha de Emisión</div>
                        <div class="info-value-sm">{{ $cuentaPorCobrar->fecha_emision->format('d/m/Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Fecha de Vencimiento</div>
                        <div class="info-value-sm">{{ $cuentaPorCobrar->fecha_vencimiento->format('d/m/Y') }}</div>
                        @if($cuentaPorCobrar->estaVencida())
                            <div style="font-size: 12px; font-weight: 600; color: var(--color-danger); margin-top: 4px;">
                                ⚠️ Vencida hace {{ $cuentaPorCobrar->dias_vencido }} días
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Acciones</div>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">

                @if(!$cuentaPorCobrar->estaPagada())
                <a href="{{ route('complementos.create', ['cuenta_id' => $cuentaPorCobrar->id]) }}"
                   class="btn btn-primary w-full">
                    💵 Crear Complemento de Pago
                </a>
                <span class="form-hint" style="text-align: center; font-size: 12px;">
                    Crea el CFDI de pago, timbralo y el pago se aplicará a esta cuenta.
                </span>
                @endif

                <a href="{{ route('facturas.show', $cuentaPorCobrar->factura_id) }}"
                   class="btn btn-outline w-full">Ver Factura</a>

                <a href="{{ route('cuentas-cobrar.index') }}"
                   class="btn btn-light w-full">← Volver</a>
            </div>
        </div>

    </div>
</div>

@endsection