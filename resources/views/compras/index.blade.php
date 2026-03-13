@extends('layouts.app')
@section('title', 'Compras')
@section('page-title', '🛒 Compras')
@section('page-subtitle', 'Facturas de compra (directas o desde CFDI)')
@section('page-actions')
    <a href="{{ route('compras.upload-cfdi') }}" class="btn btn-success">📄 Leer CFDI</a>
    <a href="{{ route('compras.create') }}" class="btn btn-primary">➕ Registrar compra</a>
@endsection

@php $breadcrumbs = [['title' => 'Compras'], ['title' => 'Facturas de compra']]; @endphp

@section('content')

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('compras.index') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;">
                <label class="form-label">Buscar</label>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Folio, UUID, proveedor, RFC..." class="form-control" style="min-width:220px;">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
        </form>
    </div>
</div>

<div class="table-container">
    @if($compras->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Folio</th>
                <th>Proveedor</th>
                <th>Fecha</th>
                <th class="td-right">Total</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($compras as $c)
            <tr>
                <td class="text-mono fw-600">{{ $c->folio_completo }}</td>
                <td>
                    {{ $c->nombre_emisor }}
                    @if($c->uuid)<br><span class="text-muted" style="font-size:11px;">{{ \Illuminate\Support\Str::limit($c->uuid, 20) }}</span>@endif
                </td>
                <td>{{ $c->fecha_emision->format('d/m/Y') }}</td>
                <td class="td-right text-mono">${{ number_format($c->total, 2) }}</td>
                <td class="td-center">
                    @if($c->estado === 'recibida')<span class="badge badge-success">Recibida</span>
                    @elseif($c->estado === 'cancelada')<span class="badge badge-danger">Cancelada</span>
                    @elseif($c->estado === 'borrador')<span class="badge badge-warning">Borrador</span>
                    @else<span class="badge badge-info">Registrada</span>@endif
                </td>
                <td class="td-actions"><a href="{{ route('compras.show', $c->id) }}" class="btn btn-info btn-sm">Ver</a></td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px;border-top:1px solid var(--color-gray-100);">{{ $compras->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🛒</div>
        <div class="empty-state-title">No hay compras registradas</div>
        <div class="empty-state-text">Registra compras manualmente o sube un CFDI XML para cargar los datos automáticamente</div>
        <div style="display:flex;gap:12px;margin-top:16px;justify-content:center;flex-wrap:wrap;">
            <a href="{{ route('compras.upload-cfdi') }}" class="btn btn-success">📄 Leer CFDI</a>
            <a href="{{ route('compras.create') }}" class="btn btn-primary">➕ Registrar compra</a>
        </div>
    </div>
    @endif
</div>

@endsection
