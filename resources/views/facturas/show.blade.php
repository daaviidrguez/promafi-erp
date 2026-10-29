@extends('layouts.app')

@section('title', 'Factura ' . $factura->folio_completo)
@section('page-title', '🧾 Factura ' . $factura->folio_completo)
@section('page-title-aside')
    @if(filled($factura->orden_compra))
    <div class="page-title-oc" title="Orden de compra">
        <span class="page-title-oc-label">OC</span>
        <span class="page-title-oc-value">{{ $factura->orden_compra }}</span>
    </div>
    @endif
@endsection
@section('page-actions')
    @include('partials.doc-show-volver')
@endsection

@php
$breadcrumbs = [
    ['title' => 'Facturas', 'url' => route('facturas.index')],
    ['title' => $factura->folio_completo]
];
@endphp

@section('content')

@if(!empty($mensajeSyncSat ?? null))
<div class="alert alert-success" id="flash-alert-sync-sat">
    <span>✓</span>
    {{ $mensajeSyncSat }}
</div>
@endif

@php
    $badgeTitle = null;
    if ($factura->estado === 'timbrada') {
        if ($factura->codigo_estatus_cancelacion && str_starts_with($factura->codigo_estatus_cancelacion, 'R')) {
            [$badgeClass, $badgeLabel] = ['badge-warning', '⚠️ ' . $factura->estado_etiqueta];
            $badgeTitle = \App\Models\Factura::descripcionCodigoCancelacion($factura->codigo_estatus_cancelacion);
        } else {
            [$badgeClass, $badgeLabel] = ['badge-success', 'Timbrada'];
        }
    } elseif ($factura->estado === 'borrador') {
        [$badgeClass, $badgeLabel] = ['badge-warning', '📝 Borrador'];
    } else {
        [$badgeClass, $badgeLabel] = ['badge-danger', $factura->estado_etiqueta];
    }

    $usuarioActual = auth()->user();
    // Las CxC de facturas en borrador no cuentan en cartera (ver CuentaPorCobrar::scopeExcluirFacturaBorrador).
    $cxc = $factura->esBorrador() ? null : $factura->cuentaPorCobrar;
    $saldoPendiente = $cxc ? (float) $cxc->saldo_pendiente_real : 0.0;
    $facturaPagada = $cxc && $saldoPendiente <= 0;
    $cfdiTimbradoAcciones = $factura->estaTimbrada() || ($factura->cancelacion_administrativa && !empty($factura->uuid)) || $factura->solicitudFiscalPendiente();

    $mostrarCancelacion = $factura->estado === 'cancelada' || $factura->cancelacion_administrativa || $factura->cancelacionesAdministrativas->isNotEmpty();
    $cancelacionActiva = $factura->estado === 'cancelada' || (bool) $factura->cancelacion_administrativa;

    // En canceladas el saldo en cero es por reversión, no por pago.
    $mostrarSaldo = $cxc && ! $cancelacionActiva;
    $cxcVencida = $cxc && $saldoPendiente > 0 && $cxc->estaVencida();
    $diasVencidos = $cxcVencida ? max(0, -1 * (int) ($cxc->dias_contra_vencimiento_realtime ?? 0)) : 0;

    $accionPrincipal = null;
    if ($factura->esBorrador()) {
        if ($usuarioActual?->can('facturas.timbrar')) {
            $accionPrincipal = 'timbrar';
        }
    } elseif ($factura->puedeConsultarEstatusCancelacion()
        && (
            $factura->solicitudFiscalPendiente()
            || $factura->cancelacionFiscalVencidaSinResolver()
            || ($factura->intentoFiscalPrevioSinCancelar() && ! $factura->intentoFiscalPrevioSinCancelarReintentable())
        )) {
        $accionPrincipal = 'consultar_sat';
    } elseif ($factura->estaTimbrada() && $factura->metodo_pago === 'PPD' && $cxc && $saldoPendiente > 0) {
        $accionPrincipal = 'complemento';
    }

    $tieneDocumentosRelacionados = $factura->estaTimbrada() && $factura->tieneDocumentosRelacionados();
    $mostrarBotonCancelAdmin = ($mostrarCancelacionAdmin ?? false)
        && $usuarioActual?->can('cancelaciones_administrativas.administrar');
    $hayZonaRiesgo = ($factura->esBorrador() && $usuarioActual?->can('facturas.crear'))
        || $mostrarBotonCancelAdmin
        || ($cfdiTimbradoAcciones && (
            $factura->puedeCancelar()
            || $factura->cancelacionFiscalVencidaSinResolver()
            || $factura->intentoFiscalPrevioSinCancelar()
            || $tieneDocumentosRelacionados
        ));

    $tabInicial = $errors->has('archivo') ? 'documentos' : 'conceptos';
@endphp

{{-- Cabecera: Receptor | Información Fiscal --}}
<div class="doc-show-header-grid">

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

    <div class="card">
        <div class="card-header">
            <div class="card-title">📋 Información Fiscal</div>
            <span class="badge {{ $badgeClass }}" @if($badgeTitle) title="{{ $badgeTitle }}" @endif>{{ $badgeLabel }}</span>
        </div>
        <div class="card-body">
            <div class="info-grid-2">
                <div class="info-row">
                    <div class="info-label">Fecha de Emisión</div>
                    <div class="info-value-sm">{{ $factura->fecha_emision->format('d/m/Y') }}</div>
                </div>
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
                @if($factura->usuario)
                <div class="info-row">
                    <div class="info-label">Elaboró</div>
                    <div class="info-value-sm">{{ $factura->usuario->name }}</div>
                </div>
                @endif
            </div>
        </div>
    </div>

</div>

