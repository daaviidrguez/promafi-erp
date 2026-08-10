@extends('layouts.app')
{{-- resources/views/cotizaciones/show.blade.php --}}

@section('title', 'Cotización ' . $cotizacion->folio)
@section('page-title', '📋 Cotización ' . $cotizacion->folio)
@section('page-subtitle', $cotizacion->cliente_nombre)

@php
$breadcrumbs = [
    ['title' => 'Cotizaciones', 'url' => route('cotizaciones.index')],
    ['title' => $cotizacion->folio],
];
@endphp

@section('content')

<div class="cotizacion-show-layout responsive-grid">

    {{-- Columna izquierda --}}
    <div>

        {{-- Cliente --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">👤 Cliente</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Razón Social</div>
                        <div class="info-value">{{ $cotizacion->cliente_nombre }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">RFC</div>
                        <div class="info-value text-mono">{{ $cotizacion->cliente_rfc }}</div>
                    </div>
                    @if($cotizacion->cliente_email)
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value-sm">{{ $cotizacion->cliente_email }}</div>
                    </div>
                    @endif
                    @if($cotizacion->cliente_telefono)
                    <div class="info-row">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value-sm">{{ $cotizacion->cliente_telefono }}</div>
                    </div>
                    @endif
                    @if($cotizacion->cliente_calle)
                    <div class="info-row" style="grid-column: 1 / -1;">
                        <div class="info-label">Dirección</div>
                        <div class="info-value-sm" style="line-height: 1.6;">
                            {{ $cotizacion->cliente_calle }} {{ $cotizacion->cliente_numero_exterior }}
                            @if($cotizacion->cliente_numero_interior) Int. {{ $cotizacion->cliente_numero_interior }}@endif<br>
                            {{ $cotizacion->cliente_colonia }}, {{ $cotizacion->cliente_municipio }}<br>
                            {{ $cotizacion->cliente_estado }} C.P. {{ $cotizacion->cliente_codigo_postal }}
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Detalle de Productos --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Detalle de Productos</div>
                <span class="badge badge-primary">
                    {{ $cotizacion->detalles->count() }} {{ $cotizacion->detalles->count() === 1 ? 'artículo' : 'artículos' }}
                </span>
            </div>
            <div class="table-container table-container--scroll" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th class="td-center">Origen</th>
                            <th>Descripción</th>
                            <th class="td-center">Cant.</th>
                            <th class="td-center">Unidad</th>
                            <th class="td-right">Precio Unit.</th>
                            <th class="td-center">Desc %</th>
                            <th class="td-center">IVA</th>
                            <th class="td-right">Subtotal</th>
                            <th class="td-right">Total</th>
                            <th class="td-center">Fotos</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cotizacion->detalles as $d)
                        <tr>
                            <td>
                                @if($cotizacion->puedeFacturarse())
                                    <div style="display:flex;align-items:center;gap:6px;">
                                        <button type="button" class="btn btn-outline btn-sm btn-icon" title="Asignar producto" onclick="abrirModalAsignarProducto({{ $d->id }})">🔍</button>
                                        <span class="producto-row-code">{{ $d->codigo === 'MANUAL' ? '—' : ($d->codigo ?? '—') }}</span>
                                    </div>
                                @else
                                    <span class="producto-row-code">{{ $d->codigo === 'MANUAL' ? '—' : ($d->codigo ?? '—') }}</span>
                                @endif
                            </td>
                            <td class="td-center">
                                @if(filled($d->origen))
                                    <span class="fw-600" style="font-size:13px;">{{ $d->origen }}</span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td>
                                <div class="fw-600">{{ $d->descripcion }}</div>
                                @if($d->es_producto_manual)
                                    <div class="text-muted" style="font-size: 11px;">✎ Manual</div>
                                @endif
                            </td>
                            <td class="td-center fw-600">{{ number_format($d->cantidad, 2) }}</td>
                            <td class="td-center">{{ $d->unidad ?? $d->producto->unidad ?? 'PZA' }}</td>
                            <td class="td-right text-mono">${{ number_format($d->precio_unitario, 2) }}</td>
                            <td class="td-center">
                                @if($d->descuento_porcentaje > 0)
                                    <span style="color: var(--color-danger); font-weight: 700;">
                                        {{ number_format($d->descuento_porcentaje, 1) }}%
                                    </span>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                            <td class="td-center fw-600" style="font-size: 13px;">
                                @if($d->tasa_iva === null)
                                    <span class="text-muted">Exento</span>
                                @else
                                    {{ number_format($d->tasa_iva * 100, 0) }}%
                                @endif
                            </td>
                            <td class="td-right text-mono" style="font-size: 13px;">
                                ${{ number_format($d->subtotal, 2) }}
                            </td>
                            <td class="td-right text-mono fw-600">
                                ${{ number_format($d->total, 2) }}
                            </td>
                            <td class="td-center">
                                @if($d->tieneImagenes())
                                    <div style="display:flex; flex-wrap:wrap; gap:6px; justify-content:center;">
                                        @foreach($d->imagenes_urls as $url)
                                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" title="Ver imagen">
                                                <img src="{{ $url }}" alt="Foto partida"
                                                     style="width:44px; height:44px; object-fit:cover; border-radius:6px; border:1px solid var(--color-gray-200);">
                                            </a>
                                        @endforeach
                                    </div>
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Referencia (interno) | Totales --}}
            <div class="card-body cotizacion-show-referencia-totales-grid">
                <div class="totales-panel" style="min-width: 0;">
                    <div class="card-title" style="margin: -4px 0 14px 0; padding-bottom: 12px; border-bottom: 1px solid var(--color-gray-200);">🔗 Referencia</div>
                    <div style="margin-bottom: 18px;">
                        <div class="info-label mb-8">Referencia comercial</div>
                        <div style="font-size: 14px; color: var(--color-gray-800);">
                            @if($cotizacion->referencia_comercial)
                                {{ $cotizacion->referencia_comercial }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                    <span class="form-hint" style="display:block;margin-bottom:12px;">Esta información es solo para uso interno y no se mostrará al cliente.</span>
                    <div style="margin-bottom: 18px;">
                        <div class="info-label mb-8">URL</div>
                        @if($cotizacion->referencia_url)
                            <a href="{{ $cotizacion->referencia_url }}" target="_blank" rel="noopener noreferrer" class="text-mono" style="font-size: 13px; word-break: break-all;">{{ $cotizacion->referencia_url }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                    <div style="margin-bottom: 18px;">
                        <div class="info-label mb-8">URL adicional</div>
                        @if($cotizacion->referencia_url_2)
                            <a href="{{ $cotizacion->referencia_url_2 }}" target="_blank" rel="noopener noreferrer" class="text-mono" style="font-size: 13px; word-break: break-all;">{{ $cotizacion->referencia_url_2 }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                    <div>
                        <div class="info-label mb-8">Otra URL</div>
                        @if($cotizacion->referencia_url_3)
                            <a href="{{ $cotizacion->referencia_url_3 }}" target="_blank" rel="noopener noreferrer" class="text-mono" style="font-size: 13px; word-break: break-all;">{{ $cotizacion->referencia_url_3 }}</a>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </div>
                </div>
                <div class="totales-panel" style="min-width: 0;">
                    <div class="totales-row">
                        <span>Subtotal</span>
                        <span class="monto text-mono">${{ number_format($cotizacion->subtotal, 2) }}</span>
                    </div>
                    @if($cotizacion->descuento > 0)
                    <div class="totales-row descuento">
                        <span>Descuento</span>
                        <span class="monto text-mono">−${{ number_format($cotizacion->descuento, 2) }}</span>
                    </div>
                    @endif
                    <div class="totales-row">
                        <span>IVA</span>
                        <span class="monto text-mono">${{ number_format($cotizacion->iva, 2) }}</span>
                    </div>
                    @if(($cotizacion->isr_retenido ?? 0) > 0)
                    <div class="totales-row descuento">
                        <span>ISR retenido</span>
                        <span class="monto text-mono">−${{ number_format($cotizacion->isr_retenido, 2) }}</span>
                    </div>
                    @endif
                    <div class="totales-row grand">
                        <span>TOTAL</span>
                        <span class="monto">${{ number_format($cotizacion->total, 2) }} {{ $cotizacion->moneda ?? 'MXN' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Condiciones y Observaciones --}}
        @if($cotizacion->condiciones_pago || $cotizacion->observaciones)
        <div class="card">
            <div class="card-header">
                <div class="card-title">📄 Condiciones y Observaciones</div>
            </div>
            <div class="card-body">
                @if($cotizacion->condiciones_pago)
                <div style="margin-bottom: 20px;">
                    <div class="info-label mb-8">Condiciones Comerciales</div>
                    <div style="background: var(--color-gray-50); border-left: 3px solid var(--color-primary); padding: 14px 16px; border-radius: 0 var(--radius-md) var(--radius-md) 0; font-size: 13.5px; color: var(--color-gray-700); line-height: 1.7;">
                        {!! nl2br(e($cotizacion->condiciones_pago)) !!}
                    </div>
                </div>
                @endif
                @if($cotizacion->observaciones)
                <div>
                    <div class="info-label mb-8">Observaciones</div>
                    <div style="background: var(--color-gray-50); border-left: 3px solid var(--color-gray-300); padding: 14px 16px; border-radius: 0 var(--radius-md) var(--radius-md) 0; font-size: 13.5px; color: var(--color-gray-700); line-height: 1.7;">
                        {!! nl2br(e($cotizacion->observaciones)) !!}
                    </div>
                </div>
                @endif
            </div>
        </div>
        @endif

        {{-- Documentos de respaldo (uso interno) --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📎 Documentos de respaldo</div>
                @if($cotizacion->adjuntos->count())
                    <span class="badge badge-gray">{{ $cotizacion->adjuntos->count() }}</span>
                @endif
            </div>
            <div class="card-body">
                <p style="margin: 0 0 16px; font-size: 13px; color: var(--color-gray-600); line-height: 1.55;">
                    Archivos de uso interno (cotizaciones de proveedor u otros soportes).
                    No se comparten con el cliente ni se incluyen en el PDF de la cotización.
                </p>

                @if($cotizacion->adjuntos->isEmpty())
                    <p class="text-muted" style="margin: 0 0 16px; font-size: 13px;">Aún no hay documentos cargados.</p>
                @else
                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 16px;">
                        @foreach($cotizacion->adjuntos as $adjunto)
                            <div style="display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 12px 14px; background: var(--color-gray-50); border-radius: var(--radius-md); border: 1px solid var(--color-gray-200);">
                                <div style="min-width: 0; flex: 1;">
                                    <div style="font-size: 13.5px; font-weight: 600; color: var(--color-gray-800); word-break: break-word;">
                                        📄 {{ $adjunto->nombre_original }}
                                    </div>
                                    <div style="margin-top: 4px; font-size: 12px; color: var(--color-gray-500);">
                                        {{ $adjunto->created_at?->format('d/m/Y H:i') }}
                                        · {{ $adjunto->tamanoLegible() }}
                                        @if($adjunto->usuario)
                                            · {{ $adjunto->usuario->name }}
                                        @endif
                                    </div>
                                    @if($adjunto->nota)
                                        <div style="margin-top: 6px; font-size: 12.5px; color: var(--color-gray-600);">
                                            {{ $adjunto->nota }}
                                        </div>
                                    @endif
                                </div>
                                <div style="display: flex; flex-wrap: wrap; gap: 6px; flex-shrink: 0;">
                                    <a href="{{ route('cotizaciones.adjuntos.ver', [$cotizacion->id, $adjunto->id]) }}"
                                       target="_blank" class="btn btn-outline btn-sm">Ver</a>
                                    <a href="{{ route('cotizaciones.adjuntos.descargar', [$cotizacion->id, $adjunto->id]) }}"
                                       class="btn btn-light btn-sm">Descargar</a>
                                    @can('cotizaciones.adjuntos')
                                    <form method="POST"
                                          action="{{ route('cotizaciones.adjuntos.destroy', [$cotizacion->id, $adjunto->id]) }}"
                                          onsubmit="return confirm('¿Eliminar este documento de respaldo?');">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-light btn-sm" style="color: var(--color-danger, #b91c1c);">Eliminar</button>
                                    </form>
                                    @endcan
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @can('cotizaciones.adjuntos')
                <form method="POST"
                      action="{{ route('cotizaciones.adjuntos.store', $cotizacion->id) }}"
                      enctype="multipart/form-data"
                      style="padding-top: 4px; border-top: 1px solid var(--color-gray-200);">
                    @csrf
                    <div class="info-label mb-8" style="margin-top: 12px;">Subir PDF</div>
                    <div style="display: grid; gap: 10px;">
                        <input type="file" name="archivo" accept=".pdf,application/pdf" class="form-control" required>
                        <input type="text" name="nota" class="form-control"
                               maxlength="255"
                               placeholder="Nota opcional (ej. Truper, correo 12/mar)"
                               value="{{ old('nota') }}">
                        @error('archivo')
                            <div class="text-danger" style="font-size: 12.5px;">{{ $message }}</div>
                        @enderror
                        @error('nota')
                            <div class="text-danger" style="font-size: 12.5px;">{{ $message }}</div>
                        @enderror
                        <button type="submit" class="btn btn-primary" style="justify-self: start;">Subir documento</button>
                    </div>
                </form>
                @endcan
            </div>
        </div>

    </div>

    {{-- Columna derecha --}}
    <div>

        {{-- Información de la Cotización --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @php
                            $estados = [
                                'borrador'  => ['badge-warning',  '📝 Borrador'],
                                'enviada'   => ['badge-info',     '📧 Enviada'],
                                'aceptada'  => ['badge-success',  '✅ Aceptada'],
                                'facturada' => ['badge-primary',  '💰 Facturada'],
                                'rechazada' => ['badge-danger',   '✗ Rechazada'],
                                'vencida'   => ['badge-gray',     '⏰ Vencida'],
                            ];
                            [$badgeClass, $badgeLabel] = $estados[$cotizacion->estado] ?? ['badge-gray', $cotizacion->estado];
                            if ($cotizacion->estado === 'facturada' && $cotizacion->factura?->estado === 'timbrada') {
                                $badgeClass = 'badge-success';
                            }
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $badgeLabel }}</span>
                    </div>
                </div>
                <div class="info-row" style="margin-top: 16px;">
                    <div class="info-label">Fecha de Emisión</div>
                    <div class="info-value-sm">{{ $cotizacion->fecha->format('d/m/Y') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Válida Hasta</div>
                    <div class="info-value-sm">{{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Condición de Pago</div>
                    <div style="margin-top: 4px;">
                        @if($cotizacion->tipo_venta === 'credito')
                            <span class="badge badge-warning">💳 Crédito {{ $cotizacion->dias_credito_aplicados }} días</span>
                        @else
                            <span class="badge badge-success">💵 Contado</span>
                        @endif
                    </div>
                </div>
                @if($cotizacion->forma_pago)
                <div class="info-row">
                    <div class="info-label">Forma de pago</div>
                    <div class="info-value-sm">{{ optional(\App\Models\FormaPago::where('clave', $cotizacion->forma_pago)->first())->etiqueta ?? $cotizacion->forma_pago }}</div>
                </div>
                @endif
                <div class="info-row">
                    <div class="info-label">Moneda</div>
                    <div class="info-value-sm">{{ $cotizacion->moneda ?? 'MXN' }}</div>
                </div>
                @if($cotizacion->usuario)
                <div class="info-row">
                    <div class="info-label">Elaboró</div>
                    <div class="info-value-sm">{{ $cotizacion->usuario->name }}</div>
                </div>
                @endif
                @if($cotizacion->fecha_envio)
                <div class="info-row">
                    <div class="info-label">Enviada el</div>
                    <div class="info-value-sm">{{ $cotizacion->fecha_envio->format('d/m/Y H:i') }}</div>
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

                <a href="{{ route('cotizaciones.ver-pdf', $cotizacion->id) }}"
                   target="_blank" class="btn btn-outline w-full">👁️ Ver PDF</a>

                <a href="{{ route('cotizaciones.descargar-pdf', $cotizacion->id) }}"
                   class="btn btn-outline w-full">📄 Descargar PDF</a>

                @if($cotizacion->puedeEditarse())
                <a href="{{ route('cotizaciones.create') }}?id={{ $cotizacion->id }}"
                   class="btn btn-primary w-full">✏️ Editar</a>
                @endif

                @if($cotizacion->puedeEnviarse())
                <form method="POST" action="{{ route('cotizaciones.enviar', $cotizacion->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-warning w-full"
                            onclick="return confirm('¿Enviar cotización por email al cliente?')">
                        📧 Enviar Email
                    </button>
                </form>
                @endif

                @if($cotizacion->puedeAceptarse())
                <form id="formAceptarCotizacion" method="POST" action="{{ route('cotizaciones.aceptar', $cotizacion->id) }}">
                    @csrf
                    @if($cotizacion->estado === 'vencida')
                        <button type="button" class="btn btn-success w-full" onclick="abrirModalAceptarVencida()">
                            ✅ Aceptar
                        </button>
                    @else
                        <button type="button" class="btn btn-success w-full" onclick="abrirModalAceptarCotizacion()">
                            ✅ Aceptar
                        </button>
                    @endif
                </form>
                @endif

                @if($cotizacion->puedeFacturarse())
                    <button type="button" class="btn btn-outline w-full" onclick="document.getElementById('modalAsignarProductosCotizacion').classList.add('show')">
                        📦 Asignar producto(s)
                    </button>
                @endif

                @if($cotizacion->puedeCrearEntradaAnticipada())
                @can('entradas_anticipadas.crear')
                <a href="{{ route('cotizaciones.crear-entrada-anticipada', $cotizacion->id) }}"
                   class="btn btn-outline w-full">
                    📥 Crear entradas anticipadas
                </a>
                @endcan
                @endif

                @if($cotizacion->puedeFacturarse())
                @can('facturas.crear')
                @if($cotizacion->puedeConvertirAFactura())
                <form id="formConvertirFacturaCotizacion" method="POST" action="{{ route('cotizaciones.convertir-factura', $cotizacion->id) }}">
                    @csrf
                    <button type="button" class="btn btn-primary w-full" onclick="abrirModalConvertirFactura()">
                        💰 Convertir a Factura
                    </button>
                </form>
                @else
                <button type="button" class="btn btn-primary w-full" disabled
                        title="{{ $cotizacion->motivoNoConvertirAFactura() }}">
                    💰 Convertir a Factura
                </button>
                <p class="text-muted small mt-1 mb-0">{{ $cotizacion->motivoNoConvertirAFactura() }}</p>
                @endif
                @endcan
                @endif

                @if($cotizacion->factura)
                @can('facturas.ver')
                <a href="{{ route('facturas.show', $cotizacion->factura->id) }}"
                   class="btn btn-outline w-full">💰 Ver factura relacionada</a>
                @endcan
                @endif

                <a href="{{ route('cotizaciones.index') }}" class="btn btn-light w-full">← Volver</a>

            </div>
        </div>

    </div>
</div>

{{-- Modal: Confirmar aceptación de cotización --}}
@if($cotizacion->puedeAceptarse() && $cotizacion->estado !== 'vencida')
<div id="modalAceptarCotizacion" class="modal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title">Confirmar aceptación</div>
            <button type="button" class="modal-close" onclick="cerrarModalAceptarCotizacion()" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom:0;">
                ¿Marcar esta cotización como <strong>aceptada</strong>?
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="cerrarModalAceptarCotizacion()">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="confirmarAceptarCotizacion()">Aceptar cotización</button>
        </div>
    </div>
</div>
@endif

{{-- Modal: Confirmar aceptación de cotización vencida --}}
@if($cotizacion->estado === 'vencida')
<div id="modalAceptarCotizacionVencida" class="modal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title">⚠️ Cotización vencida</div>
            <button type="button" class="modal-close" onclick="cerrarModalAceptarVencida()">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom:8px;">
                Esta cotización está vencida. Antes de aceptarla, verifique que los precios sigan siendo válidos.
            </p>
            <p class="text-muted" style="margin-bottom:0;">
                Si continúa, la cotización pasará a estado <strong>aceptada</strong> y el flujo seguirá de forma normal.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="cerrarModalAceptarVencida()">Cancelar</button>
            <button type="button" class="btn btn-success" onclick="confirmarAceptarCotizacionVencida()">Aceptar cotización</button>
        </div>
    </div>
</div>
@endif

{{-- Modal: Confirmar conversión a factura --}}
@if($cotizacion->puedeFacturarse() && $cotizacion->puedeConvertirAFactura())
@can('facturas.crear')
<div id="modalConvertirFacturaCotizacion" class="modal">
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-header">
            <div class="modal-title">Convertir a factura</div>
            <button type="button" class="modal-close" onclick="cerrarModalConvertirFactura()" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom:0;">
                ¿Convertir esta cotización en una <strong>factura en borrador</strong>?
                El stock y la clave SAT se validarán al timbrar.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-light" onclick="cerrarModalConvertirFactura()">Cancelar</button>
            <button type="button" class="btn btn-primary" onclick="confirmarConvertirFactura()">Convertir a factura</button>
        </div>
    </div>
</div>
@endcan
@endif

{{-- Modal: Asignar productos (instrucciones) --}}
<div id="modalAsignarProductosCotizacion" class="modal">
    <div class="modal-box" style="max-width:560px;">
        <div class="modal-header">
            <div class="modal-title">📦 Asignar producto(s)</div>
            <button type="button" class="modal-close" onclick="document.getElementById('modalAsignarProductosCotizacion').classList.remove('show')">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom:8px;">
                Debe seleccionar la <strong>lupita</strong> en las partidas para asignar un producto del catálogo.
                Si aún no existe, puede crearlo de forma rápida desde ese mismo modal.
            </p>
            <p class="text-muted" style="margin-bottom:0;">
                Cuando todas las partidas tengan producto asignado se habilitará <strong>Convertir a factura</strong>.
                El stock y la clave SAT se validan al <strong>timbrar</strong>.
            </p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="document.getElementById('modalAsignarProductosCotizacion').classList.remove('show')">Entendido</button>
        </div>
    </div>
</div>

{{-- Modal: Buscar y asignar producto a una partida --}}
<div id="modalAsignarProductoCotizacion" class="modal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title">Asignar producto</div>
            <button type="button" class="modal-close" onclick="cerrarModalAsignarProducto()">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <input type="text" id="modalBuscarProductoCot" placeholder="Buscar por código o nombre..." class="form-control" autocomplete="off">
            </div>
            <div id="modalProductoListaCot" class="table-container" style="max-height:280px;overflow-y:auto;">
                <p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>
            </div>
        </div>
        @can('productos.crear')
        <div class="modal-footer" style="flex-wrap:wrap; gap:8px; justify-content:space-between;">
            <button type="button" class="btn btn-outline" onclick="abrirModalCrearProductoRapido()">¿Deseas crear el producto?</button>
            <button type="button" class="btn btn-light" onclick="cerrarModalAsignarProducto()">Cerrar</button>
        </div>
        @endcan
    </div>
</div>

{{-- Modal: Creación rápida de producto --}}
@can('productos.crear')
<div id="modalCrearProductoRapidoCotizacion" class="modal">
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-header">
            <div class="modal-title">Creación rápida de producto</div>
            <button type="button" class="modal-close" onclick="cerrarModalCrearProductoRapido()" aria-label="Cerrar">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin-bottom:12px;">
                Se creará un producto con código <span class="text-mono">PSI-…</span> automático.
                La Clave Prod./Serv. es opcional: si no la conoce, se usa <span class="text-mono">01010101</span> provisional (se valida al timbrar).
            </p>
            <div class="form-group">
                <label class="form-label">Código</label>
                <input type="text" class="form-control text-mono" value="Automático (PSI-…)" disabled>
            </div>
            <div class="form-group">
                <label class="form-label">Nombre</label>
                <input type="text" id="crearProductoRapidoNombre" class="form-control" readonly>
            </div>
            <div class="form-group search-box" style="margin-bottom:0;">
                <label class="form-label">Clave Prod./Serv. <span class="text-muted fw-400">(opcional)</span></label>
                <input type="hidden" id="crearProductoRapidoClaveSat" value="01010101">
                <input type="text" id="crearProductoRapidoClaveSatInput" class="form-control text-mono"
                       value="01010101"
                       placeholder="Buscar clave SAT o dejar 01010101…"
                       autocomplete="off">
                <div id="crearProductoRapidoClaveSatResults" class="autocomplete-results"></div>
                <p class="text-muted small mb-0" style="margin-top:6px;">Escriba 2+ caracteres para buscar en el catálogo SAT.</p>
            </div>
            <p id="crearProductoRapidoAviso" class="text-muted small mt-2 mb-0" style="display:none;"></p>
        </div>
        <div class="modal-footer" style="flex-wrap:wrap; gap:8px; justify-content:flex-end;">
            <button type="button" class="btn btn-light" onclick="cerrarModalCrearProductoRapido()">Cancelar</button>
            <button type="button" class="btn btn-warning" id="btnCrearProductoRapidoForzar" style="display:none;" onclick="guardarProductoRapido(true)">Crear de todas formas</button>
            <button type="button" class="btn btn-primary" id="btnCrearProductoRapidoGuardar" onclick="guardarProductoRapido(false)">Guardar y asignar</button>
        </div>
    </div>
</div>
@endcan

@push('scripts')
@if(session('success'))
<script>
(function () {
    try {
        var userId = @json((int) auth()->id());
        var cotId = @json((int) $cotizacion->id);
        var pointerKey = 'promafi:cotizacion-pointer:' + userId;
        var editKey = 'promafi:cotizacion-draft:edit:' + userId + ':' + cotId;
        var createKey = 'promafi:cotizacion-draft:create:' + userId + ':new';
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
(function() {
    window.abrirModalAceptarCotizacion = function() {
        const modal = document.getElementById('modalAceptarCotizacion');
        if (modal) modal.classList.add('show');
    };

    window.cerrarModalAceptarCotizacion = function() {
        const modal = document.getElementById('modalAceptarCotizacion');
        if (modal) modal.classList.remove('show');
    };

    window.confirmarAceptarCotizacion = function() {
        const form = document.getElementById('formAceptarCotizacion');
        if (form) form.submit();
    };

    window.abrirModalAceptarVencida = function() {
        const modal = document.getElementById('modalAceptarCotizacionVencida');
        if (modal) modal.classList.add('show');
    };

    window.cerrarModalAceptarVencida = function() {
        const modal = document.getElementById('modalAceptarCotizacionVencida');
        if (modal) modal.classList.remove('show');
    };

    window.confirmarAceptarCotizacionVencida = function() {
        const form = document.getElementById('formAceptarCotizacion');
        if (form) form.submit();
    };

    window.abrirModalConvertirFactura = function() {
        const modal = document.getElementById('modalConvertirFacturaCotizacion');
        if (modal) modal.classList.add('show');
    };

    window.cerrarModalConvertirFactura = function() {
        const modal = document.getElementById('modalConvertirFacturaCotizacion');
        if (modal) modal.classList.remove('show');
    };

    window.confirmarConvertirFactura = function() {
        const form = document.getElementById('formConvertirFacturaCotizacion');
        if (form) form.submit();
    };

    const listarUrl = '{{ route("cotizaciones.buscar-productos") }}';
    const buscarClaveSatUrl = '{{ route("productos.buscar-clave-sat") }}';
    const asignarUrlTpl = '{{ route("cotizaciones.detalles.asignar-producto", ["cotizacion" => $cotizacion->id, "detalle" => "__DETALLE__"]) }}';
    const crearRapidoUrlTpl = '{{ route("cotizaciones.detalles.crear-producto-rapido", ["cotizacion" => $cotizacion->id, "detalle" => "__DETALLE__"]) }}';
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const detallesPartidas = @json($cotizacion->detalles->mapWithKeys(function ($d) {
        return [$d->id => [
            'descripcion' => (string) ($d->descripcion ?? ''),
            'unidad' => (string) ($d->unidad ?? 'PZA'),
        ]];
    }));
    let detalleActual = null;
    let timer = null;
    let timerClaveSat = null;

    function resetClaveSatRapidaCot() {
        const hid = document.getElementById('crearProductoRapidoClaveSat');
        const inp = document.getElementById('crearProductoRapidoClaveSatInput');
        const box = document.getElementById('crearProductoRapidoClaveSatResults');
        if (hid) hid.value = '01010101';
        if (inp) inp.value = '01010101';
        if (box) box.classList.remove('show');
    }

    function claveSatDesdeInputsCot() {
        const hid = document.getElementById('crearProductoRapidoClaveSat');
        const inp = document.getElementById('crearProductoRapidoClaveSatInput');
        const fromHid = (hid && hid.value) ? String(hid.value).trim() : '';
        if (/^\d{8}$/.test(fromHid)) return fromHid;
        let raw = (inp && inp.value) ? String(inp.value).trim() : '';
        if (raw.indexOf(' - ') !== -1) raw = raw.split(' - ')[0].trim();
        const digits = raw.replace(/\D+/g, '');
        return digits;
    }

    window.abrirModalAsignarProducto = function(detalleId) {
        detalleActual = detalleId;
        document.getElementById('modalAsignarProductoCotizacion').classList.add('show');
        document.getElementById('modalBuscarProductoCot').value = '';
        document.getElementById('modalBuscarProductoCot').focus();
        document.getElementById('modalProductoListaCot').innerHTML = '<p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>';
    };

    window.cerrarModalAsignarProducto = function() {
        document.getElementById('modalAsignarProductoCotizacion').classList.remove('show');
        detalleActual = null;
    };

    window.abrirModalCrearProductoRapido = function() {
        if (!detalleActual) return;
        const info = detallesPartidas[detalleActual] || detallesPartidas[String(detalleActual)] || {};
        const nombreInput = document.getElementById('crearProductoRapidoNombre');
        const aviso = document.getElementById('crearProductoRapidoAviso');
        const btnForzar = document.getElementById('btnCrearProductoRapidoForzar');
        if (nombreInput) nombreInput.value = info.descripcion || '';
        if (aviso) {
            aviso.style.display = 'none';
            aviso.textContent = '';
        }
        if (btnForzar) btnForzar.style.display = 'none';
        resetClaveSatRapidaCot();
        document.getElementById('modalAsignarProductoCotizacion').classList.remove('show');
        const modalCrear = document.getElementById('modalCrearProductoRapidoCotizacion');
        if (modalCrear) modalCrear.classList.add('show');
    };

    window.cerrarModalCrearProductoRapido = function() {
        const modal = document.getElementById('modalCrearProductoRapidoCotizacion');
        if (modal) modal.classList.remove('show');
        const aviso = document.getElementById('crearProductoRapidoAviso');
        const btnForzar = document.getElementById('btnCrearProductoRapidoForzar');
        if (aviso) {
            aviso.style.display = 'none';
            aviso.textContent = '';
        }
        if (btnForzar) btnForzar.style.display = 'none';
        resetClaveSatRapidaCot();
        if (detalleActual) {
            document.getElementById('modalAsignarProductoCotizacion').classList.add('show');
        }
    };

    window.guardarProductoRapido = function(forzar) {
        if (!detalleActual) return;
        const btnGuardar = document.getElementById('btnCrearProductoRapidoGuardar');
        const btnForzar = document.getElementById('btnCrearProductoRapidoForzar');
        const aviso = document.getElementById('crearProductoRapidoAviso');
        const claveSat = claveSatDesdeInputsCot();
        if (claveSat !== '' && !/^\d{8}$/.test(claveSat)) {
            if (aviso) {
                aviso.style.display = 'block';
                aviso.textContent = 'La Clave Prod./Serv. debe tener 8 dígitos (o deje 01010101).';
            }
            return;
        }
        if (btnGuardar) btnGuardar.disabled = true;
        if (btnForzar) btnForzar.disabled = true;

        const url = crearRapidoUrlTpl.replace('__DETALLE__', String(detalleActual));
        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ forzar: !!forzar, clave_sat: claveSat || '01010101' })
        })
            .then(async function(r) {
                const resp = await r.json().catch(function() { return null; });
                if (r.ok && resp && resp.success === true) {
                    window.location.reload();
                    return;
                }
                if (resp && resp.needs_confirm) {
                    if (aviso) {
                        aviso.style.display = 'block';
                        aviso.textContent = resp.message || 'Ya existe un producto similar.';
                    }
                    if (btnForzar) btnForzar.style.display = '';
                    return;
                }
                const msg = (resp && resp.message) ? resp.message : 'No se pudo crear el producto.';
                if (aviso) {
                    aviso.style.display = 'block';
                    aviso.textContent = msg;
                } else {
                    alert(msg);
                }
            })
            .catch(function() {
                if (aviso) {
                    aviso.style.display = 'block';
                    aviso.textContent = 'No se pudo crear el producto.';
                } else {
                    alert('No se pudo crear el producto.');
                }
            })
            .finally(function() {
                if (btnGuardar) btnGuardar.disabled = false;
                if (btnForzar) btnForzar.disabled = false;
            });
    };

    (function initClaveSatRapidaCot() {
        const inp = document.getElementById('crearProductoRapidoClaveSatInput');
        const hid = document.getElementById('crearProductoRapidoClaveSat');
        const box = document.getElementById('crearProductoRapidoClaveSatResults');
        if (!inp || !hid || !box) return;

        function buscarClaveSat(q) {
            fetch(buscarClaveSatUrl + '?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.length) {
                        box.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
                    } else {
                        box.innerHTML = data.map(function(item) {
                            const clave = String(item.clave || '').replace(/"/g, '&quot;');
                            const desc = String(item.descripcion || '').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                            const etiqueta = clave + ' - ' + desc;
                            return '<div class="autocomplete-item" data-clave="' + clave + '" data-etiqueta="' + etiqueta.replace(/"/g, '&quot;') + '">' +
                                '<div class="autocomplete-item-name">' + clave + ' - ' + desc + '</div></div>';
                        }).join('');
                        box.querySelectorAll('.autocomplete-item[data-clave]').forEach(function(el) {
                            el.addEventListener('click', function() {
                                hid.value = this.getAttribute('data-clave') || '';
                                inp.value = this.getAttribute('data-etiqueta') || hid.value;
                                box.classList.remove('show');
                            });
                        });
                    }
                    box.classList.add('show');
                })
                .catch(function() {});
        }

        inp.addEventListener('input', function() {
            clearTimeout(timerClaveSat);
            const raw = this.value.trim();
            const q = raw.indexOf(' - ') !== -1 ? raw.split(' - ')[0].trim() : raw;
            const digits = q.replace(/\D+/g, '');
            if (/^\d{8}$/.test(digits)) {
                hid.value = digits;
            } else if (raw === '') {
                hid.value = '';
            }
            if (q.length < 2) { box.classList.remove('show'); return; }
            timerClaveSat = setTimeout(function() { buscarClaveSat(q); }, 280);
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-box')) box.classList.remove('show');
        });
    })();

    document.getElementById('modalBuscarProductoCot').addEventListener('input', function() {
        clearTimeout(timer);
        const q = this.value.trim();
        if (q.length < 2) {
            document.getElementById('modalProductoListaCot').innerHTML = '<p class="text-muted text-center py-3">Escriba al menos 2 caracteres para buscar.</p>';
            return;
        }
        timer = setTimeout(function() {
            fetch(listarUrl + '?q=' + encodeURIComponent(q))
                .then(r => r.json())
                .then(function(list) {
                    const productos = (list || []).filter(x => x.tipo === 'producto');
                    const div = document.getElementById('modalProductoListaCot');
                    if (!productos.length) {
                        div.innerHTML = '<p class="text-muted text-center py-3">Sin resultados.</p>';
                        return;
                    }
                    div.innerHTML = '<table><thead><tr><th>Código</th><th>Nombre</th><th></th></tr></thead><tbody>' +
                        productos.map(function(p) {
                            const codigo = (p.codigo || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            const nombre = (p.nombre || '').replace(/"/g, '&quot;').replace(/</g, '&lt;');
                            return '<tr><td class="text-mono">' + codigo + '</td><td>' + nombre + '</td><td><button type="button" class="btn btn-primary btn-sm" data-id="' + p.id + '">Asignar</button></td></tr>';
                        }).join('') + '</tbody></table>';
                    div.querySelectorAll('button[data-id]').forEach(function(btn) {
                        btn.addEventListener('click', function() {
                            const productoId = this.getAttribute('data-id');
                            if (!detalleActual) return;
                            const url = asignarUrlTpl.replace('__DETALLE__', String(detalleActual));
                            fetch(url, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                    'X-CSRF-TOKEN': csrfToken,
                                    'X-Requested-With': 'XMLHttpRequest'
                                },
                                body: JSON.stringify({ producto_id: productoId })
                            })
                                .then(r => r.json())
                                .then(function(resp) {
                                    if (!resp || resp.success !== true) {
                                        alert(resp && resp.message ? resp.message : 'No se pudo asignar el producto.');
                                        return;
                                    }
                                    window.location.reload();
                                })
                                .catch(function() { alert('No se pudo asignar el producto.'); });
                        });
                    });
                })
                .catch(function() {
                    document.getElementById('modalProductoListaCot').innerHTML = '<p class="text-danger text-center py-3">Error al buscar.</p>';
                });
        }, 280);
    });
})();
</script>
@endpush

@endsection