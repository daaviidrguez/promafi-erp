@extends('layouts.app')
@section('title', 'Entradas anticipadas')
@section('page-title', '📥 Entradas anticipadas')
@section('page-subtitle', 'Recepción de mercancía antes de factura')
@section('page-actions')
    <a href="{{ route('entradas-anticipadas.create') }}" class="btn btn-primary">➕ Nueva entrada</a>
@endsection

@php $breadcrumbs = [['title' => 'Entradas anticipadas']]; @endphp

@section('content')

<div class="stats-grid" style="margin-bottom:20px;">
    <div class="stat-info-box"><div class="stat-label">Borrador</div><div class="stat-value">{{ $estadisticas['borrador'] ?? 0 }}</div></div>
    <div class="stat-info-box"><div class="stat-label">Confirmadas</div><div class="stat-value">{{ $estadisticas['confirmada'] ?? 0 }}</div></div>
    <div class="stat-info-box"><div class="stat-label">Facturadas</div><div class="stat-value">{{ $estadisticas['facturada'] ?? 0 }}</div></div>
    <div class="stat-info-box"><div class="stat-label">Canceladas</div><div class="stat-value">{{ $estadisticas['cancelada'] ?? 0 }}</div></div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="EA-0001, proveedor, OC…" class="form-control" style="min-width:220px;">
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Estado</label>
                <select name="estado" class="form-control">
                    <option value="">Todos</option>
                    @foreach(['borrador','confirmada','parcialmente_facturada','facturada','cancelada'] as $e)
                    <option value="{{ $e }}" {{ request('estado')===$e?'selected':'' }}>{{ ucfirst(str_replace('_',' ',$e)) }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<div class="table-container">
    @if($entradas->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Proveedor</th>
                <th>OC</th>
                <th>Fecha recepción</th>
                <th class="td-right">Total est.</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($entradas as $ea)
            <tr>
                <td class="text-mono fw-600">{{ $ea->folio }}</td>
                <td>{{ $ea->proveedor?->nombre }}</td>
                <td class="text-mono">{{ $ea->ordenCompra?->folio ?? '—' }}</td>
                <td>{{ $ea->fecha_recepcion->format('d/m/Y') }}</td>
                <td class="td-right text-mono">${{ number_format($ea->total, 2) }}</td>
                <td class="td-center">
                    @if($ea->estado === 'confirmada')<span class="badge badge-info">Confirmada</span>
                    @elseif($ea->estado === 'facturada')<span class="badge badge-success">Facturada</span>
                    @elseif($ea->estado === 'cancelada')<span class="badge badge-danger">Cancelada</span>
                    @elseif($ea->estado === 'borrador')<span class="badge badge-warning">Borrador</span>
                    @else<span class="badge badge-secondary">{{ $ea->etiquetaEstado() }}</span>@endif
                </td>
                <td class="td-actions"><a href="{{ route('entradas-anticipadas.show', $ea->id) }}" class="btn btn-info btn-sm">Ver</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px;border-top:1px solid var(--color-gray-100);">{{ $entradas->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">📥</div>
        <div class="empty-state-title">No hay entradas anticipadas</div>
        <div class="empty-state-text">Registra la recepción de mercancía antes de recibir la factura del proveedor</div>
        <a href="{{ route('entradas-anticipadas.create') }}" class="btn btn-primary" style="margin-top:16px;">➕ Nueva entrada</a>
    </div>
    @endif
</div>

@endsection
