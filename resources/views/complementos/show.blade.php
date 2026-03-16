@extends('layouts.app')

@section('title', 'Complemento ' . $complemento->folio_completo)
@section('page-title', '💳 Complemento ' . $complemento->folio_completo)
@section('page-subtitle', $complemento->cliente->nombre)

@php
$breadcrumbs = [
    ['title' => 'Complementos de Pago', 'url' => route('complementos.index')],
    ['title' => $complemento->folio_completo]
];
@endphp

@section('content')

<div class="responsive-grid" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

    {{-- Columna izquierda --}}
    <div>

        {{-- Receptor --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">👤 Receptor</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Nombre / Razón Social</div>
                        <div class="info-value">{{ $complemento->nombre_receptor }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RFC</div>
                        <div class="info-value text-mono">{{ $complemento->rfc_receptor }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Pagos --}}
        @foreach($complemento->pagosRecibidos as $pago)
        <div class="card">
            <div class="card-header">
                <div class="card-title">💵 Datos del Pago</div>
                <span class="text-mono fw-600" style="color: var(--color-success); font-size: 18px;">
                    ${{ number_format($pago->monto, 2, '.', ',') }}
                </span>
            </div>
            <div class="card-body">
                <div class="info-grid-2" style="margin-bottom: 20px;">
                    <div class="info-row">
                        <div class="info-label">Fecha de Pago</div>
                        <div class="info-value">{{ $pago->fecha_pago->format('d/m/Y') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Forma de Pago</div>
                        <div class="info-value">{{ $pago->forma_pago }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Moneda</div>
                        <div class="info-value">{{ $pago->moneda }}</div>
                    </div>
                    @if($pago->num_operacion)
                    <div class="info-row">
                        <div class="info-label">No. de Operación</div>
                        <div class="info-value text-mono">{{ $pago->num_operacion }}</div>
                    </div>
                    @endif
                </div>

                {{-- Facturas pagadas --}}
                <div style="font-size: 13px; font-weight: 700; color: var(--color-primary); margin-bottom: 12px;">
                    🧾 Facturas Pagadas
                </div>

                <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Factura</th>
                                <th class="td-center">Parcialidad</th>
                                <th class="td-right">Saldo Anterior</th>
                                <th class="td-right">Monto Pagado</th>
                                <th class="td-right">Saldo Insoluto</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pago->documentosRelacionados as $doc)
                            @php
                                $cuenta = $doc->factura->cuentaPorCobrar;
                                $saldoActual = $cuenta ? (float) $cuenta->saldo_pendiente_real : 0;
                                $saldoAnterior = $saldoActual + (float) $doc->monto_pagado;
                                $saldoInsoluto = $saldoActual;
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('facturas.show', $doc->factura->id) }}"
                                       class="text-mono fw-600" style="color: var(--color-primary);">
                                        {{ $doc->factura->folio_completo }}
                                    </a>
                                    <div class="text-mono text-muted" style="font-size: 11px;">
                                        {{ substr($doc->factura_uuid, 0, 20) }}...
                                    </div>
                                </td>
                                <td class="td-center">
                                    <span class="badge badge-info">Parc. {{ $doc->parcialidad }}</span>
                                </td>
                                <td class="td-right text-mono">
                                    ${{ number_format($saldoAnterior, 2, '.', ',') }}
                                </td>
                                <td class="td-right text-mono fw-600" style="color: var(--color-success);">
                                    ${{ number_format($doc->monto_pagado, 2, '.', ',') }}
                                </td>
                                <td class="td-right text-mono fw-600"
                                    style="color: {{ $saldoInsoluto > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
                                    ${{ number_format($saldoInsoluto, 2, '.', ',') }}
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach

    </div>

    {{-- Columna derecha --}}
    <div>

        {{-- Información Fiscal --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información Fiscal</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @if($complemento->estado === 'timbrado')
                            <span class="badge badge-success">Timbrado</span>
                        @elseif($complemento->estado === 'borrador')
                            <span class="badge badge-warning">📝 Borrador</span>
                        @else
                            <span class="badge badge-danger">✗ Cancelado</span>
                        @endif
                    </div>
                </div>
                <div class="info-row" style="margin-top: 16px;">
                    <div class="info-label">Fecha de Emisión</div>
                    <div class="info-value-sm">{{ $complemento->fecha_emision->format('d/m/Y') }}</div>
                </div>
                @if($complemento->fecha_timbrado)
                <div class="info-row">
                    <div class="info-label">Fecha de Timbrado</div>
                    <div class="info-value-sm" style="color: var(--color-success);">
                        {{ $complemento->fecha_timbrado->format('d/m/Y H:i:s') }}
                    </div>
                </div>
                @endif
                @if($complemento->uuid)
                <div class="info-row">
                    <div class="info-label">UUID / Folio Fiscal</div>
                    <div style="background: var(--color-gray-50); border-radius: var(--radius-sm); padding: 8px; font-family: var(--font-mono); font-size: 11px; word-break: break-all; margin-top: 4px;">
                        {{ $complemento->uuid }}
                    </div>
                </div>
                @endif
                <div class="info-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-100);">
                    <div class="info-label">Monto Total</div>
                    <div class="info-value" style="font-size: 22px;">
                        ${{ number_format($complemento->monto_total, 2, '.', ',') }}
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">Acciones</div>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">

                @if($complemento->estado === 'borrador')
                <a href="{{ route('complementos.edit', $complemento->id) }}" class="btn btn-outline w-full">✏️ Editar</a>
                <form method="POST" action="{{ route('complementos.destroy', $complemento->id) }}" onsubmit="return confirm('¿Eliminar este complemento de pago en borrador?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline w-full" style="color: var(--color-danger);">🗑️ Eliminar</button>
                </form>
                <p class="text-muted" style="font-size: 13px; margin: 0 0 8px 0;">
                    Al emitir se timbrará el complemento y se aplicará el pago a las cuentas por cobrar.
                </p>
                <form method="POST" action="{{ route('complementos.timbrar', $complemento->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">Emitir complemento</button>
                </form>
                @endif

                @if($complemento->estado === 'timbrado')
                    <a href="{{ route('complementos.ver-pdf', $complemento->id) }}"
                       target="_blank" class="btn btn-outline w-full">Ver PDF</a>
                    <a href="{{ route('complementos.descargar-pdf', $complemento->id) }}"
                       class="btn btn-outline w-full">Descargar PDF</a>
                    @if($complemento->xml_path)
                    <a href="{{ route('complementos.descargar-xml', $complemento->id) }}"
                       class="btn btn-success w-full">Descargar XML</a>
                    @endif
                    @if($complemento->puedeCancelar())
                    <button type="button" onclick="document.getElementById('modalCancelarComplemento').classList.add('show')"
                            class="btn btn-danger w-full">✗ Cancelar complemento</button>
                    @endif
                @endif

                @if($complemento->estado === 'cancelado')
                    @if(!empty($complemento->acuse_cancelacion))
                    <a href="{{ route('complementos.descargar-xml-cancelacion', $complemento->id) }}"
                       class="btn btn-outline w-full">📄 XML cancelado</a>
                    @else
                    <a href="{{ route('complementos.obtener-acuse-cancelacion', $complemento->id) }}"
                       class="btn btn-outline w-full">📄 Obtener XML cancelado</a>
                    @endif
                @endif

                <a href="{{ route('complementos.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>

    </div>
</div>

{{-- Modal Cancelar complemento --}}
@if($complemento->puedeCancelar())
<div id="modalCancelarComplemento" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--color-danger);">⚠️ Cancelar complemento de pago</div>
            <button class="modal-close" onclick="document.getElementById('modalCancelarComplemento').classList.remove('show')">✕</button>
        </div>
        <form method="POST" action="{{ route('complementos.cancelar', $complemento->id) }}" id="formCancelarComplemento">
            @csrf
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 20px;">
                    ¿Está seguro de cancelar este complemento? Se revertirán los pagos aplicados en las cuentas por cobrar. Esta acción es irreversible.
                </p>
                <div class="form-group">
                    <label class="form-label">Motivo de cancelación <span class="req">*</span></label>
                    <select name="motivo_cancelacion" id="cancelComplementoMotivo" class="form-control" required>
                        <option value="03" selected>03 - No se llevó a cabo la operación</option>
                        <option value="01">01 - Comprobante emitido con errores con relación</option>
                        <option value="02">02 - Comprobante emitido con errores sin relación</option>
                        <option value="04">04 - Operación nominativa relacionada en factura global</option>
                    </select>
                </div>
                <div id="bloqueComplementoUuidSustituto" class="form-group" style="display: none;">
                    <label class="form-label">UUID Sustituto <span class="req">*</span></label>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="inputComplementoUuidSustitutoDisplay" class="form-control" readonly placeholder="Seleccione el complemento que sustituye" style="flex: 1; min-width: 200px; background: var(--color-gray-50);">
                        <input type="hidden" name="uuid_sustituto" id="inputComplementoUuidSustituto" value="">
                        <button type="button" class="btn btn-outline-primary" onclick="abrirModalSeleccionarComplementoSustituto()">Seleccionar complemento que sustituye</button>
                    </div>
                    <span class="form-hint">Obligatorio cuando el motivo es 01 (SAT): UUID del CFDI que reemplaza a este complemento.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="document.getElementById('modalCancelarComplemento').classList.remove('show')">Cerrar</button>
                <button type="submit" class="btn btn-danger">Confirmar cancelación</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal seleccionar complemento sustituto (motivo 01) --}}
<div id="modalSeleccionarComplementoSustituto" class="modal">
    <div class="modal-box" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar complemento que sustituye</div>
            <button class="modal-close" onclick="cerrarModalComplementoSustituto()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom: 12px;">Elija el complemento de pago timbrado que reemplaza a este (UUID sustituto).</p>
            <div class="table-container" style="max-height: 320px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Monto</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="listaComplementosSustituto"></tbody>
                </table>
            </div>
            <div id="cargandoComplementoSustituto" style="text-align: center; padding: 20px; color: var(--color-gray-500);">Cargando complementos...</div>
            <div id="sinComplementosSustituto" style="display: none; text-align: center; padding: 20px; color: var(--color-gray-500);">No hay complementos timbrados para seleccionar.</div>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var complementoIdExcluir = {{ $complemento->id }};
    var listarComplementosUrl = '{{ route("complementos.listar-para-relacion") }}?excluir_id=' + complementoIdExcluir;

    window.toggleComplementoUuidSustituto = function() {
        var motivo = document.getElementById('cancelComplementoMotivo').value;
        var bloque = document.getElementById('bloqueComplementoUuidSustituto');
        var input = document.getElementById('inputComplementoUuidSustituto');
        if (motivo === '01') {
            bloque.style.display = 'block';
            input.setAttribute('required', 'required');
        } else {
            bloque.style.display = 'none';
            input.removeAttribute('required');
            input.value = '';
            document.getElementById('inputComplementoUuidSustitutoDisplay').value = '';
        }
    };

    document.getElementById('cancelComplementoMotivo').addEventListener('change', toggleComplementoUuidSustituto);

    window.abrirModalSeleccionarComplementoSustituto = function() {
        document.getElementById('modalSeleccionarComplementoSustituto').classList.add('show');
        document.getElementById('listaComplementosSustituto').innerHTML = '';
        document.getElementById('cargandoComplementoSustituto').style.display = 'block';
        document.getElementById('sinComplementosSustituto').style.display = 'none';
        fetch(listarComplementosUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('cargandoComplementoSustituto').style.display = 'none';
                var tbody = document.getElementById('listaComplementosSustituto');
                if (!data || data.length === 0) {
                    document.getElementById('sinComplementosSustituto').style.display = 'block';
                    return;
                }
                data.forEach(function(c) {
                    var tr = document.createElement('tr');
                    tr.innerHTML = '<td class="text-mono">' + (c.folio_completo || '') + '</td>' +
                        '<td>' + (c.cliente || '') + '</td>' +
                        '<td>' + (c.fecha || '') + '</td>' +
                        '<td class="text-right">$' + (typeof c.monto_total === 'number' ? c.monto_total.toFixed(2) : c.monto_total) + '</td>' +
                        '<td><button type="button" class="btn btn-primary btn-sm" onclick="elegirComplementoSustituto(\'' + (c.uuid || '') + '\', \'' + (c.folio_completo || '').replace(/'/g, "\\'") + '\')">Seleccionar</button></td>';
                    tbody.appendChild(tr);
                });
            })
            .catch(function() {
                document.getElementById('cargandoComplementoSustituto').style.display = 'none';
                document.getElementById('sinComplementosSustituto').style.display = 'block';
            });
    };

    window.cerrarModalComplementoSustituto = function() {
        document.getElementById('modalSeleccionarComplementoSustituto').classList.remove('show');
    };

    window.elegirComplementoSustituto = function(uuid, folio) {
        document.getElementById('inputComplementoUuidSustituto').value = uuid;
        document.getElementById('inputComplementoUuidSustitutoDisplay').value = folio ? folio + ' — ' + uuid : uuid;
        cerrarModalComplementoSustituto();
    };
})();
</script>
@endpush
@endif

@endsection