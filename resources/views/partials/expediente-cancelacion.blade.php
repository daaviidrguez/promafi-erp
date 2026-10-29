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
                <div class="doc-cancel-guidance doc-cancel-guidance--warning">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">Pendiente de envío al PAC/SAT</div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip">ERP cancelado</span>
                            <span class="doc-cancel-chip doc-cancel-chip--warn">SAT: Vigente</span>
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        Inventario y saldo ya se revirtieron en el ERP. El CFDI sigue vigente ante el SAT hasta que envíe la cancelación fiscal.
                    </p>
                    <ul class="doc-cancel-guidance-list">
                        <li>Use <strong>Cancelar factura</strong> para enviar al PAC/SAT (después de facturar con relación, si aplica).</li>
                        <li>El stock <strong>no</strong> se volverá a mover al enviar al SAT.</li>
                        <li>Si fue por error, puede <strong>reactivar</strong> en el ERP (permiso de reactivación) mientras no haya envío al PAC.</li>
                    </ul>
                </div>
            @elseif($document->cancelacionFiscalVencidaSinResolver())
                <div class="doc-cancel-guidance doc-cancel-guidance--warning">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">Plazo de respuesta vencido · CFDI vigente</div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip doc-cancel-chip--warn">SAT: Vigente</span>
                            <span class="doc-cancel-chip doc-cancel-chip--danger">Plazo vencido</span>
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        El límite de respuesta guardado ya venció y el SAT sigue reportando el CFDI vigente.
                        Esto no equivale automáticamente a «cancelado por plazo vencido» del SAT.
                    </p>
                    <ul class="doc-cancel-guidance-list">
                        @if($sustitutoSinRelacion04)
                            <li>El intento previo usó motivo <span class="doc-cancel-guidance-code">01</span>, pero el CFDI sustituto <strong>no tiene relación 04</strong>.</li>
                            <li>No reenvíe el mismo 01: corrija la relación o use motivo <span class="doc-cancel-guidance-code">03</span> si la operación no se llevó a cabo.</li>
                        @elseif($document->cancelacionFiscalVencidaReintentable())
                            <li>Puede <strong>reenviar la cancelación</strong>; la consulta SAT vigente es reciente.</li>
                            <li>Revise el motivo antes de confirmar.</li>
                        @else
                            <li>Consulte nuevamente el estatus SAT antes de reenviar la cancelación.</li>
                        @endif
                    </ul>
                    <p class="doc-cancel-guidance-foot">El ERP permanece cancelado administrativamente y no duplicará movimientos.</p>
                </div>
            @elseif($document->intentoFiscalPrevioSinCancelar())
                <div class="doc-cancel-guidance doc-cancel-guidance--warning">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">Envío previo sin solicitud pendiente</div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip doc-cancel-chip--warn">SAT: Vigente</span>
                            <span class="doc-cancel-chip">Sin pendiente en Facturama</span>
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        Hubo un envío previo al SAT, pero Facturama ya no tiene una solicitud pendiente y el CFDI sigue vigente.
                    </p>
                    <ul class="doc-cancel-guidance-list">
                        @if($sustitutoSinRelacion04)
                            <li>El intento usó motivo <span class="doc-cancel-guidance-code">01</span> sin relación fiscal <span class="doc-cancel-guidance-code">04</span> en el sustituto.</li>
                            <li>Al reenviar se sugerirá motivo <span class="doc-cancel-guidance-code">03</span>; el 01 seguirá bloqueado hasta corregir la relación.</li>
                        @endif
                        @if($document->intentoFiscalPrevioSinCancelarReintentable())
                            <li>Puede <strong>reenviar la cancelación</strong>; la consulta SAT vigente es reciente.</li>
                        @else
                            <li>Consulte nuevamente el estatus SAT antes de reenviar.</li>
                        @endif
                    </ul>
                    <p class="doc-cancel-guidance-foot">El ERP permanece cancelado administrativamente y no duplicará movimientos.</p>
                </div>
            @elseif($document->solicitudFiscalPendiente())
                <div class="doc-cancel-guidance doc-cancel-guidance--warning">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">Cancelación en proceso ante el SAT</div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip doc-cancel-chip--warn">En proceso</span>
                            @if((string) $document->codigo_estatus_cancelacion === '201')
                                <span class="doc-cancel-chip">Código 201</span>
                            @endif
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        La solicitud se envió al SAT y la cancelación sigue en proceso; el CFDI aún no figura como cancelado.
                    </p>
                    <ul class="doc-cancel-guidance-list">
                        @if((string) $document->codigo_estatus_cancelacion === '201')
                            <li>El código <span class="doc-cancel-guidance-code">201</span> solo confirma recepción exitosa de la solicitud.</li>
                        @endif
                        <li>Espere la resolución del SAT o consulte el estatus cuando corresponda.</li>
                    </ul>
                    <p class="doc-cancel-guidance-foot">El ERP permanece cancelado administrativamente; no se duplican movimientos de inventario.</p>
                </div>
            @elseif($document->cancelacionFiscalRechazada())
                <div class="doc-cancel-guidance doc-cancel-guidance--warning">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">Cancelación rechazada por el receptor</div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip doc-cancel-chip--danger">Rechazada</span>
                            <span class="doc-cancel-chip doc-cancel-chip--warn">SAT: Vigente</span>
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        El receptor rechazó la cancelación. El CFDI sigue vigente ante el SAT.
                    </p>
                    <ul class="doc-cancel-guidance-list">
                        <li>El ERP permanece cancelado (el stock ya pudo usarse en la factura con relación).</li>
                        <li>Puede <strong>reintentar el envío</strong> al SAT.</li>
                    </ul>
                </div>
            @elseif($document->canceladaAnteSat())
                <div class="doc-cancel-guidance doc-cancel-guidance--info">
                    <div class="doc-cancel-guidance-head">
                        <div class="doc-cancel-guidance-title">Cancelación confirmada</div>
                        <div class="doc-cancel-guidance-chips">
                            <span class="doc-cancel-chip doc-cancel-chip--ok">ERP + SAT</span>
                        </div>
                    </div>
                    <p class="doc-cancel-guidance-lead">
                        Cancelada en el ERP (administrativa) y confirmada ante el SAT. Inventario y saldo no se movieron de nuevo.
                    </p>
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
