@extends('layouts.app')
@section('title', 'Remisión ' . $remision->folio)
@section('page-title', '🚚 ' . $remision->folio)
@section('page-subtitle', $remision->cliente_nombre)

@php
$breadcrumbs = [
    ['title' => 'Remisiones', 'url' => route('remisiones.index')],
    ['title' => $remision->folio],
];
@endphp

@section('content')

<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">👥 Cliente</div>
                <a href="{{ route('clientes.show', $remision->cliente_id) }}" class="btn btn-light btn-sm">Ver cliente</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Razón Social</div><div class="info-value">{{ $remision->cliente_nombre }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $remision->cliente_rfc ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Fecha</div><div class="info-value">{{ $remision->fecha->format('d/m/Y') }}</div></div>
                    @if($remision->fecha_entrega)<div class="info-row"><div class="info-label">Fecha entrega</div><div class="info-value">{{ $remision->fecha_entrega->format('d/m/Y') }}</div></div>@endif
                    @if($remision->direccion_entrega)<div class="info-row"><div class="info-label">Dirección de entrega</div><div class="info-value" style="white-space:pre-wrap;">{{ $remision->direccion_entrega }}</div></div>@endif
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div class="card-title">📦 Detalle</div></div>
            <div class="table-container" style="border:none;box-shadow:none;margin-bottom:0;">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="td-center">Cantidad</th>
                            <th class="td-center">Unidad</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($remision->detalles as $d)
                        <tr>
                            <td class="text-mono">{{ $d->codigo ?? '—' }}</td>
                            <td>{{ $d->descripcion }}</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-center">{{ $d->unidad }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if($remision->observaciones)
            <div class="card-body" style="border-top:1px solid var(--color-gray-100);">
                <div class="info-row"><div class="info-label">Observaciones</div><div class="info-value">{{ $remision->observaciones }}</div></div>
            </div>
            @endif
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Estado</div></div>
            <div class="card-body">
                @if($remision->estado === 'borrador')
                <span class="badge badge-warning" style="font-size:14px;">Borrador</span>
                <p style="margin-top:12px;font-size:13px;">Puedes editar o enviar la remisión.</p>
                @elseif($remision->estado === 'enviada')
                <span class="badge badge-info" style="font-size:14px;">Enviada</span>
                <p style="margin-top:12px;font-size:13px;">Marca como entregada cuando el cliente reciba la mercancía.</p>
                @elseif($remision->estado === 'entregada')
                <span class="badge badge-success" style="font-size:14px;">Entregada</span>
                <p style="margin-top:12px;font-size:13px;">Entrega registrada.</p>
                @else
                <span class="badge badge-danger" style="font-size:14px;">Cancelada</span>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                @if($remision->puedeEditarse())
                <a href="{{ route('remisiones.edit', $remision->id) }}" class="btn btn-primary w-full">✏️ Editar</a>
                <form method="POST" action="{{ route('remisiones.enviar', $remision->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-success w-full">📤 Marcar como enviada</button>
                </form>
                <form method="POST" action="{{ route('remisiones.destroy', $remision->id) }}" style="margin:0;" onsubmit="return confirm('¿Eliminar esta remisión?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar</button>
                </form>
                @endif
                @if($remision->puedeEntregarse())
                <form method="POST" action="{{ route('remisiones.entregar', $remision->id) }}" style="margin:0;">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">✅ Marcar como entregada</button>
                </form>
                @endif
                @if($remision->puedeCancelarse() && $remision->estado !== 'borrador')
                <form method="POST" action="{{ route('remisiones.cancelar', $remision->id) }}" style="margin:0;" onsubmit="return confirm('¿Cancelar esta remisión?');">
                    @csrf
                    <button type="submit" class="btn btn-outline w-full" style="border-color:var(--color-danger);color:var(--color-danger);">Cancelar remisión</button>
                </form>
                @endif
                <a href="{{ route('remisiones.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>
    </div>
</div>

@endsection
