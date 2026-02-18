@extends('layouts.app')
{{-- resources/views/cotizaciones/show.blade.php --}}

@section('title', 'Cotización ' . $cotizacion->folio)

@php
$breadcrumbs = [
    ['title' => 'Cotizaciones', 'url' => route('cotizaciones.index')],
    ['title' => $cotizacion->folio],
];
@endphp

@section('content')

{{-- Header de la cotización --}}
<div class="cot-header">
    <div style="display:flex; justify-content:space-between; align-items:flex-start; flex-wrap:wrap; gap:16px;">
        <div>
            <div style="font-size:12px; color:rgba(255,255,255,0.6); font-weight:600; text-transform:uppercase; letter-spacing:1px; margin-bottom:6px;">
                Cotización
            </div>
            <div class="cot-folio">{{ $cotizacion->folio }}</div>
            <div class="cot-meta">
                <span class="cot-meta-item">📅 Emisión: <strong>{{ $cotizacion->fecha->format('d/m/Y') }}</strong></span>
                <span class="cot-meta-item">⏰ Vigencia: <strong>{{ $cotizacion->fecha_vencimiento->format('d/m/Y') }}</strong></span>
                <span class="cot-meta-item">
                    @if($cotizacion->tipo_venta === 'credito')
                        💳 <strong>Crédito {{ $cotizacion->dias_credito_aplicados }} días</strong>
                    @else
                        💵 <strong>Contado</strong>
                    @endif
                </span>
            </div>
        </div>
        <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
            {{-- Badge estado --}}
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
            <span class="badge {{ $badgeClass }}" style="font-size:13px; padding: 6px 14px;">{{ $badgeLabel }}</span>
        </div>
    </div>
</div>

{{-- Acciones --}}
<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px;">

    <a href="{{ route('cotizaciones.ver-pdf', $cotizacion->id) }}"
       target="_blank" class="btn btn-light">👁️ Ver PDF</a>

    <a href="{{ route('cotizaciones.descargar-pdf', $cotizacion->id) }}"
       class="btn btn-secondary">📄 Descargar PDF</a>

    @if($cotizacion->puedeEditarse())
    <a href="{{ route('cotizaciones.create') }}?id={{ $cotizacion->id }}"
       class="btn btn-info">✏️ Editar</a>
    @endif

    @if($cotizacion->puedeEnviarse())
    <form action="{{ route('cotizaciones.enviar', $cotizacion->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-warning"
                onclick="return confirm('¿Enviar cotización por email al cliente?')">
            📧 Enviar Email
        </button>
    </form>
    @endif

    @if($cotizacion->puedeAceptarse())
    <form action="{{ route('cotizaciones.aceptar', $cotizacion->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-success"
                onclick="return confirm('¿Marcar esta cotización como aceptada?')">
            ✅ Aceptar
        </button>
    </form>
    @endif

    @if($cotizacion->puedeFacturarse())
    <form action="{{ route('cotizaciones.convertir-factura', $cotizacion->id) }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-primary"
                onclick="return confirm('¿Convertir esta cotización en factura?')">
            💰 Convertir a Factura
        </button>
    </form>
    @endif

</div>

