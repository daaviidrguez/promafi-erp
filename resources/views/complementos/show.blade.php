@extends('layouts.app')

@section('title', 'Complemento ' . $complemento->folio_completo)
@section('page-title', '💳 Complemento ' . $complemento->folio_completo)
@section('page-subtitle', $complemento->cliente->nombre)

@php
$breadcrumbs = [
    ['title' => 'Complementos de Pago', 'url' => route('complementos.index')],
    ['title' => $complemento->folio_completo]
];
@endphp

@section('content')

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

    {{-- Columna izquierda --}}
    <div>

        {{-- Receptor --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">👤 Receptor</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Nombre / Razón Social</div>
                        <div class="info-value">{{ $complemento->nombre_receptor }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RFC</div>
                        <div class="info-value text-mono">{{ $complemento->rfc_receptor }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pagos --}}
        @foreach($complemento->pagosRecibidos as $pago)
        <div class="card">
            <div class="card-header">
                <div class="card-title">💵 Datos del Pago</div>
                <span class="text-mono fw-600" style="color: var(--color-success); font-size: 18px;">
                    ${{ number_format($pago->monto, 2, '.', ',') }}
                </span>
            </div>
            <div class="card-body">
                <div class="info-grid-2" style="margin-bottom: 20px;">
                    <div class="info-row">
                        <div class="info-label">Fecha de Pago</div>
                        <div class="info-value">{{ $pago->fecha_pago->format('d/m/Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Forma de Pago</div>
                        <div class="info-value">{{ $pago->forma_pago }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Moneda</div>
                        <div class="info-value">{{ $pago->moneda }}</div>
                    </div>
                    @if($pago->num_operacion)
                    <div class="info-row">
                        <div class="info-label">No. de Operación</div>
                        <div class="info-value text-mono">{{ $pago->num_operacion }}</div>
                    </div>
                    @endif
                </div>

                {{-- Facturas pagadas --}}
                <div style="font-size: 13px; font-weight: 700; color: var(--color-primary); margin-bottom: 12px;">
                    🧾 Facturas Pagadas
                </div>

                <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th class="td-center">Parcialidad</th>
                                <th class="td-right">Saldo Anterior</th>
                                <th class="td-right">Monto Pagado</th>
                                <th class="td-right">Saldo Insoluto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pago->documentosRelacionados as $doc)
                            <tr>
                                <td>
                                    <a href="{{ route('facturas.show', $doc->factura->id) }}"
                                       class="text-mono fw-600" style="color: var(--color-primary);">
                                        {{ $doc->factura->folio_completo }}
                                    </a>
                                    <div class="text-mono text-muted" style="font-size: 11px;">
                                        {{ substr($doc->factura_uuid, 0, 20) }}...
                                    </div>
                                </td>
                                <td class="td-center">
                                    <span class="badge badge-info">Parc. {{ $doc->parcialidad }}</span>
                                </td>
                                <td class="td-right text-mono">
                                    ${{ number_format($doc->saldo_anterior, 2, '.', ',') }}
                                </td>
                                <td class="td-right text-mono fw-600" style="color: var(--color-success);">
                                    ${{ number_format($doc->monto_pagado, 2, '.', ',') }}
                                </td>
                                <td class="td-right text-mono fw-600"
                                    style="color: {{ $doc->saldo_insoluto > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
                                    ${{ number_format($doc->saldo_insoluto, 2, '.', ',') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach

    </div>

    {{-- Columna derecha --}}
    <div>

        {{-- Información Fiscal --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información Fiscal</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @if($complemento->estado === 'timbrado')
                            <span class="badge badge-success">Timbrado</span>
                        @elseif($complemento->estado === 'borrador')
                            <span class="badge badge-warning">📝 Borrador</span>
                        @else
                            <span class="badge badge-danger">✗ Cancelado</span>
                        @endif
                    </div>
                </div>
                <div class="info-row" style="margin-top: 16px;">
                    <div class="info-label">Fecha de Emisión</div>
                    <div class="info-value-sm">{{ $complemento->fecha_emision->format('d/m/Y') }}</div>
                </div>
                @if($complemento->fecha_timbrado)
                <div class="info-row">
                    <div class="info-label">Fecha de Timbrado</div>
                    <div class="info-value-sm" style="color: var(--color-success);">
                        {{ $complemento->fecha_timbrado->format('d/m/Y H:i:s') }}
                    </div>
                </div>
                @endif
                @if($complemento->uuid)
                <div class="info-row">
                    <div class="info-label">UUID / Folio Fiscal</div>
                    <div style="background: var(--color-gray-50); border-radius: var(--radius-sm); padding: 8px; font-family: var(--font-mono); font-size: 11px; word-break: break-all; margin-top: 4px;">
                        {{ $complemento->uuid }}
                    </div>
                </div>
                @endif
                <div class="info-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-100);">
                    <div class="info-label">Monto Total</div>
                    <div class="info-value" style="font-size: 22px;">
                        ${{ number_format($complemento->monto_total, 2, '.', ',') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Acciones</div>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">

                @if($complemento->estado === 'borrador')
                <p class="text-muted" style="font-size: 13px; margin: 0 0 8px 0;">
                    Al emitir se timbrará el complemento y se aplicará el pago a las cuentas por cobrar.
                </p>
                <form method="POST" action="{{ route('complementos.timbrar', $complemento->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">Emitir complemento</button>
                </form>
                @endif

                @if($complemento->estado === 'timbrado')
                    <a href="{{ route('complementos.ver-pdf', $complemento->id) }}"
                       target="_blank" class="btn btn-outline w-full">Ver PDF</a>
                    <a href="{{ route('complementos.descargar-pdf', $complemento->id) }}"
                       class="btn btn-outline w-full">Descargar PDF</a>
                    @if($complemento->xml_path)
                    <a href="{{ route('complementos.descargar-xml', $complemento->id) }}"
                       class="btn btn-success w-full">Descargar XML</a>
                    @endif
                @endif

                <a href="{{ route('complementos.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>

    </div>
</div>

@endsection