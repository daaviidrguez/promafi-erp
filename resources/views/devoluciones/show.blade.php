@extends('layouts.app')

@section('title', 'Devolución #' . $devolucion->id)
@section('page-title', '↩️ Devolución #' . $devolucion->id)
@section('page-subtitle', 'Factura ' . $devolucion->factura->folio_completo)

@php
$breadcrumbs = [
    ['title' => 'Devoluciones', 'url' => route('devoluciones.index')],
    ['title' => 'Devolución #' . $devolucion->id]
];
@endphp

@section('content')

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Factura relacionada</div>
            </div>
            <div class="card-body">
                <a href="{{ route('facturas.show', $devolucion->factura_id) }}" class="fw-600 text-primary">{{ $devolucion->factura->folio_completo }}</a>
                <div class="text-muted" style="font-size: 12px;">{{ $devolucion->factura->fecha_emision->format('d/m/Y') }} · ${{ number_format($devolucion->factura->total, 2, '.', ',') }}</div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="card-title">Líneas devueltas</div>
            </div>
            <div class="table-container" style="border: none; box-shadow: none;">
                <table>
                    <thead>
                        <tr>
                            <th>Descripción</th>
                            <th class="td-center">Cant. devuelta</th>
                            <th class="td-right">P. unit.</th>
                            <th class="td-right">Importe</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($devolucion->detalles as $d)
                        @php $fd = $d->facturaDetalle; @endphp
                        <tr>
                            <td>{{ $fd ? $fd->descripcion : '-' }}</td>
                            <td class="td-center">{{ number_format($d->cantidad_devuelta, 2) }}</td>
                            <td class="td-right text-mono">${{ $fd ? number_format($fd->valor_unitario, 2, '.', ',') : '0.00' }}</td>
                            <td class="td-right text-mono fw-600">${{ $fd ? number_format($fd->valor_unitario * $d->cantidad_devuelta, 2, '.', ',') : '0.00' }}</td>
                            <td class="text-muted" style="font-size: 12px;">{{ $d->motivo_linea ?? '-' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="display: flex; justify-content: flex-end;">
                <div class="totales-panel">
                    <div class="totales-row grand">
                        <span>Total devuelto</span>
                        <span class="monto">${{ number_format($devolucion->total_devuelto, 2, '.', ',') }}</span>
                    </div>
                </div>
            </div>
        </div>

        @if($devolucion->motivo || $devolucion->observaciones)
        <div class="card">
            <div class="card-body">
                @if($devolucion->motivo)
                    <p><strong>Motivo:</strong> {{ $devolucion->motivo }}</p>
                @endif
                @if($devolucion->observaciones)
                    <p><strong>Observaciones:</strong> {{ $devolucion->observaciones }}</p>
                @endif
            </div>
        </div>
        @endif
    </div>

    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Estado</div>
            </div>
            <div class="card-body">
                @if($devolucion->estado === 'borrador')
                    <span class="badge badge-warning">Borrador</span>
                    <p class="mt-2 text-muted" style="font-size: 13px;">Autoriza la devolución para poder generar la nota de crédito.</p>
                    <form method="POST" action="{{ route('devoluciones.autorizar', $devolucion->id) }}" class="mt-2">
                        @csrf
                        <button type="submit" class="btn btn-success w-full">Autorizar devolución</button>
                    </form>
                @elseif($devolucion->estado === 'autorizada')
                    <span class="badge badge-success">Autorizada</span>
                    @if($devolucion->puedeGenerarNotaCredito())
                        <a href="{{ route('notas-credito.create', ['devolucion_id' => $devolucion->id]) }}" class="btn btn-primary w-full mt-3">Generar nota de crédito</a>
                    @endif
                @else
                    <span class="badge badge-secondary">Cerrada</span>
                @endif
            </div>
        </div>

        @if($devolucion->notasCredito->isNotEmpty())
        <div class="card">
            <div class="card-header">
                <div class="card-title">Notas de crédito</div>
            </div>
            <div class="card-body">
                <ul style="list-style: none; padding: 0;">
                    @foreach($devolucion->notasCredito as $nc)
                    <li style="margin-bottom: 8px;">
                        <a href="{{ route('notas-credito.show', $nc->id) }}" class="text-primary">{{ $nc->folio_completo }}</a>
                        <span class="badge {{ $nc->estado === 'timbrada' ? 'badge-success' : 'badge-warning' }}">{{ $nc->estado }}</span>
                    </li>
                    @endforeach
                </ul>
            </div>
        </div>
        @endif

        <a href="{{ route('devoluciones.index') }}" class="btn btn-light w-full">← Volver al listado</a>
    </div>
</div>

@endsection
