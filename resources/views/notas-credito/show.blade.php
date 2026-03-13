@extends('layouts.app')
@section('title', 'Nota de Crédito ' . $notaCredito->folio_completo)
@section('page-title', 'Nota de Crédito ' . $notaCredito->folio_completo)
@section('page-subtitle', $notaCredito->cliente->nombre ?? '')
@php
$breadcrumbs = [
    ['title' => 'Notas de Crédito', 'url' => route('notas-credito.index')],
    ['title' => $notaCredito->folio_completo]
];
@endphp
@section('content')
<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Receptor</div></div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Nombre</div><div class="info-value">{{ $notaCredito->nombre_receptor }}</div></div>
                    <div class="info-row"><div class="info-label">RFC</div><div class="info-value text-mono">{{ $notaCredito->rfc_receptor }}</div></div>
                    <div class="info-row"><div class="info-label">Factura que se acredita</div><div class="info-value"><a href="{{ route('facturas.show', $notaCredito->factura_id) }}">{{ $notaCredito->factura->folio_completo ?? '' }}</a></div></div>
                    @if($notaCredito->uuid_referencia)<div class="info-row"><div class="info-label">UUID factura</div><div class="info-value text-mono" style="font-size:11px;">{{ $notaCredito->uuid_referencia }}</div></div>@endif
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">Conceptos</div></div>
            <div class="table-container" style="border:none;">
                <table>
                    <thead><tr><th>Descripción</th><th class="td-center">Cant.</th><th class="td-right">P. unit.</th><th class="td-right">Importe</th></tr></thead>
                    <tbody>
                        @foreach($notaCredito->detalles as $d)
                        <tr>
                            <td>{{ $d->descripcion }}</td>
                            <td class="td-center">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-right text-mono">${{ number_format($d->valor_unitario, 2, '.', ',') }}</td>
                            <td class="td-right text-mono fw-600">${{ number_format($d->importe, 2, '.', ',') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body" style="display:flex;justify-content:flex-end;">
                <div class="totales-panel">
                    <div class="totales-row"><span>Subtotal</span><span class="monto">${{ number_format($notaCredito->subtotal, 2, '.', ',') }}</span></div>
                    @if($notaCredito->descuento > 0)<div class="totales-row"><span>Descuento</span><span class="monto">−${{ number_format($notaCredito->descuento, 2, '.', ',') }}</span></div>@endif
                    <div class="totales-row"><span>IVA</span><span class="monto">${{ number_format($notaCredito->calcularIVA(), 2, '.', ',') }}</span></div>
                    <div class="totales-row grand"><span>TOTAL</span><span class="monto">${{ number_format($notaCredito->total, 2, '.', ',') }}</span></div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">Estado</div></div>
            <div class="card-body">
                @if($notaCredito->estado === 'timbrada')<span class="badge badge-success">Timbrada</span>@elseif($notaCredito->estado === 'borrador')<span class="badge badge-warning">Borrador</span>@else<span class="badge badge-danger">Cancelada</span>@endif
                @if($notaCredito->fecha_timbrado)<div class="info-row mt-2"><div class="info-label">Fecha timbrado</div><div class="info-value-sm">{{ $notaCredito->fecha_timbrado->format('d/m/Y H:i') }}</div></div>@endif
                @if($notaCredito->uuid)<div class="info-row"><div class="info-label">UUID</div><div class="info-value-sm text-mono" style="font-size:11px;word-break:break-all;">{{ $notaCredito->uuid }}</div></div>@endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div class="card-title">Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                @if($notaCredito->estado === 'borrador')
                <a href="{{ route('notas-credito.edit', $notaCredito->id) }}" class="btn btn-outline w-full">✏️ Editar</a>
                <form method="POST" action="{{ route('notas-credito.destroy', $notaCredito->id) }}" onsubmit="return confirm('¿Eliminar esta nota de crédito en borrador? Se redirigirá a la factura.');">@csrf @method('DELETE')<button type="submit" class="btn btn-outline w-full" style="color:var(--color-danger);">🗑️ Eliminar</button></form>
                @endif
                <a href="{{ route('notas-credito.ver-pdf', $notaCredito->id) }}" target="_blank" class="btn btn-outline w-full">Ver PDF</a>
                @if($notaCredito->estado === 'borrador')
                <form method="POST" action="{{ route('notas-credito.timbrar', $notaCredito->id) }}">@csrf<button type="submit" class="btn btn-primary w-full">Emitir (timbrar) nota de crédito</button></form>
                @endif
                @if($notaCredito->estaTimbrada())
                    @if($notaCredito->pdf_path)<a href="{{ route('notas-credito.descargar-pdf', $notaCredito->id) }}" class="btn btn-outline w-full">Descargar PDF</a>@endif
                    @if($notaCredito->xml_path)<a href="{{ route('notas-credito.descargar-xml', $notaCredito->id) }}" class="btn btn-success w-full">Descargar XML</a>@endif
                @endif
                <a href="{{ route('notas-credito.index') }}" class="btn btn-light w-full">Volver</a>
            </div>
        </div>
    </div>
</div>
@endsection
