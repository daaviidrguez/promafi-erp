@extends('layouts.app')

@section('title', 'Ventas mensuales')
@section('page-title', '💰 Ventas mensuales')
@section('page-subtitle', 'Facturas emitidas')

@php
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => route('reportes.fiscal')],
    ['title' => 'Ventas mensuales']
];
$mesNombre = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$mes ?? 1];
@endphp

@section('content')

<div class="card">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div><strong>{{ $mesNombre }} {{ $año ?? now()->year }}</strong></div>
        @include('reportes.partials.filtro-mes', ['action' => route('reportes.ventas')])
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

@endsection
