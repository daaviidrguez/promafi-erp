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

<div class="factura-show-layout responsive-grid">

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
            <div class="table-container table-container--scroll" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
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

            {{-- Totales (mismo desglose que PDF CFDI: traslados por tasa + retenciones) --}}
            @php
                $cfdiTot = $factura->desgloseTotalesCfdi();
                $impuestosPorTasaShow = $cfdiTot['impuestosPorTasa'];
                $totalIvaShow = $cfdiTot['totalIva'];
                $totalRetencionesShow = $cfdiTot['totalRetenciones'];
            @endphp
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
                    @foreach($impuestosPorTasaShow as $datos)
                        @if($datos['importe'] > 0)
                        <div class="totales-row">
                            <span>{{ $datos['nombre'] }}</span>
                            <span class="monto">${{ number_format($datos['importe'], 2, '.', ',') }}</span>
                        </div>
                        @endif
                    @endforeach
                    @if(empty($impuestosPorTasaShow) && $totalIvaShow > 0)
                    <div class="totales-row">
                        <span>IVA (traslados)</span>
                        <span class="monto">${{ number_format($totalIvaShow, 2, '.', ',') }}</span>
                    </div>
                    @endif
                    @if($totalRetencionesShow != 0)
                    <div class="totales-row descuento">
                        <span>ISR retenido</span>
                        <span class="monto">−${{ number_format($totalRetencionesShow, 2, '.', ',') }}</span>
                    </div>
                    @endif
                    <div class="totales-row grand">
                        <span>TOTAL</span>
                        <span class="monto">${{ number_format($factura->total, 2, '.', ',') }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Orden de compra --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🧾 Orden de compra</div>
            </div>
            <div class="card-body">
                <div class="info-value-sm text-mono">
                    {{ $factura->orden_compra ?? '—' }}
                </div>
            </div>
        </div>

        {{-- Historial envíos de logística (factura y remisiones vinculadas) --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🕐 Historial</div>
            </div>
            <div class="card-body text-muted" style="max-height:420px;overflow:auto;font-size:13px;">
                @forelse($historialEnviosFactura as $h)
                    <div style="border-bottom:1px solid var(--color-gray-100);padding:8px 0;font-size:13px;">
                        <div class="fw-600">
                            @if($h->envio)
                                @can('logistica.ver')
                                    <a href="{{ route('logistica.show', $h->envio) }}" class="text-mono">{{ $h->envio->folio }}</a>
                                @else
                                    <span class="text-mono">{{ $h->envio->folio }}</span>
                                @endcan
                            @else
                                <span class="text-mono">—</span>
                            @endif
                            <span class="text-muted fw-400"> · </span>
                            {{ $h->estado_anterior ?? '—' }} → {{ $h->estado_nuevo }}
                        </div>
                        <div class="text-muted">{{ $h->created_at?->format('d/m/Y H:i') }}
                            @if($h->user) · {{ $h->user->name }}@endif
                        </div>
                        @if($h->nota)
                            <div style="margin-top:4px;">{{ $h->nota }}</div>
                        @endif
                    </div>
                @empty
                    <p class="text-muted" style="margin:0;">Sin movimientos de envíos relacionados a esta factura.</p>
                @endforelse
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
                            @if($factura->codigo_estatus_cancelacion && str_starts_with($factura->codigo_estatus_cancelacion, 'R'))
                                <span class="badge badge-warning" title="{{ \App\Models\Factura::descripcionCodigoCancelacion($factura->codigo_estatus_cancelacion) }}">⚠️ {{ $factura->estado_etiqueta }}</span>
                            @else
                                <span class="badge badge-success">Timbrada</span>
                            @endif
                        @elseif($factura->estado === 'borrador')
                            <span class="badge badge-warning">📝 Borrador</span>
                        @else
                            <span class="badge badge-danger">✗ {{ $factura->estado_etiqueta }}</span>
                            @if($factura->estado === 'cancelada' && ($factura->cancelacion_administrativa ?? false))
                                @if($factura->fecha_cancelacion)
                                    <div class="text-muted" style="font-size: 11px; margin-top: 4px;">
                                        <strong>Administrativa (ERP):</strong> {{ $factura->fecha_cancelacion->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                                @if($factura->fecha_cancelacion_pac)
                                    <div class="text-muted" style="font-size: 11px; margin-top: 2px;">
                                        <strong>Ante PAC/SAT:</strong> {{ $factura->fecha_cancelacion_pac->format('d/m/Y H:i') }}
                                    </div>
                                @endif
                            @elseif($factura->fecha_cancelacion)
                                <div class="text-muted" style="font-size: 11px; margin-top: 4px;">{{ $factura->fecha_cancelacion->format('d/m/Y H:i') }}</div>
                            @endif
                            @if($factura->cancelacion_administrativa ?? false)
                                <div class="alert {{ $factura->pendienteCancelacionAntePac() ? 'alert-warning' : 'alert-info' }}" style="margin-top: 10px; margin-bottom: 0; text-align: left; font-size: 13px;">
                                    @if($factura->pendienteCancelacionAntePac())
                                        <strong>Cancelación administrativa (solo ERP).</strong> No cancela el CFDI ante el SAT.
                                        <div style="margin-top: 8px;">El CFDI sigue vigente ante el SAT hasta que use <strong>«Cancelar factura»</strong> en acciones (cancelación ante el PAC). El inventario y el saldo ya se revirtieron en el ERP.</div>
                                    @else
                                        <div><strong>Cancelación administrativa (solo ERP).</strong></div>
                                        <div style="margin-top: 8px;"><strong>Cancelación ante el PAC/SAT.</strong></div>
                                    @endif
                                    @if($factura->cancelacion_administrativa_motivo)
                                        <div style="margin-top: 4px;">Motivo: {{ $factura->cancelacion_administrativa_motivo }}</div>
                                    @endif
                                    @if($factura->cancelacionAdministrativaUsuario)
                                        <div class="text-muted" style="font-size: 12px; margin-top: 4px;">Usuario: {{ $factura->cancelacionAdministrativaUsuario->name }}</div>
                                    @endif
                                </div>
                            @endif
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
                    <div class="info-value-sm">{{ optional(\App\Models\FormaPago::where('clave', $factura->forma_pago)->first())->etiqueta ?? $factura->forma_pago }}</div>
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

                <a id="linkVerFacturaPdf" href="{{ route('facturas.ver-pdf', $factura->id) }}"
                   target="_blank" class="btn btn-outline w-full">👁️ Ver Factura</a>

                @if($factura->esBorrador())
                @can('facturas.crear')
                <a href="{{ route('facturas.edit', $factura->id) }}" class="btn btn-primary w-full">✏️ Editar Factura</a>
                @endcan
                <form method="POST" action="{{ route('facturas.timbrar', $factura->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full">Timbrar Factura</button>
                </form>
                @can('facturas.crear')
                <button type="button"
                        onclick="document.getElementById('modalBorrarFactura').classList.add('show')"
                        class="btn btn-danger w-full">🗑️ Borrar Factura</button>
                @endcan
                @endif

                @php $cfdiTimbradoAcciones = $factura->estaTimbrada() || $factura->pendienteCancelacionAntePac(); @endphp
                @if($cfdiTimbradoAcciones)
                    @if($factura->xml_path)
                    <a href="{{ route('facturas.descargar-xml', $factura->id) }}"
                       class="btn btn-success w-full">📄 Descargar XML</a>
                    @endif

                    @if($factura->pdf_path)
                    <a href="{{ route('facturas.descargar-pdf', $factura->id) }}"
                       class="btn btn-outline w-full">📑 Descargar PDF</a>
                    @endif

                    @if($factura->puedeCancelar())
                    <button type="button"
                            onclick="document.getElementById('modalCancelar').classList.add('show')"
                            class="btn btn-danger w-full">✗ Cancelar Factura</button>
                    @elseif($factura->estaTimbrada() && $factura->tieneDocumentosRelacionados())
                    <div class="cancelar-factura-deshabilitado" style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" disabled class="btn btn-outline w-full" style="opacity: 0.6; cursor: not-allowed;">✗ Cancelar Factura</button>
                        <div class="alert alert-warning" style="margin: 0; padding: 10px 12px; font-size: 12px; line-height: 1.5;">
                            <strong>No se puede cancelar</strong> porque esta factura tiene documentos relacionados:
                            <ul style="margin: 6px 0 0 14px; padding: 0;">
                                @foreach($factura->getDocumentosRelacionadosDetalle() as $item)
                                <li>{{ $item }}</li>
                                @endforeach
                            </ul>
                            <span class="text-muted" style="font-size: 11px; display: block; margin-top: 8px;">La cancelación estará disponible en el flujo castada.</span>
                        </div>
                    </div>
                    @endif
                @endif

                {{-- XML de cancelación: visible cuando la factura está cancelada (no depende de estaTimbrada) --}}
                @if($factura->estado === 'cancelada')
                    @if(!empty($factura->acuse_cancelacion))
                    <a href="{{ route('facturas.descargar-xml-cancelacion', $factura->id) }}"
                       class="btn btn-outline w-full">📄 XML cancelado</a>
                    @elseif($factura->pendienteCancelacionAntePac())
                    <div class="alert alert-info" style="margin: 0; padding: 10px 12px; font-size: 12px; line-height: 1.5;">
                        El XML de cancelación estará disponible después de cancelar el CFDI ante el PAC (acción «Cancelar factura»).
                    </div>
                    @else
                    <a href="{{ route('facturas.obtener-acuse-cancelacion', $factura->id) }}"
                       class="btn btn-outline w-full">📄 Obtener XML cancelado</a>
                    @endif
                @endif

                @if($factura->estaTimbrada())
                    @php $facturaPagada = $factura->cuentaPorCobrar && $factura->cuentaPorCobrar->saldo_pendiente_real <= 0; @endphp
                    @if($facturaPagada)
                    <div style="display: flex; flex-direction: column; gap: 8px;">
                        <button type="button" disabled class="btn btn-outline w-full" style="opacity: 0.6; cursor: not-allowed;">↩️ Registrar devolución</button>
                        <button type="button" disabled class="btn btn-outline w-full" style="opacity: 0.6; cursor: not-allowed;">Emitir nota de crédito</button>
                        <div class="alert alert-info" style="margin: 0; padding: 10px 12px; font-size: 12px; line-height: 1.5;">
                            No se puede registrar devolución ni emitir nota de crédito porque la factura ya fue pagada (saldo en cero).
                        </div>
                    </div>
                    @else
                    <a href="{{ route('devoluciones.create', ['factura_id' => $factura->id]) }}" class="btn btn-outline w-full">↩️ Registrar devolución</a>
                    @if(isset($ncBorrador) && $ncBorrador)
                    <a href="{{ route('notas-credito.show', $ncBorrador->id) }}" class="btn btn-outline w-full">📄 Ver nota de crédito en borrador</a>
                    @else
                    <a href="{{ route('notas-credito.create', ['factura_id' => $factura->id]) }}" class="btn btn-outline w-full">Emitir nota de crédito</a>
                    @endif
                    @endif
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
                        ${{ number_format($factura->cuentaPorCobrar->saldo_pendiente_real, 2, '.', ',') }}
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
                <div style="margin-top: 12px; display: flex; flex-direction: column; gap: 8px;">
                    <a href="{{ route('cuentas-cobrar.show', $factura->cuentaPorCobrar->id) }}"
                       class="btn btn-primary w-full">Ver Detalles</a>
                    @if($factura->cuentaPorCobrar->saldo_pendiente_real > 0)
                    @if(isset($complementoBorrador) && $complementoBorrador)
                    <a href="{{ route('complementos.show', $complementoBorrador->id) }}" class="btn btn-outline w-full">📄 Ver complemento en borrador</a>
                    @else
                    <a href="{{ route('complementos.create', ['cuenta_id' => $factura->cuentaPorCobrar->id]) }}"
                       class="btn btn-outline w-full">💵 Crear Complemento de Pago</a>
                    @endif
                    @endif
                </div>
            </div>
        </div>
        @endif

    </div>
</div>

{{-- Modal Cancelar (timbrada o cancelada administrativamente pendiente de PAC) --}}
@if($factura->puedeCancelar())
<div id="modalCancelar" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--color-danger);">⚠️ Cancelar Factura</div>
            <button class="modal-close"
                    onclick="document.getElementById('modalCancelar').classList.remove('show')">✕</button>
        </div>
        <form method="POST" action="{{ route('facturas.cancelar', $factura->id) }}" id="formCancelarFactura">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                @if($factura->pendienteCancelacionAntePac())
                <div class="alert alert-warning" style="margin-bottom: 16px; font-size: 13px; line-height: 1.5;">
                    Esta factura ya fue <strong>cancelada administrativamente en el ERP</strong> (inventario y saldo revertidos). Al confirmar, solo se enviará la <strong>cancelación ante el PAC/SAT</strong>. No se volverán a registrar movimientos de inventario.
                </div>
                @endif
                <p class="text-muted" style="margin-bottom: 20px;">
                    ¿Estás seguro de cancelar esta factura? Esta acción es irreversible.
                </p>
                <div class="form-group">
                    <label class="form-label">Motivo de Cancelación <span class="req">*</span></label>
                    <select name="motivo_cancelacion" id="cancelMotivo" class="form-control" required>
                        <option value="01">01 - Comprobante emitido con errores con relación</option>
                        <option value="02">02 - Comprobante emitido con errores sin relación</option>
                        <option value="03">03 - No se llevó a cabo la operación</option>
                        <option value="04">04 - Operación nominativa relacionada en factura global</option>
                    </select>
                </div>
                <div id="bloqueUuidSustituto" class="form-group" style="display: none;">
                    <label class="form-label">UUID Sustituto <span class="req">*</span></label>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="inputUuidSustitutoDisplay" class="form-control" readonly placeholder="Seleccione la factura que sustituye a esta" style="flex: 1; min-width: 200px; background: var(--color-gray-50);">
                        <input type="hidden" name="uuid_sustituto" id="inputUuidSustituto" value="">
                        <button type="button" class="btn btn-outline-primary" onclick="abrirModalSeleccionarSustituto()">
                            Seleccionar factura que sustituye
                        </button>
                    </div>
                    <span class="form-hint">Obligatorio cuando el motivo es 01 (SAT): UUID del CFDI que reemplaza a esta factura.</span>
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

{{-- Modal seleccionar factura sustituta (para motivo 01) --}}
<div id="modalSeleccionarSustituto" class="modal">
    <div class="modal-box" style="max-width: 640px;">
        <div class="modal-header">
            <div class="modal-title">Seleccionar factura que sustituye</div>
            <button class="modal-close" onclick="cerrarModalSeleccionarSustituto()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom: 12px;">Elija la factura timbrada (o cancelada solo en ERP) que reemplaza a la que se va a cancelar (UUID sustituto).</p>
            <div class="table-container" style="max-height: 320px; overflow-y: auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Serie / Folio</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody id="listaFacturasSustituto"></tbody>
                </table>
            </div>
            <div id="cargandoSustituto" style="text-align: center; padding: 20px; color: var(--color-gray-500);">Cargando facturas...</div>
            <div id="sinFacturasSustituto" style="display: none; text-align: center; padding: 20px; color: var(--color-gray-500);">No hay facturas disponibles para seleccionar (timbradas o canceladas administrativamente pendientes de PAC).</div>
        </div>
    </div>
</div>

<script>
(function() {
    var facturaIdExcluir = {{ $factura->id }};
    var listarUrl = '{{ route("facturas.listar-para-relacion") }}?excluir_id=' + facturaIdExcluir;

    window.toggleUuidSustituto = function() {
        var motivo = document.getElementById('cancelMotivo').value;
        var bloque = document.getElementById('bloqueUuidSustituto');
        var input = document.getElementById('inputUuidSustituto');
        if (motivo === '01') {
            bloque.style.display = 'block';
            input.setAttribute('required', 'required');
        } else {
            bloque.style.display = 'none';
            input.removeAttribute('required');
            input.value = '';
            document.getElementById('inputUuidSustitutoDisplay').value = '';
        }
    };
    document.getElementById('cancelMotivo').addEventListener('change', window.toggleUuidSustituto);
    window.toggleUuidSustituto();

    window.abrirModalSeleccionarSustituto = function() {
        document.getElementById('modalSeleccionarSustituto').classList.add('show');
        document.getElementById('cargandoSustituto').style.display = 'block';
        document.getElementById('sinFacturasSustituto').style.display = 'none';
        document.getElementById('listaFacturasSustituto').innerHTML = '';
        fetch(listarUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                document.getElementById('cargandoSustituto').style.display = 'none';
                var list = data.facturas || [];
                if (list.length === 0) {
                    document.getElementById('sinFacturasSustituto').style.display = 'block';
                    return;
                }
                var tbody = document.getElementById('listaFacturasSustituto');
                list.forEach(function(f) {
                    var tr = document.createElement('tr');
                    var uuid = (f.uuid || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                    tr.innerHTML = '<td>' + (f.serie || '') + ' ' + (f.folio || '') + '</td><td>' + (f.cliente_nombre || '') + '</td><td>' + (f.fecha_emision || '') + '</td><td>' + (f.total || 0) + '</td><td><button type="button" class="btn btn-primary btn-sm" data-uuid="' + uuid + '">Seleccionar</button></td>';
                    tr.querySelector('button').addEventListener('click', function() {
                        document.getElementById('inputUuidSustituto').value = this.getAttribute('data-uuid');
                        document.getElementById('inputUuidSustitutoDisplay').value = this.getAttribute('data-uuid');
                        window.cerrarModalSeleccionarSustituto();
                    });
                    tbody.appendChild(tr);
                });
            })
            .catch(function() {
                document.getElementById('cargandoSustituto').style.display = 'none';
                document.getElementById('sinFacturasSustituto').style.display = 'block';
                document.getElementById('sinFacturasSustituto').textContent = 'Error al cargar facturas.';
            });
    };
    window.cerrarModalSeleccionarSustituto = function() {
        document.getElementById('modalSeleccionarSustituto').classList.remove('show');
    };
})();
</script>
@endif

{{-- Modal Borrar Factura (solo borrador) --}}
@if($factura->esBorrador())
<div id="modalBorrarFactura" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--color-danger);">🗑️ Borrar Factura</div>
            <button class="modal-close"
                    onclick="document.getElementById('modalBorrarFactura').classList.remove('show')">✕</button>
        </div>
        <form method="POST" action="{{ route('facturas.destroy', $factura->id) }}">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                <p class="text-muted" style="margin-bottom: 0;">
                    ¿Estás seguro de borrar esta factura en borrador? Esta acción no se puede deshacer.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                        onclick="document.getElementById('modalBorrarFactura').classList.remove('show')">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-danger">Borrar Factura</button>
            </div>
        </form>
    </div>
</div>
@endif

@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const linkVerFacturaPdf = document.getElementById('linkVerFacturaPdf');
    if (!linkVerFacturaPdf) return;

    const isMobile = window.matchMedia('(max-width: 1024px)').matches;
    const isStandalonePwa = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // En móvil/PWA abrir en el mismo flujo para que "atrás" regrese a la vista previa.
    if (isMobile || isStandalonePwa) {
        linkVerFacturaPdf.removeAttribute('target');
    }
});
</script>
@endpush