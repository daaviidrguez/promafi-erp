{{-- Expediente de cancelación fiscal (factura o complemento) --}}
@php
    $eventos = $document->relationLoaded('cancelacionEventos')
        ? $document->cancelacionEventos->sortByDesc('created_at')
        : $document->cancelacionEventos()->with('user')->limit(20)->get();
    $esFactura = $document instanceof \App\Models\Factura;
    $esAdmin = $esFactura && (bool) ($document->cancelacion_administrativa ?? false);
@endphp

<div class="card" style="margin-top: 16px;">
    <div class="card-header">
        <div class="card-title">Cancelación fiscal (PAC / SAT)</div>
    </div>
    <div class="card-body" style="font-size: 13px; line-height: 1.5;">
        <div class="info-row">
            <div class="info-label">Estado PAC</div>
            <div class="info-value-sm">
                @if($document->solicitudFiscalPendiente())
                    Pendiente de aceptación del receptor
                @elseif($document->canceladaAnteSat())
                    Cancelado ante el SAT
                @elseif($esFactura && $document->cancelacionFiscalRechazada())
                    Rechazada · el CFDI sigue vigente
                @elseif($esFactura && $document->pendienteCancelacionAntePac())
                    Aún no se envía al SAT
                @elseif($document->estatus_cancelacion_pac)
                    {{ $document->estatus_cancelacion_pac }}
                @else
                    Sin solicitud en Facturama
                @endif
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Estado SAT</div>
            <div class="info-value-sm">{{ $document->estatus_sat ?: 'Sin consultar' }}</div>
        </div>
        @if($document->motivo_cancelacion)
        <div class="info-row">
            <div class="info-label">Motivo SAT</div>
            <div class="info-value-sm">{{ \App\Services\EstatusCancelacionCfdi::descripcionMotivoSat($document->motivo_cancelacion) }}</div>
        </div>
        @endif
        @if($document->uuid_sustitucion_cancelacion ?? null)
        <div class="info-row">
            <div class="info-label">UUID sustituto</div>
            <div class="info-value-sm text-mono" style="word-break: break-all;">{{ $document->uuid_sustitucion_cancelacion }}</div>
        </div>
        @endif
        @if($esFactura && ($document->fecha_cancelacion_pac ?? null))
        <div class="info-row">
            <div class="info-label">Envío PAC/SAT</div>
            <div class="info-value-sm">{{ $document->fecha_cancelacion_pac->format('d/m/Y H:i') }}</div>
        </div>
        @elseif(! $esFactura && ($document->fecha_cancelacion ?? null))
        <div class="info-row">
            <div class="info-label">Envío PAC/SAT</div>
            <div class="info-value-sm">{{ $document->fecha_cancelacion->format('d/m/Y H:i') }}</div>
        </div>
        @endif
        @if($document->is_cancelable)
        <div class="info-row">
            <div class="info-label">¿Cancelable?</div>
            <div class="info-value-sm">{{ $document->is_cancelable }}</div>
        </div>
        @endif
        @if($document->codigo_estatus_cancelacion)
        <div class="info-row">
            <div class="info-label">Código SAT</div>
            <div class="info-value-sm text-mono">
                {{ $document->codigo_estatus_cancelacion }}
                — {{ $esFactura
                    ? \App\Models\Factura::descripcionCodigoCancelacion($document->codigo_estatus_cancelacion)
                    : \App\Models\ComplementoPago::descripcionCodigoCancelacion($document->codigo_estatus_cancelacion) }}
            </div>
        </div>
        @endif
        @if($document->fecha_solicitud_cancelacion)
        <div class="info-row">
            <div class="info-label">Solicitud enviada</div>
            <div class="info-value-sm">{{ $document->fecha_solicitud_cancelacion->format('d/m/Y H:i') }}</div>
        </div>
        @endif
        @if($document->fecha_vencimiento_aceptacion)
        <div class="info-row">
            <div class="info-label">Límite de aceptación</div>
            <div class="info-value-sm">{{ $document->fecha_vencimiento_aceptacion->format('d/m/Y H:i') }}</div>
        </div>
        @endif
        @if($document->mensaje_cancelacion_pac)
        <div class="info-row">
            <div class="info-label">Mensaje PAC</div>
            <div class="info-value-sm">{{ $document->mensaje_cancelacion_pac }}</div>
        </div>
        @endif

        @if($esAdmin)
            @if($document->cancelacion_administrativa_motivo)
            <div class="info-row">
                <div class="info-label">Motivo administrativo (ERP)</div>
                <div class="info-value-sm">{{ $document->cancelacion_administrativa_motivo }}</div>
            </div>
            @endif
            @if($document->cancelacionAdministrativaUsuario)
            <div class="info-row">
                <div class="info-label">Registró</div>
                <div class="info-value-sm">{{ $document->cancelacionAdministrativaUsuario->name }}</div>
            </div>
            @endif
            @if($document->pendienteCancelacionAntePac())
                <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    Cancelación administrativa en el ERP: inventario y saldo ya se revirtieron.
                    El CFDI sigue vigente ante el SAT hasta que use <strong>Cancelar factura</strong>
                    (después de facturar con relación, si aplica). El stock no se volverá a mover.
                </div>
            @elseif($document->solicitudFiscalPendiente())
                <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    La solicitud sí se envió al SAT. El receptor debe aceptar o dejar vencer el plazo.
                    El ERP ya está cancelado administrativamente; no se duplican movimientos de inventario.
                </div>
            @elseif($document->cancelacionFiscalRechazada())
                <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    El receptor rechazó la cancelación. El CFDI sigue vigente ante el SAT.
                    El ERP permanece cancelado (el stock ya pudo usarse en la factura con relación).
                    Puede reintentar el envío al SAT.
                </div>
            @elseif($document->canceladaAnteSat())
                <div class="alert alert-info" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    Cancelada en el ERP (administrativa) y confirmada ante el SAT.
                    Inventario y saldo no se movieron de nuevo.
                </div>
            @endif
        @endif

        @if($eventos->isNotEmpty())
            <div class="info-label" style="margin-top: 16px; margin-bottom: 8px;">Bitácora</div>
            <div style="display: grid; gap: 8px;">
                @foreach($eventos->take(12) as $evento)
                    <div style="border: 1px solid var(--color-gray-200); border-radius: var(--radius-sm); padding: 8px 10px;">
                        <div style="display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                            <strong>{{ $evento->etiquetaTipo() }}</strong>
                            <span class="text-muted" style="font-size: 11px;">{{ $evento->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        @if($evento->user)
                            <div class="text-muted" style="font-size: 11px;">{{ $evento->user->name }}</div>
                        @endif
                        @if($evento->status_pac || $evento->estatus_sat || $evento->codigo_estatus)
                            <div class="text-mono" style="font-size: 11px; margin-top: 4px;">
                                @if($evento->status_pac) PAC: {{ $evento->status_pac }} @endif
                                @if($evento->estatus_sat) · SAT: {{ $evento->estatus_sat }} @endif
                                @if($evento->codigo_estatus) · {{ $evento->codigo_estatus }} @endif
                            </div>
                        @endif
                        @if($evento->mensaje)
                            <div style="margin-top: 4px;">{{ $evento->mensaje }}</div>
                        @endif
                        @if(is_array($evento->payload) && (!empty($evento->payload['motivo_sat']) || !empty($evento->payload['uuid_sustitucion'])))
                            <div class="text-muted" style="font-size: 11px; margin-top: 4px;">
                                @if(!empty($evento->payload['motivo_sat']))
                                    Motivo SAT: {{ \App\Services\EstatusCancelacionCfdi::descripcionMotivoSat($evento->payload['motivo_sat']) }}
                                @endif
                                @if(!empty($evento->payload['uuid_sustitucion']))
                                    <div class="text-mono" style="word-break: break-all;">UUID sustituto: {{ $evento->payload['uuid_sustitucion'] }}</div>
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
