@extends('layouts.app')
@section('title', 'Cuentas por Pagar')
@section('page-title', '💳 Cuentas por Pagar')
@section('page-subtitle', 'Obligaciones con proveedores')

@php $breadcrumbs = [['title' => 'Compras'], ['title' => 'Cuentas por Pagar']]; @endphp

@section('content')

<div class="stats-grid" style="grid-template-columns: repeat(2, 1fr);">
    <div class="stat-card stat-warning">
        <div class="stat-info-box"><div class="stat-label">Total Pendiente</div><div class="stat-value" style="font-size:22px;">${{ number_format($totales['pendiente'], 0, '.', ',') }}</div></div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box"><div class="stat-label">Total Pagado</div><div class="stat-value" style="font-size:22px;">${{ number_format($totales['pagado'], 0, '.', ',') }}</div></div>
        <div class="stat-icon">✅</div>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('cuentas-por-pagar.index') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <select name="proveedor_id" class="form-control" style="min-width:200px;">
                <option value="">Todos los proveedores</option>
                @foreach($proveedores as $prov)
                <option value="{{ $prov->id }}" {{ ($proveedor_id ?? '') == $prov->id ? 'selected' : '' }}>{{ $prov->nombre }}</option>
                @endforeach
            </select>
            <select name="estado" class="form-control" style="min-width:160px;">
                <option value="">Todos</option>
                <option value="pendiente" {{ ($estado ?? '') == 'pendiente' ? 'selected' : '' }}>Pendiente</option>
                <option value="parcial" {{ ($estado ?? '') == 'parcial' ? 'selected' : '' }}>Parcial</option>
                <option value="vencida" {{ ($estado ?? '') == 'vencida' ? 'selected' : '' }}>Vencida</option>
                <option value="pagada" {{ ($estado ?? '') == 'pagada' ? 'selected' : '' }}>Pagada</option>
            </select>
            <button type="submit" class="btn btn-primary">Filtrar</button>
            @if(($estado ?? false) || ($proveedor_id ?? false))<a href="{{ route('cuentas-por-pagar.index') }}" class="btn btn-light">✕ Limpiar</a>@endif
        </form>
    </div>
</div>

<div class="table-container">
    @if($cuentas->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Orden de Compra</th>
                <th>Proveedor</th>
                <th>Emisión</th>
                <th>Vencimiento</th>
                <th class="td-right">Total</th>
                <th class="td-right">Pendiente</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cuentas as $c)
            <tr>
                <td class="text-mono fw-600">{{ $c->ordenCompra->folio ?? '—' }}</td>
                <td>{{ $c->proveedor->nombre ?? '—' }}</td>
                <td>{{ $c->fecha_emision->format('d/m/Y') }}</td>
                <td>{{ $c->fecha_vencimiento->format('d/m/Y') }}</td>
                <td class="td-right text-mono">${{ number_format($c->monto_total, 2) }}</td>
                <td class="td-right text-mono fw-600">${{ number_format($c->monto_pendiente, 2) }}</td>
                <td class="td-center">
                    @if($c->estado === 'pendiente')<span class="badge badge-warning">Pendiente</span>
                    @elseif($c->estado === 'parcial')<span class="badge badge-info">Parcial</span>
                    @elseif($c->estado === 'vencida')<span class="badge badge-danger">Vencida</span>
                    @else<span class="badge badge-success">Pagada</span>@endif
                </td>
                <td class="td-actions"><a href="{{ route('cuentas-por-pagar.show', $c->id) }}" class="btn btn-info btn-sm">Ver</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px;border-top:1px solid var(--color-gray-100);">{{ $cuentas->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">💳</div>
        <div class="empty-state-title">No hay cuentas por pagar</div>
        <div class="empty-state-text">Se generan al aceptar una orden de compra</div>
    </div>
    @endif
</div>

@endsection
