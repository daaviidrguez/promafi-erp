@extends('layouts.app')

@section('title', 'Reporte de utilidad')
@section('page-title', '📈 Reporte de utilidad')
@section('page-subtitle', 'Ingresos, costos y utilidad por ventas')

@php
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => route('reportes.fiscal')],
    ['title' => 'Utilidad']
];
@endphp

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title">Filtros</div>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('reportes.utilidad') }}" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; align-items: end;">
            <div class="form-group">
                <label class="form-label">📅 Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ $fechaDesde ?? '' }}">
            </div>
            <div class="form-group">
                <label class="form-label">📅 Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ $fechaHasta ?? '' }}">
            </div>
            <div class="form-group">
                <label class="form-label">👤 Cliente</label>
                <select name="cliente_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($clientes ?? [] as $c)
                        <option value="{{ $c->id }}" {{ ($clienteId ?? '') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">📦 Producto</label>
                <select name="producto_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($productos ?? [] as $p)
                        <option value="{{ $p->id }}" {{ ($productoId ?? '') == $p->id ? 'selected' : '' }}>{{ $p->codigo ? $p->codigo . ' - ' : '' }}{{ Str::limit($p->nombre, 40) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">🧾 Factura</label>
                <select name="factura_id" class="form-control">
                    <option value="">Todas</option>
                    @foreach($facturas ?? [] as $f)
                        <option value="{{ $f->id }}" {{ ($facturaId ?? '') == $f->id ? 'selected' : '' }}>
                            {{ $f->serie }}-{{ str_pad($f->folio, 4, '0', STR_PAD_LEFT) }} ({{ $f->fecha_emision->format('d/m/Y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Filtrar</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Resumen</div>
    </div>
    <div class="card-body">
        <table class="table" style="max-width: 480px;">
            <tr>
                <td><strong>Total ingresos</strong></td>
                <td class="text-end text-mono">${{ number_format($totalIngreso ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Total costos</strong></td>
                <td class="text-end text-mono">${{ number_format($totalCosto ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Utilidad</strong></td>
                <td class="text-end text-mono fw-600" style="color: {{ ($totalUtilidad ?? 0) >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    ${{ number_format($totalUtilidad ?? 0, 2, '.', ',') }}
                </td>
            </tr>
            <tr>
                <td><strong>Margen %</strong></td>
                <td class="text-end text-mono">{{ number_format($margen ?? 0, 1) }}%</td>
            </tr>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Detalle</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Factura</th>
                        <th>Fecha</th>
                        <th>Cliente</th>
                        <th>Producto / Concepto</th>
                        <th class="td-center">Cant.</th>
                        <th class="td-right">Ingreso</th>
                        <th class="td-right">Costo</th>
                        <th class="td-right">Utilidad</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas ?? [] as $fila)
                    <tr>
                        <td>
                            <a href="{{ route('facturas.show', $fila['detalle']->factura_id) }}" class="text-mono" style="color: var(--color-primary);">
                                {{ $fila['detalle']->factura->folio_completo ?? $fila['detalle']->factura->serie . '-' . $fila['detalle']->factura->folio }}
                            </a>
                        </td>
                        <td>{{ $fila['detalle']->factura->fecha_emision->format('d/m/Y') }}</td>
                        <td>{{ optional($fila['detalle']->factura->cliente)->nombre ?? $fila['detalle']->factura->nombre_receptor ?? '—' }}</td>
                        <td>
                            @if($fila['detalle']->producto)
                                {{ $fila['detalle']->producto->codigo ? $fila['detalle']->producto->codigo . ' - ' : '' }}{{ Str::limit($fila['detalle']->descripcion ?? $fila['detalle']->producto->nombre, 35) }}
                            @else
                                {{ Str::limit($fila['detalle']->descripcion ?? 'Concepto', 35) }}
                            @endif
                        </td>
                        <td class="td-center text-mono">{{ number_format($fila['detalle']->cantidad, 2) }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['ingreso'], 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['costo'], 2, '.', ',') }}</td>
                        <td class="td-right text-mono fw-600" style="color: {{ $fila['utilidad'] >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                            ${{ number_format($fila['utilidad'], 2, '.', ',') }}
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="8" class="text-center text-muted" style="padding: 40px;">No hay datos con los filtros aplicados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-2">
    <strong>Nota:</strong> El costo se obtiene del producto (costo o costo promedio). Conceptos sin producto asignado tienen costo cero.
</p>

@endsection
