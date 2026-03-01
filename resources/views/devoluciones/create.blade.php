@extends('layouts.app')

@section('title', 'Registrar devolución')
@section('page-title', 'Registrar devolución')
@section('page-subtitle', 'Factura ' . $factura->folio_completo)

@php
$breadcrumbs = [
    ['title' => 'Devoluciones', 'url' => route('devoluciones.index')],
    ['title' => 'Nueva devolución']
];
@endphp

@section('content')

@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())
<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
@endif

<form method="POST" action="{{ route('devoluciones.store') }}">
@csrf
<input type="hidden" name="factura_id" value="{{ $factura->id }}">

<div class="card">
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Fecha devolución *</label>
                <input type="date" name="fecha_devolucion" class="form-control" value="{{ old('fecha_devolucion', now()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Motivo</label>
                <input type="text" name="motivo" class="form-control" maxlength="100" value="{{ old('motivo') }}">
            </div>
        </div>
        <div class="form-group">
            <label class="form-label">Observaciones</label>
            <textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones') }}</textarea>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><div class="card-title">Líneas a devolver</div></div>
    <div class="table-container" style="border:none;">
        <table>
            <thead>
                <tr>
                    <th>Descripción</th>
                    <th class="td-center">Cant. facturada</th>
                    <th class="td-center">Cant. ya devuelta</th>
                    <th class="td-center">Cant. pendiente</th>
                    <th class="td-right">P. unit.</th>
                    <th class="td-center">Cant. a devolver</th>
                    <th>Motivo línea</th>
                </tr>
            </thead>
            <tbody>
                @foreach($factura->detalles as $i => $d)
                @php
                    $yaDevuelto = $cantidadesDevueltas->get($d->id, 0);
                    $cantPendiente = (float) $d->cantidad - $yaDevuelto;
                @endphp
                <tr>
                    <td>
                        <input type="hidden" name="lineas[{{ $i }}][factura_detalle_id]" value="{{ $d->id }}">
                        {{ $d->descripcion }}
                    </td>
                    <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                    <td class="td-center text-muted">{{ number_format($yaDevuelto, 2) }}</td>
                    <td class="td-center fw-600">{{ number_format($cantPendiente, 2) }}</td>
                    <td class="td-right text-mono">${{ number_format($d->valor_unitario, 2, '.', ',') }}</td>
                    <td class="td-center">
                        <input type="number" name="lineas[{{ $i }}][cantidad_devuelta]" class="form-control" min="0" max="{{ $cantPendiente }}" step="0.01" value="{{ old('lineas.'.$i.'.cantidad_devuelta', 0) }}" style="width:100px;margin:0 auto;text-align:right;" placeholder="0">
                    </td>
                    <td><input type="text" name="lineas[{{ $i }}][motivo_linea]" class="form-control" maxlength="255" value="{{ old('lineas.'.$i.'.motivo_linea') }}"></td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <a href="{{ route('facturas.show', $factura->id) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar devolución</button>
    </div>
</div>

{{-- Historial de devoluciones --}}
@if($devolucionesAnteriores->isNotEmpty())
<div class="card">
    <div class="card-header">
        <div class="card-title">📋 Historial de devoluciones</div>
    </div>
    <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
        <table>
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Devolución</th>
                    <th class="td-center">Estado</th>
                    <th class="td-right">Total devuelto</th>
                </tr>
            </thead>
            <tbody>
                @foreach($devolucionesAnteriores as $dev)
                <tr>
                    <td>{{ $dev->fecha_devolucion->format('d/m/Y') }}</td>
                    <td>
                        <a href="{{ route('devoluciones.show', $dev->id) }}" class="text-primary fw-600">Devolución #{{ $dev->id }}</a>
                    </td>
                    <td class="td-center">
                        @if($dev->estado === 'borrador')
                            <span class="badge badge-warning">Borrador</span>
                        @elseif($dev->estado === 'autorizada')
                            <span class="badge badge-success">Autorizada</span>
                        @else
                            <span class="badge badge-secondary">{{ $dev->estado }}</span>
                        @endif
                    </td>
                    <td class="td-right text-mono fw-600">${{ number_format($dev->total_devuelto, 2, '.', ',') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <p class="text-muted" style="font-size: 12px; margin: 0;">Cantidades ya devueltas se reflejan en la tabla de líneas arriba. El máximo permitido por línea es la cantidad pendiente.</p>
    </div>
</div>
@endif
</form>

@endsection
