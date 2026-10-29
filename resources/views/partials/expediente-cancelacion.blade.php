{{-- Expediente de cancelación fiscal (factura o complemento) --}}
@php
    $eventos = $document->relationLoaded('cancelacionEventos')
        ? $document->cancelacionEventos->sortByDesc('created_at')
        : $document->cancelacionEventos()->with('user')->limit(20)->get();
    $esFactura = $document instanceof \App\Models\Factura;
    $esAdmin = $esFactura && (bool) ($document->cancelacion_administrativa ?? false);
    $soloHistorialAdmin = $esFactura
        && ! $esAdmin
        && $document->estado === 'timbrada'
        && ($document->relationLoaded('cancelacionesAdministrativas')
            ? $document->cancelacionesAdministrativas->isNotEmpty()
            : $document->cancelacionesAdministrativas()->exists());
    $limiteAceptacion = $esFactura
        ? $document->fechaLimiteCancelacionFiscal()
        : ($document->fecha_vencimiento_aceptacion ?? null);
    $facturaSustituta = $facturaSustituta ?? ($esFactura ? $document->resolverFacturaSustituta() : null);
    $sustitutoConRelacion04 = $esFactura
        && filled($document->uuid_sustitucion_cancelacion)
        && $document->sustitutoTieneRelacionFiscal04();
    $sustitutoSinRelacion04 = $esFactura
        && (string) ($document->motivo_cancelacion ?? '') === '01'
        && filled($document->uuid_sustitucion_cancelacion)
        && ! $sustitutoConRelacion04;
    $bitacoraAdmin = $esFactura
        ? ($document->relationLoaded('cancelacionesAdministrativas')
            ? $document->cancelacionesAdministrativas->sortByDesc('id')
            : $document->cancelacionesAdministrativas()->with('user')->orderByDesc('id')->limit(12)->get())
        : collect();
    $embebido = (bool) ($embebido ?? false);
    $tituloExpediente = $soloHistorialAdmin ? 'Historial de cancelación administrativa (ERP)' : 'Cancelación fiscal (PAC / SAT)';

    // Snapshot de la solicitud (columnas de la factura = eco del envío)
    $codigoAlEnviar = $document->codigo_estatus_cancelacion;
    $mensajeAlEnviar = $document->mensaje_cancelacion_pac;
    $fechaPrimerEnvio = $esFactura
        ? ($document->fecha_cancelacion_pac ?? null)
        : ($document->fecha_cancelacion ?? null);
    $fechaUltimaSolicitud = $document->fecha_solicitud_cancelacion ?? null;

    $mostrarPrimerEnvio = $fechaPrimerEnvio
        && (
            ! $fechaUltimaSolicitud
            || abs($fechaPrimerEnvio->diffInMinutes($fechaUltimaSolicitud)) >= 2
        );

    $descripcionCodigoAlEnviar = $codigoAlEnviar
        ? ($esFactura
            ? \App\Models\Factura::descripcionCodigoCancelacion($codigoAlEnviar)
            : \App\Models\ComplementoPago::descripcionCodigoCancelacion($codigoAlEnviar))
        : null;

    $mostrarProceso = $fechaUltimaSolicitud
        || $limiteAceptacion
        || $mostrarPrimerEnvio
        || filled($codigoAlEnviar)
        || filled($mensajeAlEnviar);

    // Última respuesta = primer evento de bitácora (más reciente)
    $ultimoEvento = $eventos->first();
    $mensajeUltimoEvento = $ultimoEvento
        ? \App\Services\EstatusCancelacionCfdi::mensajeUsuario([
            'status_pac' => $ultimoEvento->status_pac,
            'estatus_sat' => $ultimoEvento->estatus_sat,
            'codigo_estatus' => $ultimoEvento->codigo_estatus,
            'mensaje_pac' => $ultimoEvento->mensaje,
        ], $esAdmin)
        : null;
    $estatusSatUltimo = $ultimoEvento
        ? \App\Services\EstatusCancelacionCfdi::estatusSatParaUsuario(
            $ultimoEvento->status_pac,
            $ultimoEvento->estatus_sat,
            $ultimoEvento->codigo_estatus
        )
        : null;
    $motivoSatUltimo = is_array($ultimoEvento?->payload)
        ? ($ultimoEvento->payload['motivo_sat'] ?? null)
        : null;
    $uuidSustUltimo = is_array($ultimoEvento?->payload)
        ? ($ultimoEvento->payload['uuid_sustitucion'] ?? null)
        : null;
    $mostrarBandaUltimaRespuesta = $ultimoEvento !== null;
