@extends('layouts.app')

@section('title', 'Reporte fiscal')
@section('page-title', '📑 Reporte fiscal')
@section('page-subtitle', 'Ingresos cobrados, IVA, ISR RESICO (mensual)')

@php
$breadcrumbs = [
    ['title' => 'Reporte fiscal']
];
$mesNombre = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'][$mes ?? 1];
@endphp

@section('content')

<div class="card">
    <div class="card-body" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
        <div><strong>{{ $mesNombre }} {{ $año ?? now()->year }}</strong></div>
        @include('reportes.partials.filtro-mes', ['action' => route('reportes.fiscal')])
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Resumen fiscal</div>
    </div>
    <div class="card-body">
        <table class="table" style="max-width: 400px;">
            <tr>
                <td><strong>Ingresos cobrados</strong> <span class="text-muted small">(sin IVA)</span></td>
                <td class="text-end">${{ number_format($ingresosCobrados ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>IVA trasladado</strong></td>
                <td class="text-end">${{ number_format($ivaTrasladado ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>IVA acreditable</strong></td>
                <td class="text-end">${{ number_format($ivaAcreditable ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>IVA a pagar</strong></td>
                <td class="text-end">${{ number_format($ivaPagar ?? 0, 2, '.', ',') }}</td>
            </tr>
            @if($aplicaResico ?? false)
            <tr>
                <td><strong>ISR estimado (RESICO)</strong></td>
                <td class="text-end">${{ number_format($isrEstimado ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td colspan="2" class="text-muted small">Calculado sobre ingresos cobrados (base gravable sin IVA) del mes según tabla ISR RESICO. El IVA no forma parte de la base del ISR por ser impuesto trasladado al cliente.</td>
            </tr>
            @else
            <tr>
                <td colspan="2" class="text-muted small">ISR RESICO aplica solo a persona física con régimen 626. Configura en ⚙️ Configuración → Datos fiscales.</td>
            </tr>
            @endif
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Detalle</div>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2"><strong>Ingresos cobrados (sin IVA):</strong> Base gravable (subtotal − descuento). PUE: base de facturas contado timbradas en el mes. PPD: base proporcional conforme se reciben pagos en complementos. El IVA no se incluye por ser impuesto trasladado al cliente.</p>
        <p class="text-muted small mb-2"><strong>IVA trasladado:</strong> PUE: IVA total de facturas contado timbradas en el mes. PPD: IVA proporcional conforme se reciben pagos en complementos.</p>
        <p class="text-muted small mb-2"><strong>IVA acreditable:</strong> IVA de órdenes de compra (compras) del mes.</p>
    </div>
</div>

@endsection
