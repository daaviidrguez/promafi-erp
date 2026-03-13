@extends('layouts.app')

@section('title', 'Reporte compras')
@section('page-title', '🛒 Compras')
@section('page-subtitle', 'Órdenes y facturas de compra')

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
        @include('reportes.partials.filtro-mes', ['action' => route('reportes.compras')])
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
                <td>Órdenes de compra</td>
                <td class="text-end">{{ $ordenes->count() ?? 0 }}</td>
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
                <th>Tipo</th>
                <th>Folio</th>
                <th>Fecha</th>
                <th>Proveedor</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            @forelse($comprasMerge ?? [] as $item)
            <tr>
                <td><span class="badge badge-{{ $item->tipo === 'factura' ? 'success' : 'info' }}">{{ $item->tipo === 'factura' ? 'Factura' : 'Orden' }}</span></td>
                <td>
                    @if($item->route)
                    <a href="{{ route($item->route, $item->id) }}">{{ $item->folio }}</a>
                    @else
                    {{ $item->folio }}
                    @endif
                </td>
                <td>{{ $item->fecha ? \Carbon\Carbon::parse($item->fecha)->format('d/m/Y') : '-' }}</td>
                <td>{{ $item->proveedor }}</td>
                <td class="text-end">${{ number_format($item->total, 2, '.', ',') }}</td>
            </tr>
            @empty
            <tr><td colspan="5" class="text-center text-muted">No hay compras en este período.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

@endsection
