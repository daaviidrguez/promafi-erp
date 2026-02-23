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
                    <th class="td-right">P. unit.</th>
                    <th class="td-center">Cant. a devolver</th>
                    <th>Motivo línea</th>
                </tr>
            </thead>
            <tbody>
                @foreach($factura->detalles as $i => $d)
                <tr>
                    <td>
                        <input type="hidden" name="lineas[{{ $i }}][factura_detalle_id]" value="{{ $d->id }}">
                        {{ $d->descripcion }}
                    </td>
                    <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                    <td class="td-right text-mono">${{ number_format($d->valor_unitario, 2, '.', ',') }}</td>
                    <td class="td-center">
                        <input type="number" name="lineas[{{ $i }}][cantidad_devuelta]" class="form-control" min="0" max="{{ $d->cantidad }}" step="0.01" value="{{ old('lineas.'.$i.'.cantidad_devuelta', 0) }}" style="width:100px;margin:0 auto;text-align:right;">
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
</form>

@endsection
