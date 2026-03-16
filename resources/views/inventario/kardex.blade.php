@extends('layouts.app')
@section('title', 'Kardex')
@section('page-title', '📒 Kardex')
@section('page-subtitle', 'Movimientos de inventario por producto y rango de fechas')

@php
$breadcrumbs = [
    ['title' => 'Inventario', 'url' => route('inventario.index')],
    ['title' => 'Kardex']
];
@endphp

@section('content')

<div class="card">
    <div class="card-header"><div class="card-title">Filtros</div></div>
    <div class="card-body">
        <form method="GET" action="{{ route('inventario.kardex') }}" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="min-width: 260px;">
                <label class="form-label">Producto <span class="req">*</span></label>
                <select name="producto_id" class="form-control" required>
                    <option value="">Seleccione un producto</option>
                    @foreach($productos as $p)
                        <option value="{{ $p->id }}" {{ ($productoId ?? '') == $p->id ? 'selected' : '' }}>{{ $p->codigo }} — {{ Str::limit($p->nombre, 50) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ $fechaDesde ?? now()->startOfMonth()->format('Y-m-d') }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ $fechaHasta ?? now()->format('Y-m-d') }}" required>
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <button type="submit" class="btn btn-primary">👁️ Ver</button>
                @if($producto && $fechaDesde && $fechaHasta)
                <a href="{{ route('inventario.kardex.pdf', ['producto_id' => $producto->id, 'fecha_desde' => $fechaDesde, 'fecha_hasta' => $fechaHasta]) }}"
                   class="btn btn-outline"
                   target="_blank">
                    📄 Descargar PDF
                </a>
                @endif
            </div>
        </form>
    </div>
</div>

@if($producto)
<div class="card mt-4">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="card-title mb-0">Kardex — {{ $producto->codigo }} {{ $producto->nombre }}</div>
        <span class="text-muted small">{{ \Carbon\Carbon::parse($fechaDesde)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaHasta)->format('d/m/Y') }}</span>
    </div>
    <div class="card-body p-0">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Tipo / Referencia</th>
                        <th class="td-right">Entrada</th>
                        <th class="td-right">Salida</th>
                        <th class="td-right">Saldo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr style="background: var(--color-gray-50);">
                        <td colspan="2" class="fw-600">Saldo inicial</td>
                        <td class="td-right">—</td>
                        <td class="td-right">—</td>
                        <td class="td-right fw-600">{{ number_format($saldoInicial, 2, '.', ',') }}</td>
                    </tr>
                    @foreach($movimientos as $m)
                    <tr>
                        <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            {{ $m->etiqueta_tipo }}
                            @if($m->folio)<span class="text-muted"> — {{ $m->folio }}</span>@endif
                            @if($m->observaciones)<span class="text-muted"> {{ Str::limit($m->observaciones, 40) }}</span>@endif
                        </td>
                        <td class="td-right">
                            @if(\App\Models\InventarioMovimiento::esEntrada($m->tipo))
                                {{ number_format($m->cantidad, 2, '.', ',') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="td-right">
                            @if(!\App\Models\InventarioMovimiento::esEntrada($m->tipo))
                                {{ number_format($m->cantidad, 2, '.', ',') }}
                            @else
                                —
                            @endif
                        </td>
                        <td class="td-right fw-600">{{ number_format($m->stock_resultante, 2, '.', ',') }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if($movimientos->isEmpty())
        <div class="p-4 text-center text-muted">No hay movimientos en el rango de fechas seleccionado.</div>
        @endif
    </div>
</div>
@else
<div class="card mt-4">
    <div class="card-body text-center text-muted py-5">
        Seleccione un producto y rango de fechas, luego pulse <strong>Ver</strong> para consultar el kardex.
    </div>
</div>
@endif

@endsection
