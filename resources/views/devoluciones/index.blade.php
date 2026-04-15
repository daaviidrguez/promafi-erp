@extends('layouts.app')
@section('title', 'Devoluciones')
@section('page-title', 'Devoluciones')
@section('page-subtitle', 'Registro de devoluciones')
@php $breadcrumbs = [['title' => 'Devoluciones']]; @endphp
@section('content')
<div class="card"><div class="card-body">
    <form method="GET" action="{{ route('devoluciones.index') }}" style="display:flex;gap:12px;">
        <select name="estado" class="form-control" style="min-width:160px;">
            <option value="">Todos</option>
            <option value="borrador" {{ ($estado ?? '') == 'borrador' ? 'selected' : '' }}>Borrador</option>
            <option value="autorizada" {{ ($estado ?? '') == 'autorizada' ? 'selected' : '' }}>Autorizada</option>
            <option value="cerrada" {{ ($estado ?? '') == 'cerrada' ? 'selected' : '' }}>Cerrada</option>
            <option value="cancelada" {{ ($estado ?? '') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
    </form>
</div></div>
<div class="table-container">
@if($devoluciones->count() > 0)
<table>
<thead><tr><th>ID</th><th>Factura</th><th>Cliente</th><th>Fecha</th><th class="td-right">Total</th><th class="td-center">Estado</th><th class="td-actions">Acciones</th></tr></thead>
<tbody>
@foreach($devoluciones as $dev)
<tr>
    <td>{{ $dev->id }}</td>
    <td><a href="{{ route('facturas.show', $dev->factura_id) }}">{{ $dev->factura->folio_completo ?? '' }}</a></td>
    <td>{{ $dev->cliente->nombre ?? '' }}</td>
    <td>{{ $dev->fecha_devolucion->format('d/m/Y') }}</td>
    <td class="td-right text-mono">${{ number_format($dev->total_devuelto, 2, '.', ',') }}</td>
    <td class="td-center">
        @if($dev->estado === 'borrador')
            <span class="badge badge-warning">Borrador</span>
        @elseif($dev->estado === 'autorizada')
            <span class="badge badge-success">Autorizada</span>
        @elseif($dev->estado === 'cancelada')
            <span class="badge badge-danger">Cancelada</span>
        @else
            <span class="badge badge-secondary">Cerrada</span>
        @endif
    </td>
    <td class="td-actions"><a href="{{ route('devoluciones.show', $dev->id) }}" class="btn btn-info btn-sm">Ver</a></td>
</tr>
@endforeach
</tbody>
</table>
<div style="padding:16px;">{{ $devoluciones->withQueryString()->links() }}</div>
@else
<div class="empty-state"><div class="empty-state-title">No hay devoluciones</div><div class="empty-state-text">Registra una devolución desde la ficha de una factura timbrada.</div></div>
@endif
</div>
@endsection
