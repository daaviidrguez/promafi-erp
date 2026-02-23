@extends('layouts.app')
@section('title', 'Notas de Crédito')
@section('page-title', 'Notas de Crédito')
@section('page-subtitle', 'CFDI tipo E')
@php $breadcrumbs = [['title' => 'Notas de Crédito']]; @endphp
@section('content')
<div class="card"><div class="card-body">
    <form method="GET" action="{{ route('notas-credito.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;">
        <select name="estado" class="form-control" style="min-width:150px;">
            <option value="">Todos</option>
            <option value="borrador" {{ ($estado ?? '') == 'borrador' ? 'selected' : '' }}>Borrador</option>
            <option value="timbrada" {{ ($estado ?? '') == 'timbrada' ? 'selected' : '' }}>Timbrada</option>
            <option value="cancelada" {{ ($estado ?? '') == 'cancelada' ? 'selected' : '' }}>Cancelada</option>
        </select>
        <select name="cliente_id" class="form-control" style="min-width:200px;">
            <option value="">Todos los clientes</option>
            @foreach($clientes as $c)
                <option value="{{ $c->id }}" {{ ($cliente_id ?? '') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
            @endforeach
        </select>
        <button type="submit" class="btn btn-primary">Filtrar</button>
        <a href="{{ route('notas-credito.create') }}" class="btn btn-primary">Nueva nota de crédito</a>
    </form>
</div></div>
<div class="table-container">
@if($notas->count() > 0)
<table>
<thead><tr><th>Folio</th><th>Factura</th><th>Cliente</th><th>Fecha</th><th class="td-right">Total</th><th class="td-center">Estado</th><th class="td-actions">Acciones</th></tr></thead>
<tbody>
@foreach($notas as $nc)
<tr>
    <td><span class="text-mono fw-600">{{ $nc->folio_completo }}</span>@if($nc->uuid)<div class="text-muted" style="font-size:11px;">{{ substr($nc->uuid, 0, 18) }}...</div>@endif</td>
    <td><a href="{{ route('facturas.show', $nc->factura_id) }}">{{ $nc->factura->folio_completo ?? '' }}</a></td>
    <td>{{ $nc->cliente->nombre ?? '' }}</td>
    <td>{{ $nc->fecha_emision->format('d/m/Y') }}</td>
    <td class="td-right text-mono fw-600">${{ number_format($nc->total, 2, '.', ',') }}</td>
    <td class="td-center">@if($nc->estado === 'timbrada')<span class="badge badge-success">Timbrada</span>@elseif($nc->estado === 'borrador')<span class="badge badge-warning">Borrador</span>@else<span class="badge badge-danger">Cancelada</span>@endif</td>
    <td class="td-actions"><a href="{{ route('notas-credito.show', $nc->id) }}" class="btn btn-info btn-sm">Ver</a></td>
</tr>
@endforeach
</tbody>
</table>
<div style="padding:16px;">{{ $notas->withQueryString()->links() }}</div>
@else
<div class="empty-state"><div class="empty-state-title">No hay notas de crédito</div><div class="empty-state-text">Crea una desde una factura timbrada o desde una devolución autorizada.</div><a href="{{ route('notas-credito.create') }}" class="btn btn-primary mt-3">Nueva nota de crédito</a></div>
@endif
</div>
@endsection