@endphp

@unless($embebido)
<div class="card" style="margin-top: 16px;">
    <div class="card-header">
        <div class="card-title">{{ $tituloExpediente }}</div>
    </div>
@endunless
    <div class="{{ $embebido ? '' : 'card-body' }}" style="font-size: 13px; line-height: 1.5;">
        @if($embebido)
            <div class="card-title" style="margin-bottom: 14px;">{{ $tituloExpediente }}</div>
        @endif
        @unless($soloHistorialAdmin)

        {{-- Capa 1: estado actual --}}
        <div class="info-label" style="margin-bottom: 8px;">Estado actual</div>
        <div class="info-row">
            <div class="info-label">Estado PAC</div>
            <div class="info-value-sm">
                @if($esFactura && $document->cancelacionFiscalVencidaSinResolver())
                    Límite de respuesta vencido · CFDI vigente
                @elseif($esFactura && $document->intentoFiscalPrevioSinCancelar())
                    Sin solicitud pendiente ahora · CFDI vigente
                @elseif($document->solicitudFiscalPendiente())
                    Pendiente de cancelación SAT
                @elseif($document->canceladaAnteSat())
                    Cancelado ante el SAT
                @elseif($esFactura && $document->cancelacionFiscalRechazada())
                    Rechazada · el CFDI sigue vigente
                @elseif($esFactura && $document->pendienteCancelacionAntePac())
                    Aún no se envía al SAT
                @elseif($document->estatus_cancelacion_pac)
                    {{ $document->estatus_cancelacion_pac }}
                @elseif($esFactura && $document->tuvoSolicitudFiscalPrevia())
                    Sin solicitud pendiente ahora
                @else
                    Sin solicitud en Facturama
                @endif
            </div>
        </div>
        <div class="info-row">
            <div class="info-label">Estado SAT</div>
            <div class="info-value-sm">
                @if($esFactura && $document->cancelacionFiscalVencidaSinResolver())
                    Vigente · el límite de respuesta en el ERP ya venció
                @elseif($esFactura && $document->intentoFiscalPrevioSinCancelar())
                    Vigente · el intento previo no canceló el CFDI
                @else
                    {{ \App\Services\EstatusCancelacionCfdi::estatusSatParaUsuario(
                        $document->estatus_cancelacion_pac,
                        $document->estatus_sat,
                        $document->codigo_estatus_cancelacion
                    ) }}
                @endif
            </div>
        </div>
        @if($document->is_cancelable ?? null)
        <div class="info-row">
            <div class="info-label">Cancelable (SAT)</div>
            <div class="info-value-sm">{{ $document->is_cancelable }}</div>
        </div>
        @endif
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
        @if($facturaSustituta)
        <div class="info-row">
            <div class="info-label">Factura sustituta</div>
            <div class="info-value-sm">
                <a href="{{ route('facturas.show', $facturaSustituta) }}" class="text-link">
                    {{ $facturaSustituta->folio_completo }}
                </a>
                <div class="text-mono text-muted" style="font-size: 11px; word-break: break-all;">{{ $facturaSustituta->uuid }}</div>
                @if($sustitutoSinRelacion04)
                    <div class="text-muted" style="font-size: 11.5px; margin-top: 4px; color: var(--color-danger);">
                        Sin relación fiscal 04 hacia este CFDI. El motivo 01 no es válido hasta corregirlo.
                    </div>
                @elseif($sustitutoConRelacion04)
                    <div class="text-muted" style="font-size: 11.5px; margin-top: 4px;">
                        Relación 04 (sustitución) verificada en el ERP.
                    </div>
                @endif
            </div>
        </div>
        @elseif($sustitutoSinRelacion04)
        <div class="info-row">
            <div class="info-label">Relación sustituto</div>
            <div class="info-value-sm" style="color: var(--color-danger);">
                El UUID sustituto no tiene tipo de relación 04 hacia esta factura.
            </div>
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
        @endif

        {{-- Capa 2: proceso / eco de la solicitud --}}
        @if($mostrarProceso)
        <div class="info-label" style="margin-top: 16px; margin-bottom: 8px;">Proceso ante el PAC / SAT</div>
        @if($fechaUltimaSolicitud)
        <div class="info-row">
            <div class="info-label">Última solicitud</div>
            <div class="info-value-sm">{{ $fechaUltimaSolicitud->format('d/m/Y H:i') }}</div>
        </div>
        @endif
        @if($codigoAlEnviar)
        <div class="info-row">
            <div class="info-label">Código al enviar</div>
            <div class="info-value-sm text-mono">
                {{ $codigoAlEnviar }}@if($descripcionCodigoAlEnviar) — {{ $descripcionCodigoAlEnviar }}@endif
            </div>
        </div>
        @endif
        @if($mensajeAlEnviar)
        <div class="info-row">
            <div class="info-label">Mensaje al enviar</div>
            <div class="info-value-sm">{{ $mensajeAlEnviar }}</div>
        </div>
        @endif
        @if($limiteAceptacion)
        <div class="info-row">
            <div class="info-label">Límite de aceptación</div>
            <div class="info-value-sm">
                {{ $limiteAceptacion->format('d/m/Y H:i') }}
                @if($esFactura && ! $document->fecha_vencimiento_aceptacion)
                    <span class="text-muted">(estimado)</span>
                @endif
            </div>
        </div>
        @endif
        @if($mostrarPrimerEnvio)
        <div class="info-row">
            <div class="info-label">Primer envío al PAC</div>
            <div class="info-value-sm">
                {{ $fechaPrimerEnvio->format('d/m/Y H:i') }}
                <span class="text-muted" style="font-size: 11px;"> · distinto de la última solicitud</span>
            </div>
        </div>
        @endif
        <p class="form-hint" style="margin: 8px 0 0; font-size: 11.5px;">
            Código y mensaje de esta sección son el eco del envío. El código 201 solo confirma que el SAT recibió la solicitud; el estado actual está arriba.
        </p>
        @endif

        {{-- Capa 3: preview del último evento de bitácora --}}
        @if($mostrarBandaUltimaRespuesta)
        <div class="doc-show-cancel-pac-band" style="margin-top: 16px;">
            <div class="doc-show-cancel-pac-band-head">
                <div class="doc-show-cancel-pac-band-title">Última respuesta técnica del PAC</div>
                <div class="doc-show-cancel-pac-band-date">
                    {{ $ultimoEvento->etiquetaTipo() }} · {{ $ultimoEvento->created_at?->format('d/m/Y H:i') }}
                </div>
            </div>
            @if($ultimoEvento->user)
            <div class="text-muted" style="font-size: 12px; margin-bottom: 10px;">{{ $ultimoEvento->user->name }}</div>
            @endif
            @if($ultimoEvento->status_pac || $ultimoEvento->estatus_sat || $ultimoEvento->codigo_estatus)
            <div class="text-mono" style="font-size: 12px; margin-bottom: 8px; color: var(--color-dark);">
                @if($ultimoEvento->status_pac) PAC: {{ $ultimoEvento->status_pac }} @endif
                @if($ultimoEvento->estatus_sat || $ultimoEvento->status_pac)
                    · SAT: {{ $estatusSatUltimo }}
                @endif
                @if($ultimoEvento->codigo_estatus) · {{ $ultimoEvento->codigo_estatus }} @endif
            </div>
            @endif
            @if($mensajeUltimoEvento)
            <div style="font-size: 13px; line-height: 1.5; color: var(--color-dark); margin-bottom: 6px;">
                {{ $mensajeUltimoEvento }}
            </div>
            @elseif($ultimoEvento->mensaje)
            <div style="font-size: 13px; line-height: 1.5; color: var(--color-dark); margin-bottom: 6px;">
                {{ $ultimoEvento->mensaje }}
            </div>
            @endif
            @if($motivoSatUltimo)
            <div class="text-muted" style="font-size: 12px; margin-top: 4px;">
                Motivo SAT: {{ \App\Services\EstatusCancelacionCfdi::descripcionMotivoSat($motivoSatUltimo) }}
            </div>
            @endif
            @if($uuidSustUltimo)
            <div class="text-mono text-muted" style="font-size: 11px; margin-top: 4px; word-break: break-all;">
                UUID sustituto: {{ $uuidSustUltimo }}
            </div>
            @endif
            <p class="doc-show-cancel-pac-band-note">
                Vista del evento más reciente de la bitácora. El historial completo está abajo.
            </p>
        </div>
        @endif

        @if($esAdmin)
            @if($document->pendienteCancelacionAntePac())
                <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    Cancelación administrativa en el ERP: inventario y saldo ya se revirtieron.
                    El CFDI sigue vigente ante el SAT hasta que use <strong>Cancelar factura</strong>
                    (después de facturar con relación, si aplica). El stock no se volverá a mover al enviar al SAT.
                    Si la cancelación administrativa fue por error, puede <strong>reactivar</strong> la factura en el ERP
                    (permiso de reactivación) mientras no haya envío al PAC.
                </div>
            @elseif($document->cancelacionFiscalVencidaSinResolver())
                <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    El límite de respuesta guardado ya venció y el SAT sigue reportando el CFDI <strong>vigente</strong>
                    (esto no equivale automáticamente a «cancelado por plazo vencido» del SAT).
                    @if($sustitutoSinRelacion04)
                        El intento previo usó motivo <strong>01</strong>, pero el CFDI sustituto <strong>no tiene relación 04</strong> hacia esta factura.
                        No reenvíe el mismo 01: corrija la relación o use motivo <strong>03</strong> si la operación no se llevó a cabo.
                    @elseif($document->cancelacionFiscalVencidaReintentable())
                        Puede reenviar la cancelación; la consulta SAT vigente es reciente. Revise el motivo antes de confirmar.
                    @else
                        Consulte nuevamente el estatus SAT antes de reenviar la cancelación.
                    @endif
                    El ERP permanece cancelado administrativamente y no duplicará movimientos.
                </div>
            @elseif($document->intentoFiscalPrevioSinCancelar())
                <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    Hubo un envío previo al SAT, pero Facturama <strong>ya no tiene una solicitud pendiente</strong> y el CFDI sigue <strong>vigente</strong>.
                    @if($sustitutoSinRelacion04)
                        El intento usó motivo <strong>01</strong> sin relación fiscal <strong>04</strong> en el sustituto.
                        Al reenviar se sugerirá motivo <strong>03</strong>; el 01 seguirá bloqueado hasta corregir la relación.
                    @endif
                    @if($document->intentoFiscalPrevioSinCancelarReintentable())
                        Puede <strong>reenviar la cancelación</strong>; la consulta SAT vigente es reciente.
                    @else
                        Consulte nuevamente el estatus SAT antes de reenviar.
                    @endif
                    El ERP permanece cancelado administrativamente y no duplicará movimientos.
                </div>
            @elseif($document->solicitudFiscalPendiente())
                <div class="alert alert-warning" style="margin-top: 12px; margin-bottom: 0; font-size: 13px;">
                    La solicitud se envió al SAT y la cancelación sigue en proceso; el CFDI aún no figura como cancelado.
                    @if((string) $document->codigo_estatus_cancelacion === '201')
                        El código 201 solo confirma recepción exitosa de la solicitud.
                    @endif
                    El ERP permanece cancelado administrativamente; no se duplican movimientos de inventario.
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
        @endunless

        @if($esFactura && $bitacoraAdmin->isNotEmpty())
            <div class="info-label" style="margin-top: {{ $soloHistorialAdmin ? '0' : '16px' }}; margin-bottom: 8px;">Bitácora administrativa (ERP)</div>
            <div style="display: grid; gap: 8px;">
                @foreach($bitacoraAdmin->take(12) as $eventoAdmin)
                    @php
                        $esRevAdmin = ($eventoAdmin->tipo ?? 'cancelacion') === 'reversion';
                        $enlaceExpediente = $esRevAdmin
                            ? ($bitacoraAdmin->first(fn ($e) => ($e->tipo ?? 'cancelacion') !== 'reversion' && $e->id < $eventoAdmin->id)
                                ?? $bitacoraAdmin->first(fn ($e) => ($e->tipo ?? 'cancelacion') !== 'reversion'))
                            : $eventoAdmin;
                    @endphp
                    <div style="border: 1px solid var(--color-gray-200); border-radius: var(--radius-sm); padding: 8px 10px;">
                        <div style="display: flex; justify-content: space-between; gap: 8px; flex-wrap: wrap;">
                            <strong>{{ $esRevAdmin ? 'Reactivación' : 'Cancelación administrativa' }}</strong>
                            <span class="text-muted" style="font-size: 11px;">{{ $eventoAdmin->created_at?->format('d/m/Y H:i') }}</span>
                        </div>
                        @if($eventoAdmin->user)
                            <div class="text-muted" style="font-size: 11px;">{{ $eventoAdmin->user->name }}</div>
                        @endif
                        <div style="margin-top: 4px; font-size: 12px;">{{ $eventoAdmin->motivo }}</div>
                        @if($enlaceExpediente && auth()->user()?->can('cancelaciones_administrativas.administrar'))
                            <div style="margin-top: 6px;">
                                <a href="{{ route('cancelaciones-administrativas.show', $enlaceExpediente) }}" class="text-link" style="font-size: 12px;">
                                    Ver expediente #{{ $enlaceExpediente->id }}
                                </a>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            @can('cancelaciones_administrativas.administrar')
            <div style="margin-top: 10px;">
                <a href="{{ route('cancelaciones-administrativas.index') }}" class="text-link" style="font-size: 12px;">Ver historial de cancelaciones administrativas</a>
            </div>
            @endcan
        @endif

        @unless($soloHistorialAdmin)
        @if($eventos->isNotEmpty())
            <div class="info-label" style="margin-top: 16px; margin-bottom: 8px;">Bitácora</div>
            <div style="display: grid; gap: 8px;">
                @foreach($eventos->take(12) as $evento)
                    @php
                        $mensajeEvento = \App\Services\EstatusCancelacionCfdi::mensajeUsuario([
                            'status_pac' => $evento->status_pac,
                            'estatus_sat' => $evento->estatus_sat,
                            'codigo_estatus' => $evento->codigo_estatus,
                        ], $esAdmin);
                    @endphp
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
                                @if($evento->estatus_sat || $evento->status_pac)
                                    · SAT: {{ \App\Services\EstatusCancelacionCfdi::estatusSatParaUsuario(
                                        $evento->status_pac,
                                        $evento->estatus_sat,
                                        $evento->codigo_estatus
                                    ) }}
                                @endif
                                @if($evento->codigo_estatus) · {{ $evento->codigo_estatus }} @endif
                            </div>
                        @endif
                        @if($mensajeEvento)
                            <div style="margin-top: 4px;">{{ $mensajeEvento }}</div>
                        @endif
                        @if(is_array($evento->payload) && (!empty($evento->payload['motivo_sat']) || !empty($evento->payload['uuid_sustitucion'])))
                            <div class="text-muted" style="font-size: 11px; margin-top: 4px;">
                                @if(!empty($evento->payload['motivo_sat']))
                                    Motivo SAT: {{ \App\Services\EstatusCancelacionCfdi::descripcionMotivoSat($evento->payload['motivo_sat']) }}
                                @endif
                                @if(!empty($evento->payload['uuid_sustitucion']))
                                    <div class="text-mono" style="word-break: break-all;">UUID sustituto: {{ $evento->payload['uuid_sustitucion'] }}</div>
                                    @if($facturaSustituta && strcasecmp($facturaSustituta->uuid, $evento->payload['uuid_sustitucion']) === 0)
                                        <div>
                                            Factura sustituta:
                                            <a href="{{ route('facturas.show', $facturaSustituta) }}" class="text-link">{{ $facturaSustituta->folio_completo }}</a>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
        @endunless
    </div>
@unless($embebido)
</div>
@endunless