{{-- Datos principales en 2 columnas --}}
<div class="info-grid-2">

    {{-- Cliente --}}
    <div class="card">
        <div class="card-header"><div class="card-title">👤 Cliente</div></div>
        <div class="card-body">
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
            <div class="info-row">
                <div class="info-label">Dirección</div>
                <div class="info-value-sm" style="line-height:1.6;">
                    {{ $cotizacion->cliente_calle }} {{ $cotizacion->cliente_numero_exterior }}
                    @if($cotizacion->cliente_numero_interior) Int. {{ $cotizacion->cliente_numero_interior }}@endif<br>
                    {{ $cotizacion->cliente_colonia }}, {{ $cotizacion->cliente_municipio }}<br>
                    {{ $cotizacion->cliente_estado }} C.P. {{ $cotizacion->cliente_codigo_postal }}
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Datos Cotización --}}
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Datos de la Cotización</div></div>
        <div class="card-body">
            <div class="info-row">
                <div class="info-label">Folio</div>
                <div class="info-value text-mono">{{ $cotizacion->folio }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Fecha de Emisión</div>
                <div class="info-value">{{ $cotizacion->fecha->format('d \d\e F \d\e Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Válida Hasta</div>
                <div class="info-value">{{ $cotizacion->fecha_vencimiento->format('d \d\e F \d\e Y') }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Condición de Pago</div>
                <div class="info-value">
                    @if($cotizacion->tipo_venta === 'credito')
                        <span style="color: var(--color-warning);">💳 Crédito {{ $cotizacion->dias_credito_aplicados }} días</span>
                    @else
                        <span style="color: var(--color-success);">💵 Contado</span>
                    @endif
                </div>
            </div>
            <div class="info-row">
                <div class="info-label">Moneda</div>
                <div class="info-value">{{ $cotizacion->moneda ?? 'MXN' }}</div>
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

</div>

{{-- Tabla de productos --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">📦 Detalle de Productos</div>
        <span class="badge badge-primary">{{ $cotizacion->detalles->count() }} {{ $cotizacion->detalles->count() === 1 ? 'artículo' : 'artículos' }}</span>
    </div>
    <div style="overflow-x:auto;">
        <table style="width:100%; border-collapse:collapse;">
            <thead style="background: var(--color-gray-50);">
                <tr>
                    <th style="padding:11px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200);">Código</th>
                    <th style="padding:11px 16px; text-align:left; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200); width:35%;">Descripción</th>
                    <th style="padding:11px 16px; text-align:center; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200);">Cantidad</th>
                    <th style="padding:11px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200);">Precio Unit.</th>
                    <th style="padding:11px 16px; text-align:center; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200);">Desc %</th>
                    <th style="padding:11px 16px; text-align:center; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200);">IVA</th>
                    <th style="padding:11px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200);">Subtotal</th>
                    <th style="padding:11px 16px; text-align:right; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:0.6px; color: var(--color-gray-600); border-bottom:2px solid var(--color-gray-200);">Total</th>
                </tr>
            </thead>
            <tbody>
                @foreach($cotizacion->detalles as $d)
                <tr style="border-bottom:1px solid var(--color-gray-100);">
                    <td style="padding:12px 16px;">
                        <span class="producto-row-code">{{ $d->codigo }}</span>
                    </td>
                    <td style="padding:12px 16px;">
                        <div class="fw-600" style="font-size:13.5px; color: var(--color-dark);">{{ $d->descripcion }}</div>
                        @if($d->es_producto_manual)
                            <span style="font-size:11px; color: var(--color-warning);">✎ Manual</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px; text-align:center; font-weight:600;">
                        {{ number_format($d->cantidad, 2) }}
                    </td>
                    <td style="padding:12px 16px; text-align:right; font-family: var(--font-mono);">
                        ${{ number_format($d->precio_unitario, 2) }}
                    </td>
                    <td style="padding:12px 16px; text-align:center;">
                        @if($d->descuento_porcentaje > 0)
                            <span style="color: var(--color-danger); font-weight:700;">{{ number_format($d->descuento_porcentaje, 1) }}%</span>
                        @else
                            <span style="color: var(--color-gray-300);">—</span>
                        @endif
                    </td>
                    <td style="padding:12px 16px; text-align:center; font-weight:600; font-size:13px;">
                        @if($d->tasa_iva === null)
                            <span class="text-muted">Exento</span>
                        @else
                            {{ number_format($d->tasa_iva * 100, 0) }}%
                        @endif
                    </td>
                    <td style="padding:12px 16px; text-align:right; font-family: var(--font-mono); font-size:13px;">
                        ${{ number_format($d->subtotal, 2) }}
                    </td>
                    <td style="padding:12px 16px; text-align:right; font-family: var(--font-mono); font-weight:700; color: var(--color-secondary); font-size:14px;">
                        ${{ number_format($d->total, 2) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- TOTALES alineados a la derecha --}}
    <div style="display:flex; justify-content:flex-end; padding: 20px;">
        <div class="totales-panel" style="min-width:320px;">
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
    <div class="card-header"><div class="card-title">📄 Condiciones y Observaciones</div></div>
    <div class="card-body">
        @if($cotizacion->condiciones_pago)
        <div style="margin-bottom:20px;">
            <div class="info-label mb-8">Condiciones Comerciales</div>
            <div style="background: var(--color-gray-50); border-left: 3px solid var(--color-primary); padding: 14px 16px; border-radius: 0 var(--radius-md) var(--radius-md) 0; font-size:13.5px; color: var(--color-gray-700); line-height:1.7;">
                {!! nl2br(e($cotizacion->condiciones_pago)) !!}
            </div>
        </div>
        @endif
        @if($cotizacion->observaciones)
        <div>
            <div class="info-label mb-8">Observaciones</div>
            <div style="background: var(--color-gray-50); border-left: 3px solid var(--color-gray-300); padding: 14px 16px; border-radius: 0 var(--radius-md) var(--radius-md) 0; font-size:13.5px; color: var(--color-gray-700); line-height:1.7;">
                {!! nl2br(e($cotizacion->observaciones)) !!}
            </div>
        </div>
        @endif
    </div>
</div>
@endif

{{-- Botón volver --}}
<div style="text-align:center; padding-bottom:8px;">
    <a href="{{ route('cotizaciones.index') }}" class="btn btn-light">
        ← Volver a Cotizaciones
    </a>
</div>

@endsection