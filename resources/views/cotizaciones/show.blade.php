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

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

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
            <div class="table-container" style="border: none; box-shadow: none; border-radius: 0; margin-bottom: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Descripción</th>
                            <th class="td-center">Cant.</th>
                            <th class="td-center">Unidad</th>
                            <th class="td-right">Precio Unit.</th>
                            <th class="td-center">Desc %</th>
                            <th class="td-center">IVA</th>
                            <th class="td-right">Subtotal</th>
                            <th class="td-right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cotizacion->detalles as $d)
                        <tr>
                            <td>
                                <span class="producto-row-code">{{ $d->codigo === 'MANUAL' ? '—' : ($d->codigo ?? '—') }}</span>
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
                <form method="POST" action="{{ route('cotizaciones.aceptar', $cotizacion->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-success w-full"
                            onclick="return confirm('¿Marcar esta cotización como aceptada?')">
                        ✅ Aceptar
                    </button>
                </form>
                @endif

                @if($cotizacion->puedeFacturarse() && $cotizacion->tienePartidasManuales())
                <form method="POST" action="{{ route('cotizaciones.crear-productos-manuales', $cotizacion->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline w-full"
                            onclick="return confirm('¿Crear productos en el catálogo a partir de las partidas manuales? Se usará descripción, unidad y precio unitario.')">
                        📦 Crear producto(s)
                    </button>
                </form>
                @endif

                @if($cotizacion->puedeFacturarse())
                @if($cotizacion->puedeConvertirAFactura())
                <form method="POST" action="{{ route('cotizaciones.convertir-factura', $cotizacion->id) }}">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full"
                            onclick="return confirm('¿Convertir esta cotización en factura?')">
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
                @endif

                <a href="{{ route('cotizaciones.index') }}" class="btn btn-light w-full">← Volver</a>

            </div>
        </div>

    </div>
</div>

@endsection