{{-- Banda fiscal --}}
@if($factura->uuid)
<div class="doc-show-fiscal-band">
    <div>
        <div class="doc-show-fiscal-band-label">UUID fiscal (folio fiscal)</div>
        <div class="doc-show-fiscal-band-value">
            <span id="facturaUuidFiscal">{{ $factura->uuid }}</span>
            <button type="button" class="doc-show-fiscal-band-copy" data-copy-target="facturaUuidFiscal" title="Copiar UUID">Copiar</button>
        </div>
    </div>
    <div>
        <div class="doc-show-fiscal-band-label">Fecha de timbrado</div>
        <div class="doc-show-fiscal-band-value">{{ $factura->fecha_timbrado?->format('d/m/Y H:i:s') ?? '—' }}</div>
    </div>
    <div>
        <div class="doc-show-fiscal-band-label">No. certificado SAT</div>
        <div class="doc-show-fiscal-band-value">{{ $factura->no_certificado_sat ?? '—' }}</div>
    </div>
    <div>
        <div class="doc-show-fiscal-band-label">No. certificado emisor</div>
        <div class="doc-show-fiscal-band-value">{{ $noCertificadoEmisor ?? '—' }}</div>
    </div>
</div>
@endif

<div class="doc-show-layout responsive-grid">

    {{-- Columna principal --}}
    <div>

        <div class="card" data-tabs data-tab-inicial="{{ $tabInicial }}">
            <div class="doc-tabs" role="tablist" aria-label="Secciones de la factura">
                <button type="button" class="doc-tab" role="tab" id="tab-conceptos" data-tab="conceptos"
                        aria-controls="panel-conceptos" aria-selected="{{ $tabInicial === 'conceptos' ? 'true' : 'false' }}" tabindex="{{ $tabInicial === 'conceptos' ? '0' : '-1' }}">
                    📦 Conceptos <span class="doc-tab-count">{{ $factura->detalles->count() }}</span>
                </button>
                <button type="button" class="doc-tab" role="tab" id="tab-observaciones" data-tab="observaciones"
                        aria-controls="panel-observaciones" aria-selected="false" tabindex="-1">
                    📝 Observaciones
                </button>
                @if($cxc)
                <button type="button" class="doc-tab" role="tab" id="tab-cobranza" data-tab="cobranza"
                        aria-controls="panel-cobranza" aria-selected="false" tabindex="-1">
                    💰 Cobranza
                    @if($pagosAplicados->isNotEmpty())<span class="doc-tab-count">{{ $pagosAplicados->count() }}</span>@endif
                </button>
                @endif
                <button type="button" class="doc-tab" role="tab" id="tab-documentos" data-tab="documentos"
                        aria-controls="panel-documentos" aria-selected="{{ $tabInicial === 'documentos' ? 'true' : 'false' }}" tabindex="{{ $tabInicial === 'documentos' ? '0' : '-1' }}">
                    📎 Documentos
                </button>
                <button type="button" class="doc-tab" role="tab" id="tab-historial" data-tab="historial"
                        aria-controls="panel-historial" aria-selected="false" tabindex="-1">
                    🕐 Historial
                    @if($historialEnviosFactura->isNotEmpty())<span class="doc-tab-count">{{ $historialEnviosFactura->count() }}</span>@endif
                </button>
                @if($mostrarCancelacion)
                <button type="button" class="doc-tab" role="tab" id="tab-cancelacion" data-tab="cancelacion"
                        aria-controls="panel-cancelacion" aria-selected="false" tabindex="-1">
                    @if($cancelacionActiva)<span class="doc-tab-dot" aria-hidden="true"></span>@endif
                    Cancelación
                </button>
                @endif
            </div>

            {{-- Conceptos --}}
            <div class="doc-tab-panel" role="tabpanel" id="panel-conceptos" aria-labelledby="tab-conceptos" @if($tabInicial !== 'conceptos') hidden @endif>
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
                <div class="card-body doc-show-totales">
                    <div class="totales-panel">
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

            {{-- Observaciones --}}
            <div class="doc-tab-panel" role="tabpanel" id="panel-observaciones" aria-labelledby="tab-observaciones" hidden>
                <div class="card-body">
                    @if($factura->observaciones)
                        <div class="info-row">
                            <div class="info-label">📝 Observaciones</div>
                            <div class="info-value-sm" style="line-height: 1.7; white-space: pre-wrap;">{{ $factura->observaciones }}</div>
                        </div>
                    @else
                        <p class="text-muted" style="margin: 0; font-size: 13px;">Sin observaciones.</p>
                    @endif
                </div>
            </div>

            {{-- Cobranza --}}
            @if($cxc)
            <div class="doc-tab-panel" role="tabpanel" id="panel-cobranza" aria-labelledby="tab-cobranza" hidden>
                <div class="card-body">
                    @if($cancelacionActiva)
                    <div class="alert alert-info" style="margin-bottom: 16px; font-size: 13px;">
                        Factura cancelada: el saldo de la cuenta por cobrar se revirtió con la cancelación; no corresponde a pagos recibidos.
                    </div>
                    @endif
                    <div class="info-grid-2" style="grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));">
                        <div class="info-row">
                            <div class="info-label">Monto total</div>
                            <div class="info-value text-mono">${{ number_format($cxc->monto_total, 2, '.', ',') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Pagado</div>
                            <div class="info-value text-mono">${{ number_format($cxc->monto_pagado, 2, '.', ',') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Saldo pendiente</div>
                            <div class="info-value text-mono" style="color: {{ $saldoPendiente > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
                                ${{ number_format($saldoPendiente, 2, '.', ',') }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Vencimiento</div>
                            <div class="info-value-sm">
                                {{ $cxc->fecha_vencimiento->format('d/m/Y') }}
                                @if($cxcVencida)
                                    <span class="badge badge-danger" style="margin-left: 6px;">⚠ Vencida{{ $diasVencidos > 0 ? ' '.$diasVencidos.'d' : '' }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card-title" style="font-size: 14px; margin-bottom: 10px;">Pagos aplicados</div>
                    @if($pagosAplicados->isEmpty())
                        <p class="text-muted" style="margin: 0 0 16px; font-size: 13px;">Aún no hay pagos aplicados mediante complemento de pago.</p>
                    @else
                        <div class="table-container table-container--scroll" style="margin-bottom: 16px;">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Complemento</th>
                                        <th>Fecha de pago</th>
                                        <th class="td-center">Parc.</th>
                                        <th class="td-right">Saldo anterior</th>
                                        <th class="td-right">Pagado</th>
                                        <th class="td-right">Saldo insoluto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($pagosAplicados as $pagoAplicado)
                                    @php $complementoPago = $pagoAplicado->pagoRecibido?->complementoPago; @endphp
                                    <tr>
                                        <td>
                                            @if($complementoPago)
                                                <a href="{{ route('complementos.show', $complementoPago->id) }}" class="text-mono fw-600">{{ $complementoPago->folio_completo }}</a>
                                                @unless($complementoPago->estaTimbrado())
                                                    <span class="badge badge-gray" style="margin-left: 4px;">{{ $complementoPago->estado_etiqueta }}</span>
                                                @endunless
                                            @else
                                                <span class="text-muted">—</span>
                                            @endif
                                        </td>
                                        <td>{{ $pagoAplicado->pagoRecibido?->fecha_pago?->format('d/m/Y') ?? '—' }}</td>
                                        <td class="td-center">{{ $pagoAplicado->parcialidad }}</td>
                                        <td class="td-right text-mono">${{ number_format($pagoAplicado->saldo_anterior, 2, '.', ',') }}</td>
                                        <td class="td-right text-mono fw-600">${{ number_format($pagoAplicado->monto_pagado, 2, '.', ',') }}</td>
                                        <td class="td-right text-mono">${{ number_format($pagoAplicado->saldo_insoluto, 2, '.', ',') }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif

                    <a href="{{ route('cuentas-cobrar.show', $cxc->id) }}" class="btn btn-outline btn-sm">Ver en Cuentas por Cobrar →</a>
                </div>
            </div>
            @endif

            {{-- Documentos --}}
            <div class="doc-tab-panel" role="tabpanel" id="panel-documentos" aria-labelledby="tab-documentos" @if($tabInicial !== 'documentos') hidden @endif>
                <div class="card-body">
                    {{-- Soporte de recepción (acuse interno, no forma parte del CFDI) --}}
                    <div id="soporte-recepcion">
                        <div class="card-title" style="font-size: 14px; margin-bottom: 8px;">📎 Soporte de recepción</div>
                        <p style="margin: 0 0 16px; font-size: 13px; color: var(--color-gray-600); line-height: 1.55;">
                            Factura firmada por quien recibe. Es un acuse interno; no se incluye en el CFDI ni en el PDF fiscal.
                        </p>

                        @if($factura->esBorrador())
                            <p class="text-muted" style="margin: 0; font-size: 13px;">Disponible cuando la factura esté timbrada.</p>
                        @elseif($factura->soporte)
                            <div style="display: flex; flex-wrap: wrap; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 12px 14px; background: var(--color-gray-50); border-radius: var(--radius-md); border: 1px solid var(--color-gray-200);">
                                <div style="min-width: 0; flex: 1;">
                                    <div style="font-size: 13.5px; font-weight: 600; color: var(--color-gray-800); word-break: break-word;">
                                        📄 {{ $factura->soporte->nombre_original }}
                                    </div>
                                    <div style="margin-top: 4px; font-size: 12px; color: var(--color-gray-500);">
                                        {{ $factura->soporte->updated_at?->format('d/m/Y H:i') }}
                                        · {{ $factura->soporte->tamanoLegible() }}
                                        @if($factura->soporte->usuario)
                                            · {{ $factura->soporte->usuario->name }}
                                        @endif
                                    </div>
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; flex-shrink: 0;">
                                    <a href="{{ route('facturas.soporte.ver', $factura) }}"
                                       target="_blank" class="btn btn-outline btn-sm js-ver-soporte">Ver</a>
                                    @can('facturas.soporte')
                                    <form method="POST"
                                          action="{{ route('facturas.soporte.destroy', $factura) }}"
                                          onsubmit="return confirm('¿Eliminar el soporte de recepción?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-light btn-sm" style="color: var(--color-danger, #b91c1c);">Eliminar</button>
                                    </form>
                                    @endcan
                                </div>
                            </div>
                            @can('facturas.soporte')
                            <form method="POST"
                                  action="{{ route('facturas.soporte.store', $factura) }}"
                                  enctype="multipart/form-data"
                                  style="margin-top: 14px; padding-top: 14px; border-top: 1px solid var(--color-gray-200);">
                                @csrf
                                <div class="info-label mb-8">Reemplazar archivo</div>
                                <div style="display: grid; gap: 10px;">
                                    <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" class="form-control" required>
                                    @error('archivo')
                                        <div class="text-danger" style="font-size: 12.5px;">{{ $message }}</div>
                                    @enderror
                                    <button type="submit" class="btn btn-outline" style="justify-self: start;">Reemplazar</button>
                                </div>
                            </form>
                            @endcan
                        @else
                            <p class="text-muted" style="margin: 0 0 16px; font-size: 13px;">Aún no hay soporte de recepción.</p>
                            @can('facturas.soporte')
                            <form method="POST"
                                  action="{{ route('facturas.soporte.store', $factura) }}"
                                  enctype="multipart/form-data">
                                @csrf
                                <div style="display: grid; gap: 10px;">
                                    <input type="file" name="archivo" accept=".pdf,.jpg,.jpeg,.png,.webp,application/pdf,image/jpeg,image/png,image/webp" class="form-control" required>
                                    @error('archivo')
                                        <div class="text-danger" style="font-size: 12.5px;">{{ $message }}</div>
                                    @enderror
                                    <button type="submit" class="btn btn-primary" style="justify-self: start;">Adjuntar</button>
                                </div>
                            </form>
                            @endcan
                        @endif
                    </div>
                </div>
            </div>

            {{-- Historial envíos de logística (factura y remisiones vinculadas) --}}
            <div class="doc-tab-panel" role="tabpanel" id="panel-historial" aria-labelledby="tab-historial" hidden>
                <div class="card-body text-muted" style="font-size: 13px;">
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

            {{-- Cancelación --}}
            @if($mostrarCancelacion)
            <div class="doc-tab-panel" role="tabpanel" id="panel-cancelacion" aria-labelledby="tab-cancelacion" hidden>
                <div class="card-body">
                    @include('partials.expediente-cancelacion', ['document' => $factura, 'embebido' => true])
                </div>
            </div>
            @endif
        </div>

    </div>

    {{-- Columna lateral --}}
    <div class="doc-show-aside">

        {{-- Acciones --}}
        <div class="card doc-show-acciones">
            <div class="card-header">
                <div class="card-title">⚡ Acciones</div>
            </div>
            <div class="card-body">

                <div class="doc-show-acciones-resumen">
                    <div class="doc-show-acciones-resumen-row">
                        <span class="info-label">Total</span>
                        <span class="monto">${{ number_format($factura->total, 2, '.', ',') }}</span>
                    </div>
                    @if($mostrarSaldo)
                    <div class="doc-show-acciones-resumen-row">
                        <span class="info-label">Saldo pendiente</span>
                        @if($facturaPagada)
                            <span class="badge badge-success">✓ Pagada</span>
                        @else
                            <span class="monto-sm" style="color: var(--color-warning);">${{ number_format($saldoPendiente, 2, '.', ',') }}</span>
                        @endif
                    </div>
                    @unless($facturaPagada)
                    <div class="doc-show-acciones-resumen-row">
                        <span class="info-label">Vence</span>
                        <span>
                            {{ $cxc->fecha_vencimiento->format('d/m/Y') }}
                            @if($cxcVencida)
                                <span class="badge badge-danger" style="margin-left: 4px;">Vencida{{ $diasVencidos > 0 ? ' '.$diasVencidos.'d' : '' }}</span>
                            @endif
                        </span>
                    </div>
                    @endunless
                    @endif
                </div>

                {{-- Documentos del registro (consulta rápida junto al resumen) --}}
                <div class="doc-show-acciones-fila">
                    <a id="linkVerFacturaPdf" href="{{ route('facturas.ver-pdf', $factura->id) }}"
                       target="_blank" class="btn btn-light btn-sm">👁️ Ver factura</a>
                    @if($factura->soporte)
                    <a id="linkVerSoporteRecepcion" href="{{ route('facturas.soporte.ver', $factura) }}"
                       target="_blank" class="btn btn-light btn-sm js-ver-soporte">📎 Soporte</a>
                    @endif
                    @if($cfdiTimbradoAcciones && $factura->pdf_path)
                    <a href="{{ route('facturas.descargar-pdf', $factura->id) }}" class="btn btn-light btn-sm">📑 PDF</a>
                    @endif
                    @if($cfdiTimbradoAcciones && $factura->xml_path)
                    <a href="{{ route('facturas.descargar-xml', $factura->id) }}" class="btn btn-light btn-sm">📄 XML</a>
                    @endif
                    @if($factura->estado === 'cancelada')
                        @if($factura->canceladaAnteSat() && !empty($factura->uuid))
                        <a href="{{ route('facturas.descargar-pdf-acuse-cancelacion', $factura->id) }}" class="btn btn-light btn-sm full">📑 Acuse de cancelación (PDF)</a>
                        @endif
                        @if($factura->canceladaAnteSat() && $factura->tieneAcuseCancelacionXmlValido())
                        <a href="{{ route('facturas.descargar-xml-cancelacion', $factura->id) }}" class="btn btn-light btn-sm full">📄 Acuse de cancelación (XML)</a>
                        @elseif(! $factura->pendienteCancelacionAntePac() && ! $factura->solicitudFiscalPendiente() && $factura->canceladaAnteSat())
                        <a href="{{ route('facturas.obtener-acuse-cancelacion', $factura->id) }}" class="btn btn-light btn-sm full">📄 Obtener acuse XML</a>
                        @endif
                    @endif
                </div>
                @if($factura->estado === 'cancelada' && ! ($factura->canceladaAnteSat() && $factura->tieneAcuseCancelacionXmlValido()))
                    @if($factura->pendienteCancelacionAntePac())
                    <p class="doc-show-acciones-nota">El acuse XML estará disponible después de enviar la cancelación al PAC.</p>
                    @elseif($factura->solicitudFiscalPendiente())
                    <p class="doc-show-acciones-nota">El acuse XML aparece cuando el SAT confirme la cancelación. Si hay código 201, solo confirma que la solicitud fue recibida.</p>
                    @endif
                @endif

                <div class="doc-show-acciones-divider"></div>

                {{-- Acción principal según el estado --}}
                @if($accionPrincipal === 'timbrar')
                    @if($factura->puedeTimbrar())
                    <form method="POST" action="{{ route('facturas.timbrar', $factura->id) }}"
                          class="form-timbrar-protegido" data-loading-text="Timbrando factura…">
                        @csrf
                        <button type="submit" class="btn btn-primary w-full">🔏 Timbrar Factura</button>
                    </form>
                    @else
                    <button type="button" class="btn btn-primary w-full" disabled
                            title="{{ $factura->motivoNoTimbrar() }}"
                            style="opacity: 0.6; cursor: not-allowed;">🔏 Timbrar Factura</button>
                    <p class="doc-show-acciones-nota">{{ $factura->motivoNoTimbrar() }}</p>
                    @endif
                @elseif($accionPrincipal === 'consultar_sat')
                    <button type="button"
                            class="btn btn-primary w-full js-actualizar-estatus-sat"
                            data-action="{{ route('facturas.actualizar-estatus-cancelacion', $factura->id) }}"
                            data-folio="{{ $factura->folio_completo }}"
                            data-tipo="factura">🔎 Consultar estatus SAT</button>
                @elseif($accionPrincipal === 'complemento')
                    @if(isset($complementoBorrador) && $complementoBorrador)
                    <a href="{{ route('complementos.show', $complementoBorrador->id) }}" class="btn btn-primary w-full">📄 Ver complemento en borrador</a>
                    @else
                    <a href="{{ route('complementos.create', ['cuenta_id' => $cxc->id]) }}" class="btn btn-primary w-full">💵 Crear complemento de pago</a>
                    @endif
                @endif

                {{-- Operaciones --}}
                @if($factura->esBorrador())
                    @can('facturas.crear')
                    <a href="{{ route('facturas.edit', $factura->id) }}" class="btn btn-outline w-full">✏️ Editar Factura</a>
                    @endcan
                    @canany(['facturas.crear', 'facturas.timbrar'])
                    @if(!empty($datosFiscalesBorrador['pendiente_en_catalogo']))
                    <div class="alert alert-warning" style="margin: 0; padding: 10px 12px; font-size: 12px; line-height: 1.5;">
                        <strong>Faltan datos SAT en catálogo</strong> para poder timbrar.
                        Complételos en el producto; al volver a esta factura se sincronizarán solos.
                    </div>
                    @endif
                    @if(!empty($datosFiscalesBorrador['puede_sincronizar_desde_catalogo']))
                    <form method="POST" action="{{ route('facturas.sincronizar-clave-sat', $factura->id) }}">
                        @csrf
                        <button type="submit" class="btn btn-outline w-full"
                                title="El catálogo ya tiene clave/unidad, pero esta factura aún no las refleja">
                            🔄 Actualizar claves SAT
                        </button>
                    </form>
                    <p class="doc-show-acciones-nota">
                        El catálogo ya tiene datos listos; pulse para traerlos a este borrador (o recargue la página).
                    </p>
                    @endif
                    @endcanany
                @endif

                @if($factura->puedeConsultarEstatusCancelacion() && $accionPrincipal !== 'consultar_sat')
                    <button type="button"
                            class="btn btn-outline w-full js-actualizar-estatus-sat"
                            data-action="{{ route('facturas.actualizar-estatus-cancelacion', $factura->id) }}"
                            data-folio="{{ $factura->folio_completo }}"
                            data-tipo="factura">🔎 Consultar estatus SAT</button>
                @endif

                @if($factura->cotizacion)
                @can('cotizaciones.ver')
                <a href="{{ route('cotizaciones.show', $factura->cotizacion->id) }}"
                   class="btn btn-outline w-full">📋 Ver cotización relacionada</a>
                @endcan
                @endif

                @can('cancelaciones_administrativas.revertir')
                    @if($factura->cancelacion_administrativa ?? false)
                        @if($puedeReactivarCancelacionAdmin ?? false)
                        <button type="button"
                                onclick="document.getElementById('modalReactivarCancelAdmin').classList.add('show')"
                                class="btn btn-outline w-full">↻ Reactivar cancelación administrativa</button>
                        @elseif($motivoNoReactivarCancelacionAdmin)
                        <button type="button" disabled class="btn btn-outline w-full" style="opacity: 0.6; cursor: not-allowed;"
                                title="{{ $motivoNoReactivarCancelacionAdmin }}">↻ Reactivar cancelación administrativa</button>
                        <p class="doc-show-acciones-nota">{{ $motivoNoReactivarCancelacionAdmin }}</p>
                        @endif
                    @endif
                @endcan

                @php
                    $masAccionesComplemento = $cxc
                        && $saldoPendiente > 0
                        && $accionPrincipal !== 'complemento';
                    $masAccionesCorrecciones = $factura->estaTimbrada() && ! $facturaPagada;
                    $mostrarMasAcciones = (bool) $cxc || $masAccionesComplemento || $masAccionesCorrecciones;
                @endphp
                @if($mostrarMasAcciones)
                <details class="doc-show-acciones-mas">
                    <summary class="btn btn-outline w-full">⋯ Más acciones</summary>
                    <div class="doc-show-acciones-mas-menu">
                        @if($masAccionesComplemento)
                            @if(isset($complementoBorrador) && $complementoBorrador)
                            <a href="{{ route('complementos.show', $complementoBorrador->id) }}" class="doc-show-acciones-mas-item">
                                <span class="icon" aria-hidden="true">📄</span>
                                <span>Ver complemento en borrador</span>
                            </a>
                            @else
                            <a href="{{ route('complementos.create', ['cuenta_id' => $cxc->id]) }}" class="doc-show-acciones-mas-item">
                                <span class="icon" aria-hidden="true">💵</span>
                                <span>Crear complemento de pago</span>
                            </a>
                            @endif
                        @endif

                        @if($cxc)
                        <a href="{{ route('cuentas-cobrar.show', $cxc->id) }}" class="doc-show-acciones-mas-item">
                            <span class="icon" aria-hidden="true">💰</span>
                            <span>Ver cuenta por cobrar</span>
                        </a>
                        @endif

                        @if($masAccionesCorrecciones)
                        <a href="{{ route('devoluciones.create', ['factura_id' => $factura->id]) }}" class="doc-show-acciones-mas-item">
                            <span class="icon" aria-hidden="true">↩️</span>
                            <span>Registrar devolución</span>
                        </a>
                            @if(isset($ncBorrador) && $ncBorrador)
                            <a href="{{ route('notas-credito.show', $ncBorrador->id) }}" class="doc-show-acciones-mas-item">
                                <span class="icon" aria-hidden="true">📄</span>
                                <span>Ver nota de crédito en borrador</span>
                            </a>
                            @else
                            <a href="{{ route('notas-credito.create', ['factura_id' => $factura->id]) }}" class="doc-show-acciones-mas-item">
                                <span class="icon" aria-hidden="true">🧾</span>
                                <span>Emitir nota de crédito</span>
                            </a>
                            @endif
                        @endif
                    </div>
                </details>
                @endif

                {{-- Atajo de módulo (no opera sobre esta factura) --}}
                @can('facturas.crear')
                <div class="doc-show-acciones-divider"></div>
                <a href="{{ route('facturas.create') }}"
                   class="btn btn-outline w-full">➕ Nueva Factura</a>
                @endcan

                {{-- Zona de riesgo --}}
                @if($hayZonaRiesgo)
                <div class="doc-show-acciones-divider"></div>
                <div class="doc-show-acciones-grupo">Zona de riesgo</div>

                @if($factura->esBorrador())
                @can('facturas.crear')
                <button type="button"
                        onclick="document.getElementById('modalBorrarFactura').classList.add('show')"
                        class="btn btn-danger-outline w-full">🗑️ Borrar Factura</button>
                @endcan
                @endif

                @if($mostrarBotonCancelAdmin)
                    @if($puedeCancelarAdmin ?? false)
                    <a href="{{ route('cancelaciones-administrativas.create', ['factura_id' => $factura->id]) }}"
                       class="btn btn-danger-outline w-full">Cancelar administrativamente</a>
                    @else
                    <button type="button"
                            class="btn btn-danger-outline w-full is-aria-disabled"
                            aria-disabled="true"
                            onclick="document.getElementById('modalCancelAdminNoAplica').classList.add('show')">
                        Cancelar administrativamente
                    </button>
                    @endif
                @endif

                @if($cfdiTimbradoAcciones)
                    @if($factura->puedeCancelar())
                    <button type="button"
                            onclick="document.getElementById('modalCancelar').classList.add('show')"
                            class="btn btn-danger-outline w-full">
                        {{ $factura->puedeReintentarCancelacionFiscal() ? '↻ Reenviar cancelación al SAT' : '✗ Cancelar ante el SAT' }}
                    </button>
                    @elseif($factura->cancelacionFiscalVencidaSinResolver())
                    <p class="doc-show-acciones-nota" style="margin-top: 0;">
                        El límite de respuesta venció y el CFDI sigue vigente. Use <strong>Consultar estatus SAT</strong>; después podrá reenviar si continúa vigente. Revise el motivo (si el 01 no tiene relación 04, use 03).
                    </p>
                    @elseif($factura->intentoFiscalPrevioSinCancelar())
                    <p class="doc-show-acciones-nota" style="margin-top: 0;">
                        El intento previo no canceló el CFDI y Facturama ya no tiene solicitud pendiente.
                        Use <strong>Consultar estatus SAT</strong>; después podrá reenviar (se sugerirá 03 si el 01 no tiene relación 04).
                    </p>
                    @elseif($tieneDocumentosRelacionados)
                    <button type="button" disabled class="btn btn-outline w-full" style="opacity: 0.6; cursor: not-allowed;">✗ Cancelar ante el SAT</button>
                    <details class="doc-show-acciones-nota">
                        <summary>No se puede cancelar: tiene documentos relacionados</summary>
                        <ul style="margin: 6px 0 0 14px; padding: 0;">
                            @foreach($factura->getDocumentosRelacionadosDetalle() as $item)
                            <li>{{ $item }}</li>
                            @endforeach
                        </ul>
                        <span style="font-size: 11px; display: block; margin-top: 6px;">La cancelación estará disponible en el flujo castada.</span>
                    </details>
                    @endif
                @endif
                @endif

            </div>
        </div>

    </div>
</div>

{{-- Modal Cancelar (timbrada o cancelada administrativamente pendiente de PAC) --}}
@if($factura->puedeCancelar())
@php
    $esReintentoCancelacion = $factura->puedeReintentarCancelacionFiscal();
    $motivoCancelDefault = old(
        'motivo_cancelacion',
        $esReintentoCancelacion
            ? $factura->motivoCancelacionSugeridoParaReintento()
            : ($factura->motivo_cancelacion ?: '')
    );
    $uuidSustitutoDefault = old('uuid_sustituto', $factura->uuid_sustitucion_cancelacion ?: ($facturaSustituta?->uuid ?? ''));
    $reintentoSinRelacion04 = $esReintentoCancelacion
        && (string) ($factura->motivo_cancelacion ?? '') === '01'
        && ! $factura->sustitutoTieneRelacionFiscal04();
@endphp
<div id="modalCancelar" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title" style="color: var(--color-danger);">⚠️ {{ $esReintentoCancelacion ? 'Reenviar cancelación al SAT' : 'Cancelar ante el SAT' }}</div>
            <button class="modal-close"
                    onclick="document.getElementById('modalCancelar').classList.remove('show')">✕</button>
        </div>
        <form method="POST" action="{{ route('facturas.cancelar', $factura->id) }}" id="formCancelarFactura">
            @csrf
            @method('DELETE')
            <div class="modal-body">
                @if($factura->pendienteCancelacionAntePac())
                <div class="doc-cancel-guidance doc-cancel-guidance--warning doc-cancel-guidance--compact">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">Solo envío fiscal al PAC/SAT</div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip">ERP ya cancelado</span>
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        Inventario y saldo ya se revirtieron. Al confirmar solo se enviará la cancelación ante el PAC/SAT.
                    </p>
                    <ul class="doc-cancel-guidance-list">
                        <li>No se volverán a registrar movimientos de inventario, aunque después se haya facturado con relación el mismo stock.</li>
                    </ul>
                </div>
                @elseif($esReintentoCancelacion)
                <div class="doc-cancel-guidance doc-cancel-guidance--warning doc-cancel-guidance--compact">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">
                            @if($factura->cancelacionFiscalVencidaSinResolver())
                                Reenvío tras plazo vencido
                            @elseif($factura->intentoFiscalPrevioSinCancelar())
                                Reenvío: sin solicitud pendiente
                            @else
                                Reenvío tras rechazo
                            @endif
                        </div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip doc-cancel-chip--warn">SAT: Vigente</span>
                            @if($factura->is_cancelable)
                                <span class="doc-cancel-chip">{{ $factura->is_cancelable }}</span>
                            @endif
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        @if($factura->cancelacionFiscalVencidaSinResolver())
                            El límite de la solicitud anterior venció y una consulta reciente confirma que el CFDI sigue vigente.
                        @elseif($factura->intentoFiscalPrevioSinCancelar())
                            Facturama ya no reporta una solicitud pendiente y el CFDI sigue vigente. El intento anterior no canceló el comprobante.
                        @else
                            El SAT o el receptor rechazaron la cancelación anterior. El CFDI sigue vigente.
                        @endif
                    </p>
                    <ul class="doc-cancel-guidance-list">
                        @if($reintentoSinRelacion04)
                            <li>El intento previo usó motivo <span class="doc-cancel-guidance-code">01</span>, pero el UUID sustituto <strong>no tiene relación fiscal 04</strong>.</li>
                            <li>Se sugiere motivo <span class="doc-cancel-guidance-code">03</span>. Si insiste en 01, elija un sustituto con relación 04.</li>
                        @else
                            <li>Puede reenviar con un motivo válido; revise la selección abajo antes de confirmar.</li>
                        @endif
                    </ul>
                    <p class="doc-cancel-guidance-foot">El ERP permanece cancelado administrativamente; reenviar no restaura ni vuelve a mover inventario.</p>
                </div>
                @endif
                <p class="text-muted" style="margin-bottom: 20px;">
                    ¿Estás seguro de {{ $esReintentoCancelacion ? 'reenviar la cancelación de' : 'cancelar' }} esta factura? Esta acción es irreversible ante el PAC/SAT.
                </p>
                <div class="form-group">
                    <label class="form-label">Motivo de Cancelación <span class="req">*</span></label>
                    <select name="motivo_cancelacion" id="cancelMotivo" class="form-control" required>
                        <option value="01" @selected($motivoCancelDefault === '01')>01 - Comprobante emitido con errores con relación</option>
                        <option value="02" @selected($motivoCancelDefault === '02')>02 - Comprobante emitido con errores sin relación</option>
                        <option value="03" @selected($motivoCancelDefault === '03')>03 - No se llevó a cabo la operación</option>
                        <option value="04" @selected($motivoCancelDefault === '04')>04 - Operación nominativa relacionada en factura global</option>
                    </select>
                    @if($reintentoSinRelacion04 && $motivoCancelDefault === '03')
                    <span class="form-hint">Sugerido automáticamente: el 01 anterior no tenía relación 04 válida.</span>
                    @endif
                </div>
                <div id="bloqueUuidSustituto" class="form-group" style="display: none;">
                    <label class="form-label">UUID Sustituto <span class="req">*</span></label>
                    <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                        <input type="text" id="inputUuidSustitutoDisplay" class="form-control" readonly placeholder="Seleccione la factura que sustituye a esta" value="{{ $motivoCancelDefault === '01' ? $uuidSustitutoDefault : '' }}" style="flex: 1; min-width: 200px; background: var(--color-gray-50);">
                        <input type="hidden" name="uuid_sustituto" id="inputUuidSustituto" value="{{ $motivoCancelDefault === '01' ? $uuidSustitutoDefault : '' }}">
                        <button type="button" class="btn btn-outline-primary" onclick="abrirModalSeleccionarSustituto()">
                            Seleccionar factura que sustituye
                        </button>
                    </div>
                    <span class="form-hint">Obligatorio con motivo 01: el CFDI sustituto debe tener tipo de relación 04 apuntando a esta factura.</span>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                        onclick="document.getElementById('modalCancelar').classList.remove('show')">
                    Cerrar
                </button>
                <button type="submit" class="btn btn-danger">
                    {{ $factura->puedeReintentarCancelacionFiscal() ? 'Confirmar reenvío' : 'Confirmar Cancelación' }}
                </button>
            </div>
        </form>
    </div>
</div>

@can('cancelaciones_administrativas.revertir')
@if(($puedeReactivarCancelacionAdmin ?? false) && ($factura->cancelacion_administrativa ?? false))
<div id="modalReactivarCancelAdmin" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Reactivar cancelación administrativa</div>
            <button class="modal-close"
                    onclick="document.getElementById('modalReactivarCancelAdmin').classList.remove('show')">✕</button>
        </div>
        <form method="POST" action="{{ route('facturas.reactivar-cancelacion-administrativa', $factura->id) }}">
            @csrf
            <div class="modal-body">
                <div class="alert alert-info" style="margin-bottom: 16px; font-size: 13px; line-height: 1.5;">
                    Reactiva solo el estado en el ERP (vuelve a <strong>timbrada</strong>).
                    El CFDI <strong>nunca se canceló ante el SAT</strong>; se reaplica inventario y cartera si se revirtieron en la cancelación administrativa.
                </div>
                <div class="form-group">
                    <label class="form-label">Motivo de reactivación <span class="req">*</span></label>
                    <textarea name="motivo" class="form-control" rows="4" required minlength="10" maxlength="2000"
                              placeholder="Explique por qué se deshace la cancelación administrativa (mín. 10 caracteres).">{{ old('motivo') }}</textarea>
                    <p class="form-hint" style="margin-top: 6px;">Registro obligatorio para auditoría.</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light"
                        onclick="document.getElementById('modalReactivarCancelAdmin').classList.remove('show')">
                    Cerrar
                </button>
                <button type="submit" class="btn btn-primary">Confirmar reactivación</button>
            </div>
        </form>
    </div>
</div>
@endif
@endcan

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

@if($mostrarBotonCancelAdmin && ! ($puedeCancelarAdmin ?? false))
<div id="modalCancelAdminNoAplica" class="modal">
    <div class="modal-box">
        <div class="modal-header">
            <div class="modal-title">Cancelar administrativamente</div>
            <button class="modal-close"
                    onclick="document.getElementById('modalCancelAdminNoAplica').classList.remove('show')">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom: 0; line-height: 1.5;">
                {{ $motivoNoCancelarAdmin ?: 'Esta factura no es elegible para cancelación administrativa.' }}
            </p>
            <p class="doc-show-acciones-nota" style="margin-top: 12px;">
                Esta acción solo cancela en el ERP; no cancela el CFDI ante el SAT.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light"
                    onclick="document.getElementById('modalCancelAdminNoAplica').classList.remove('show')">
                Entendido
            </button>
        </div>
    </div>
</div>
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

@include('partials.modal-actualizar-estatus-sat')

@endsection

@push('scripts')
@if(session('success'))
<script>
(function () {
    try {
        var userId = @json((int) auth()->id());
        var facturaId = @json((int) $factura->id);
        var pointerKey = 'promafi:factura-pointer:' + userId;
        var editKey = 'promafi:factura-draft:edit:' + userId + ':' + facturaId;
        var createKey = 'promafi:factura-draft:create:' + userId;
        localStorage.removeItem(editKey);
        localStorage.removeItem(createKey);
        var pointerRaw = localStorage.getItem(pointerKey);
        if (pointerRaw) {
            var pointer = JSON.parse(pointerRaw);
            if (pointer && (pointer.key === editKey || pointer.key === createKey)) {
                localStorage.removeItem(pointerKey);
            }
        }
    } catch (e) {}
})();
</script>
@endif
<script>
document.addEventListener('DOMContentLoaded', function () {
    const isMobile = window.matchMedia('(max-width: 1024px)').matches;
    const isStandalonePwa = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    // En móvil/PWA abrir en el mismo flujo para que "atrás" regrese a la vista previa.
    if (isMobile || isStandalonePwa) {
        document.querySelectorAll('#linkVerFacturaPdf, .js-ver-soporte').forEach(function (link) {
            link.removeAttribute('target');
        });
    }
});

(function () {
    var contenedor = document.querySelector('[data-tabs]');
    if (!contenedor) return;
    var tabs = Array.prototype.slice.call(contenedor.querySelectorAll('[role="tab"]'));
    var alias = { 'soporte-recepcion': 'documentos' };

    function activar(nombre, opciones) {
        opciones = opciones || {};
        var tab = tabs.filter(function (t) { return t.dataset.tab === nombre; })[0];
        if (!tab) return false;
        tabs.forEach(function (t) {
            var activo = t === tab;
            t.setAttribute('aria-selected', activo ? 'true' : 'false');
            t.tabIndex = activo ? 0 : -1;
            var panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) panel.hidden = !activo;
        });
        if (opciones.actualizarHash) history.replaceState(null, '', '#' + nombre);
        if (opciones.foco) tab.focus();
        return true;
    }

    function tabDesdeHash() {
        var hash = decodeURIComponent((location.hash || '').replace('#', ''));
        return alias[hash] || hash;
    }

    tabs.forEach(function (tab, i) {
        tab.addEventListener('click', function () {
            activar(tab.dataset.tab, { actualizarHash: true });
        });
        tab.addEventListener('keydown', function (e) {
            var destino = null;
            if (e.key === 'ArrowRight') destino = tabs[(i + 1) % tabs.length];
            else if (e.key === 'ArrowLeft') destino = tabs[(i - 1 + tabs.length) % tabs.length];
            else if (e.key === 'Home') destino = tabs[0];
            else if (e.key === 'End') destino = tabs[tabs.length - 1];
            if (destino) {
                e.preventDefault();
                activar(destino.dataset.tab, { actualizarHash: true, foco: true });
            }
        });
    });

    // Los errores de validación (soporte) mandan sobre el hash para que el mensaje quede visible.
    var inicial = contenedor.dataset.tabInicial;
    if (inicial === 'conceptos') {
        activar(tabDesdeHash()) || activar('conceptos');
    } else {
        activar(inicial);
    }
    window.addEventListener('hashchange', function () { activar(tabDesdeHash()); });
})();

document.querySelectorAll('[data-copy-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var origen = document.getElementById(btn.dataset.copyTarget);
        if (!origen || !navigator.clipboard) return;
        navigator.clipboard.writeText(origen.textContent.trim()).then(function () {
            var texto = btn.textContent;
            btn.textContent = 'Copiado ✓';
            setTimeout(function () { btn.textContent = texto; }, 1500);
        });
    });
});
</script>
@endpush