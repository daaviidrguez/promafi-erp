@extends('layouts.app')
@section('title', 'Editar Nota de Crédito ' . $notaCredito->folio_completo)
@section('page-title', 'Editar Nota de Crédito ' . $notaCredito->folio_completo)
@section('page-subtitle', 'Factura ' . $factura->folio_completo)
@php
$breadcrumbs = [
    ['title' => 'Notas de Crédito', 'url' => route('notas-credito.index')],
    ['title' => $notaCredito->folio_completo, 'url' => route('notas-credito.show', $notaCredito->id)],
    ['title' => 'Editar']
];
$formasPagoNc = [
    '23' => '23 - Novación (recomendado si ya se pagó la factura)',
    '15' => '15 - Condonación (si no ha pagado y se perdona parte de la deuda)',
    '03' => '03 - Transferencia (solo si se devolverá el dinero al cliente)',
];
$cantidadesPorDetalle = $notaCredito->detalles->keyBy('factura_detalle_id');
@endphp
@section('content')
@if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
@if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

<form method="POST" action="{{ route('notas-credito.update', $notaCredito->id) }}">
@csrf
@method('PUT')

<div class="card">
    <div class="card-body">
        <div class="form-row">
            <div class="form-group">
                <label class="form-label">Fecha emisión *</label>
                <input type="date" name="fecha_emision" class="form-control" value="{{ old('fecha_emision', $notaCredito->fecha_emision?->format('Y-m-d') ?? now()->format('Y-m-d')) }}" required>
            </div>
            <div class="form-group">
                <label class="form-label">Motivo CFDI *</label>
                <select name="motivo_cfdi" class="form-control" required>
                    <option value="01" {{ old('motivo_cfdi', $notaCredito->motivo_cfdi ?? '02') == '01' ? 'selected' : '' }}>01 - Devolución</option>
                    <option value="02" {{ old('motivo_cfdi', $notaCredito->motivo_cfdi ?? '02') == '02' ? 'selected' : '' }}>02 - Anulación</option>
                    <option value="03" {{ old('motivo_cfdi', $notaCredito->motivo_cfdi) == '03' ? 'selected' : '' }}>03 - Errores con relación</option>
                    <option value="04" {{ old('motivo_cfdi', $notaCredito->motivo_cfdi) == '04' ? 'selected' : '' }}>04 - Bonificación</option>
                    <option value="05" {{ old('motivo_cfdi', $notaCredito->motivo_cfdi) == '05' ? 'selected' : '' }}>05 - Descuento</option>
                    <option value="06" {{ old('motivo_cfdi', $notaCredito->motivo_cfdi) == '06' ? 'selected' : '' }}>06 - Ajuste</option>
                    <option value="07" {{ old('motivo_cfdi', $notaCredito->motivo_cfdi) == '07' ? 'selected' : '' }}>07 - Ajuste inflación</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">Forma de pago *</label>
                <select name="forma_pago" class="form-control" required>
                    @foreach($formasPagoNc as $clave => $etiqueta)
                    <option value="{{ $clave }}" {{ old('forma_pago', $notaCredito->forma_pago ?? '23') == $clave ? 'selected' : '' }}>{{ $etiqueta }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group"><label class="form-label">Observaciones</label><textarea name="observaciones" class="form-control" rows="2">{{ old('observaciones', $notaCredito->observaciones) }}</textarea></div>
    </div>
</div>

@if($devolucion)
{{-- NC desde devolución: Líneas a acreditar --}}
<div class="card">
    <div class="card-header"><div class="card-title">Conceptos a acreditar (Líneas a devolver)</div></div>
    <div class="table-container" style="border:none;">
        <table>
            <thead><tr><th>Descripción</th><th class="td-center">Cant.</th><th class="td-right">P. unit.</th><th class="td-center">Cant. a acreditar</th></tr></thead>
            <tbody>
                @foreach($devolucion->detalles as $idx => $dd)
                @php $fd = $dd->facturaDetalle; $detNc = $fd ? $cantidadesPorDetalle->get($fd->id) : null; @endphp
                @if($fd)
                <tr>
                    <td><input type="hidden" name="lineas[{{ $idx }}][factura_detalle_id]" value="{{ $fd->id }}">{{ $fd->descripcion }}</td>
                    <td class="td-center">{{ number_format($fd->cantidad, 2) }}</td>
                    <td class="td-right text-mono">${{ number_format($fd->valor_unitario, 2, '.', ',') }}</td>
                    <td class="td-center"><input type="number" name="lineas[{{ $idx }}][cantidad]" class="form-control" min="0.01" max="{{ $fd->cantidad }}" step="0.01" value="{{ old('lineas.'.$idx.'.cantidad', $detNc?->cantidad ?? $dd->cantidad_devuelta) }}" style="width:100px;margin:0 auto;text-align:right;" required></td>
                </tr>
                @endif
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="card-body">
        <a href="{{ route('notas-credito.show', $notaCredito->id) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</div>
@else
{{-- NC directa: Monto a acreditar --}}
<div class="card">
    <div class="card-header"><div class="card-title">Monto a acreditar</div></div>
    <div class="card-body">
        <div class="info-box" style="margin-bottom: 16px;">
            <strong>Saldo restante de la factura:</strong>
            <span class="text-mono fw-600" style="font-size: 1.1em;">${{ number_format($saldoAcreditable ?? 0, 2, '.', ',') }}</span>
        </div>
        <div class="form-group">
            <label class="form-label">Monto a acreditar *</label>
            <input type="number" name="monto_a_acreditar" class="form-control" min="0.01" max="{{ $saldoAcreditable ?? 0 }}" step="0.01" value="{{ old('monto_a_acreditar', $notaCredito->total) }}" required style="max-width: 200px;">
            <span class="form-hint">Máximo: ${{ number_format($saldoAcreditable ?? 0, 2, '.', ',') }}</span>
        </div>
        <a href="{{ route('notas-credito.show', $notaCredito->id) }}" class="btn btn-light">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
    </div>
</div>
@endif
</form>
@endsection
