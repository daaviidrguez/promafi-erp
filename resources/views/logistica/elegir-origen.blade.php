@extends('layouts.app')
@section('title', 'Elegir documento — Logística')
@section('page-title', '📦 Nuevo envío')
@section('page-subtitle', 'Selecciona una factura o remisión para abrir el registro ya precargado')

@php
$breadcrumbs = [
    ['title' => 'Logística', 'url' => route('logistica.index')],
    ['title' => 'Elegir documento'],
];
@endphp

@section('page-actions')
    <div style="display:flex;flex-wrap:wrap;gap:10px;align-items:center;">
        <a href="{{ route('logistica.create') }}" class="btn btn-primary">➕ Nuevo envío (búsqueda libre)</a>
        @can('logistica.crear')
        <a href="{{ route('logistica.elegir-origen-masivo') }}" class="btn btn-outline">📋 Nuevo envío masivo</a>
        @endcan
        <a href="{{ route('logistica.index') }}" class="btn btn-light">← Envíos registrados</a>
    </div>
@endsection

@section('content')

<p class="text-muted" style="margin:0 0 16px;font-size:13px;">Elige un documento en las tablas para abrir el alta <strong>ya precargado</strong>, o usa el botón superior para el flujo con búsqueda manual.</p>

<div class="card" style="margin-bottom:16px;">
    <div class="card-body">
        <form method="GET" action="{{ route('logistica.elegir-origen') }}" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
            <div class="form-group" style="margin:0;flex:1;min-width:220px;">
                <label class="form-label">Buscar en ambas tablas</label>
                <input type="text" name="search" value="{{ $search }}" placeholder="Folio, cliente, UUID, RFC..." class="form-control">
            </div>
            <button type="submit" class="btn btn-primary">Buscar</button>
            @if($search)
                <a href="{{ route('logistica.elegir-origen') }}" class="btn btn-light">Limpiar</a>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">🧾 Facturas timbradas</div></div>
    <div class="table-container" style="border:none;margin:0;">
        @if($facturas->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th class="td-center">Estado</th>
                    <th class="td-center">Envío logística</th>
                    <th class="td-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($facturas as $f)
                <tr>
                    <td class="text-mono fw-600">{{ $f->folio_completo }}</td>
                    <td>{{ $f->cliente->nombre ?? $f->nombre_receptor }}</td>
                    <td>{{ $f->fecha_emision?->format('d/m/Y') }}</td>
                    <td class="td-center">
                        <span class="badge badge-success">Timbrada</span>
                    </td>
                    <td class="td-center">
                        @php
                            $enviosFacturaRemision = $f->logisticaEnvios;
                            if ($f->remisionVinculada) {
                                $enviosFacturaRemision = $enviosFacturaRemision->concat($f->remisionVinculada->logisticaEnvios);
                            }
                            $enviosFacturaRemision = $enviosFacturaRemision->unique('id')->sortByDesc('id');
                        @endphp
                        @if($enviosFacturaRemision->isNotEmpty())
                            <span class="text-mono" style="font-size:12px;">{{ $enviosFacturaRemision->pluck('folio')->join(', ') }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="td-actions">
                        @if($f->permiteNuevoEnvioLogistica())
                            <a href="{{ route('logistica.create', ['factura_id' => $f->id]) }}" class="btn btn-primary btn-sm">Nuevo envío</a>
                        @else
                            @php
                                $envioVer = $f->envioLogisticaParaAccionVer();
                                $tituloNoAplica = ($f->remisionVinculada && $f->remisionVinculada->estado === 'entregada')
                                    ? 'Factura desde remisión ya entregada; el envío se gestiona por la remisión.'
                                    : 'Hay un envío activo: continúa ahí hasta registrar entrega parcial o marcas en destino y queden partidas pendientes; si ya está todo entregado, usa Ver envío.';
                            @endphp
                            <span class="text-muted" style="font-size:12px;" title="{{ $tituloNoAplica }}">No aplica</span>
                            @if($envioVer)
                                <a href="{{ route('logistica.show', $envioVer) }}" class="btn btn-light btn-sm">Ver envío</a>
                            @endif
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:12px 16px;">{{ $facturas->links() }}</div>
        @else
            <p class="text-muted" style="padding:24px;margin:0;">No hay facturas timbradas con los filtros actuales.</p>
        @endif
    </div>
</div>

<div class="card" style="margin-top:20px;">
    <div class="card-header"><div class="card-title">🚚 Remisiones</div></div>
    <div class="table-container" style="border:none;margin:0;">
        @if($remisiones->count() > 0)
        <table>
            <thead>
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th class="td-center">Estado</th>
                    <th class="td-center">Envío logística</th>
                    <th class="td-actions">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach($remisiones as $r)
                <tr>
                    <td class="text-mono fw-600">{{ $r->folio }}</td>
                    <td>{{ $r->cliente_nombre }}</td>
                    <td>{{ $r->fecha?->format('d/m/Y') }}</td>
                    <td class="td-center">
                        @if($r->estado === 'borrador')<span class="badge badge-warning">Borrador</span>
                        @elseif($r->estado === 'enviada')<span class="badge badge-info">Enviada</span>
                        @elseif($r->estado === 'entregada')<span class="badge badge-success">Entregada</span>
                        @else<span class="badge badge-danger">Cancelada</span>@endif
                    </td>
                    <td class="td-center">
                        @if($r->logisticaEnvios->isNotEmpty())
                            <span class="text-mono" style="font-size:12px;">{{ $r->logisticaEnvios->pluck('folio')->join(', ') }}</span>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td class="td-actions">
                        @php
                            $ultimoEnvRem = $r->logisticaEnvios->first();
                        @endphp
                        @if($r->permiteNuevoEnvioDesdeElegirOrigen())
                            <a href="{{ route('logistica.create', ['remision_id' => $r->id]) }}" class="btn btn-primary btn-sm">Nuevo envío</a>
                        @endif
                        @if($ultimoEnvRem)
                            <a href="{{ route('logistica.show', $ultimoEnvRem) }}" class="btn btn-light btn-sm">Ver envío</a>
                        @elseif(!in_array($r->estado, ['enviada', 'entregada'], true))
                            <span class="text-muted" style="font-size:12px;">No enviada</span>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div style="padding:12px 16px;">{{ $remisiones->links() }}</div>
        @else
            <p class="text-muted" style="padding:24px;margin:0;">No hay remisiones con los filtros actuales.</p>
        @endif
    </div>
</div>

@endsection
