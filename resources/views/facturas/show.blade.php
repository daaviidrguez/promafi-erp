@extends('layouts.app')

@section('title', 'Factura ' . $factura->folio_completo)
@section('page-title', '🧾 Factura ' . $factura->folio_completo)
@section('page-subtitle', $factura->cliente->nombre)

@php
$breadcrumbs = [
    ['title' => 'Facturas', 'url' => route('facturas.index')],
    ['title' => $factura->folio_completo]
];
@endphp

@section('content')

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

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
                        <div class="info-value">{{ $factura->nombre_receptor }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RFC</div>
                        <div class="info-value text-mono">{{ $factura->rfc_receptor }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Uso de CFDI</div>
                        <div class="info-value">{{ $factura->uso_cfdi }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Régimen Fiscal</div>
                        <div class="info-value">{{ $factura->regimen_fiscal_receptor ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conceptos --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Conceptos</div>
            </div>
            <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Clave</th>
                            <th>Descripción</th>
                            <th class="td-center">Cant.</th>
                            <th class="td-right">P. Unit.</th>
                            <th class="td-right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($factura->detalles as $detalle)
                        <tr>
                            <td>
                                <span class="producto-row-code">{{ $detalle->clave_prod_serv }}</span>
                            </td>
                            <td>
                                <div class="fw-600">{{ $detalle->descripcion }}</div>
                                @if($detalle->no_identificacion)
                                    <div class="text-muted" style="font-size: 11px;">
                                        Clave: {{ $detalle->no_identificacion }}
                                    </div>
                                @endif
                                <div class="text-muted" style="font-size: 11px;">
                                    Unidad: {{ $detalle->unidad }}
                                </div>
                            </td>
                            <td class="td-center">{{ number_format($detalle->cantidad, 2) }}</td>
                            <td class="td-right text-mono">
                                ${{ number_format($detalle->valor_unitario, 2, '.', ',') }}
                            </td>
                            <td class="td-right text-mono fw-600">
                                ${{ number_format($detalle->importe, 2, '.', ',') }}
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Totales --}}
            <div class="card-body" style="display: flex; justify-content: flex-end;">
                <div class="totales-panel" style="min-width: 280px;">
                    <div class="totales-row">
                        <span>Subtotal</span>
                        <span class="monto">${{ number_format($factura->subtotal, 2, '.', ',') }}</span>
                    </div>
                    @if($factura->descuento > 0)
                    <div class="totales-row descuento">
                        <span>Descuento</span>
                        <span class="monto">−${{ number_format($factura->descuento, 2, '.', ',') }}</span>
                    </div>
                    @endif
                    <div class="totales-row">
                        <span>IVA</span>
                        <span class="monto">${{ number_format($factura->calcularIVA(), 2, '.', ',') }}</span>
                    </div>
                    <div class="totales-row grand">
                        <span>TOTAL</span>
                        <span class="monto">${{ number_format($factura->total, 2, '.', ',') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Observaciones --}}
        @if($factura->observaciones)
        <div class="card">
            <div class="card-header">
                <div class="card-title">📝 Observaciones</div>
            </div>
            <div class="card-body">
                <div class="info-value-sm" style="line-height: 1.7;">{{ $factura->observaciones }}</div>
            </div>
        </div>
        @endif

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
                        @if($factura->estado === 'timbrada')
                            <span class="badge badge-success">✓ Timbrada</span>
                        @elseif($factura->estado === 'borrador')
                            <span class="badge badge-warning">📝 Borrador</span>
                        @else
                            <span class="badge badge-danger">✗ Cancelada</span>
                        @endif
                    </div>
                </div>
                <div class="info-row" style="margin-top: 16px;">
                    <div class="info-label">Fecha de Emisión</div>
                    <div class="info-value-sm">{{ $factura->fecha_emision->format('d/m/Y') }}</div>
                </div>
                @if($factura->fecha_timbrado)
                <div class="info-row">
                    <div class="info-label">Fecha de Timbrado</div>
                    <div class="info-value-sm" style="color: var(--color-success);">
                        {{ $factura->fecha_timbrado->format('d/m/Y H:i:s') }}
                    </div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Método de Pago</div>
                    <div style="margin-top: 4px;">
                        @if($factura->metodo_pago === 'PUE')
                            <span class="badge badge-success">💵 PUE - Una exhibición</span>
                        @else
                            <span class="badge badge-warning">💳 PPD - Pago diferido</span>
                        @endif
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Forma de Pago</div>
                    <div class="info-value-sm">{{ $factura->forma_pago }}</div>
                </div>
                @if($factura->uuid)
                <div class="info-row">
                    <div class="info-label">UUID / Folio Fiscal</div>
                    <div style="background: var(--color-gray-50); border-radius: var(--radius-sm); padding: 8px; font-family: var(--font-mono); font-size: 11px; word-break: break-all; margin-top: 4px;">
                        {{ $factura->uuid }}
                    </div>
                </div>
                @endif
                @if($factura->no_certificado_sat)
                <div class="info-row">
                    <div class="info-label">No. Certificado SAT</div>
                    <div class="info-value-sm text-mono">{{ $factura->no_certificado_sat }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Acciones</div>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">

                @if($factura->esBorrador())
                <form method="POST" action="{{ route('facturas.timbrar', $factura->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">✓ Timbrar Factura</button>
                </form>
                @endif

                @if($factura->estaTimbrada())
                    @if($factura->xml_path)
                    <a href="{{ route('facturas.descargar-xml', $factura->id) }}"
                       class="btn btn-success w-full">📄 Descargar XML</a>
                    @endif

                    @if($factura->pdf_path)
                    <a href="{{ route('facturas.descargar-pdf', $factura->id) }}"
                       class="btn btn-outline w-full">📑 Descargar PDF</a>
                    @endif

                    <button type="button"
                            onclick="document.getElementById('modalCancelar').classList.add('show')"
                            class="btn btn-danger w-full">✗ Cancelar Factura</button>
                @endif

                <a href="{{ route('facturas.index') }}" class="btn btn-light w-full">← Volver</a>
            </div>
        </div>

        {{-- Cuenta por Cobrar --}}
        @if($factura->cuentaPorCobrar)
        <div class="card">
            <div class="card-header">
                <div class="card-title">💰 Cuenta por Cobrar</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Monto Total</div>
                    <div class="info-value">
                        ${{ number_format($factura->cuentaPorCobrar->monto_total, 2, '.', ',') }}
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Monto Pendiente</div>
                    <div class="info-value" style="color: var(--color-warning);">
                        ${{ number_format($factura->cuentaPorCobrar->monto_pendiente, 2, '.', ',') }}
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Vencimiento</div>
                    <div class="info-value-sm">
                        {{ $factura->cuentaPorCobrar->fecha_vencimiento->format('d/m/Y') }}
                        @if($factura->cuentaPorCobrar->estaVencida())
                            <span class="badge badge-danger" style="margin-left: 6px;">
                                ⚠ Vencida {{ $factura->cuentaPorCobrar->dias_vencido }}d
                            </span>
                        @endif
                    </div>
                </div>
                <div style="margin-top: 12px;">
                    <a href="{{ route('cuentas-cobrar.show', $factura->cuentaPorCobrar->id) }}"
                       class="btn btn-primary w-full">Ver Detalles</a>
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

{{-- Modal Cancelar --}}
@if($factura->estaTimbrada())
<div id="modalCancelar" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--color-danger);">⚠️ Cancelar Factura</div>
            <button class="modal-close"
                    onclick="document.getElementById('modalCancelar').classList.remove('show')">✕</button>
        </div>
        <form method="POST" action="{{ route('facturas.cancelar', $factura->id) }}">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 20px;">
                    ¿Estás seguro de cancelar esta factura? Esta acción es irreversible.
                </p>
                <div class="form-group">
                    <label class="form-label">Motivo de Cancelación <span class="req">*</span></label>
                    <select name="motivo_cancelacion" class="form-control" required>
                        <option value="01">01 - Comprobante emitido con errores con relación</option>
                        <option value="02">02 - Comprobante emitido con errores sin relación</option>
                        <option value="03">03 - No se llevó a cabo la operación</option>
                        <option value="04">04 - Operación nominativa relacionada en factura global</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                        onclick="document.getElementById('modalCancelar').classList.remove('show')">
                    Cerrar
                </button>
                <button type="submit" class="btn btn-danger">Confirmar Cancelación</button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection