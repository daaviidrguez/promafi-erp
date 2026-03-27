{{-- Contenido CFDI 4.0 para factura: emisor, receptor, datos comprobante, conceptos, totales, timbre --}}
@php
    $f = $doc;
    $e = $empresa;
    $totalIva = 0;
    $totalRetenciones = 0;
    $impuestosPorTasa = []; // [tasa => ['base' => x, 'importe' => y, 'nombre' => 'IVA 16%']]
    foreach ($f->detalles ?? [] as $d) {
        foreach ($d->impuestos ?? [] as $imp) {
            if ($imp->tipo === 'traslado') {
                $totalIva += (float) $imp->importe;
                $tasa = (float) ($imp->tasa_o_cuota ?? 0);
                $key = (string) $tasa;
                if (!isset($impuestosPorTasa[$key])) {
                    $pct = $tasa >= 1 ? $tasa : ($tasa * 100);
                    $impuestosPorTasa[$key] = ['base' => 0, 'importe' => 0, 'nombre' => ($imp->nombre_impuesto ?? 'IVA') . ' ' . number_format($pct, 0) . '%'];
                }
                $impuestosPorTasa[$key]['base'] += (float) ($imp->base ?? 0);
                $impuestosPorTasa[$key]['importe'] += (float) $imp->importe;
            } else {
                $totalRetenciones += (float) $imp->importe;
            }
        }
    }
    $fechaEmision = $f->fecha_emision ? \Carbon\Carbon::parse($f->fecha_emision) : null;
    $fechaTimbrado = $f->fecha_timbrado ? \Carbon\Carbon::parse($f->fecha_timbrado) : null;
    // Si la hora está en ceros, usar hora real (created_at / updated_at) para no mostrar 00:00:00
    $fechaExpedicionMostrar = $fechaEmision;
    if ($fechaEmision && $fechaEmision->format('H:i:s') === '00:00:00' && $f->created_at) {
        $fechaExpedicionMostrar = $fechaEmision->copy()->setTime(
            (int) $f->created_at->format('H'),
            (int) $f->created_at->format('i'),
            (int) $f->created_at->format('s')
        );
    }
    $fechaCertificacionMostrar = $fechaTimbrado;
    if ($fechaTimbrado && $fechaTimbrado->format('H:i:s') === '00:00:00' && $f->updated_at) {
        $fechaCertificacionMostrar = $f->updated_at;
    }

    $usoCfdiEtiqueta = \App\Models\UsoCfdi::where('clave', $f->uso_cfdi)->first()?->etiqueta ?? $f->uso_cfdi;
    $formaPagoEtiqueta = \App\Models\FormaPago::where('clave', $f->forma_pago)->first()?->etiqueta ?? $f->forma_pago;
    $metodoPagoEtiqueta = \App\Models\MetodoPago::where('clave', $f->metodo_pago)->first()?->etiqueta ?? $f->metodo_pago;
    $regimenReceptorEtiqueta = $f->regimen_fiscal_receptor
        ? (\App\Models\RegimenFiscal::where('clave', $f->regimen_fiscal_receptor)->first()?->etiqueta ?? $f->regimen_fiscal_receptor)
        : '-';

    $qrVerificacionDataUri = null;
    if ($f->uuid && $f->rfc_emisor && $f->rfc_receptor) {
        $urlVerificacion = urlVerificacionSat($f->uuid, $f->rfc_emisor, $f->rfc_receptor, (float) $f->total, $f->sello_cfdi ?? null);
        $qrVerificacionDataUri = qrCodeDataUri($urlVerificacion, 110);
    }
@endphp

{{-- RECEPTOR (izq) y DATOS DEL COMPROBANTE (der). Emisor ya va en el header. --}}
<table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:2px;">
<tr>
    <td width="50%" valign="top" style="padding-right:6px;">
        <div class="info-box" style="font-size:6.5pt; line-height:1.2; padding:2px 6px; margin-bottom:2px;">
            <div class="section-title" style="font-size:7pt; margin-bottom:1px; padding-bottom:1px;">RECEPTOR</div>
            <strong>RFC:</strong> {{ $f->rfc_receptor }}<br>
            <strong>Nombre:</strong> {{ $f->nombre_receptor }}<br>
            <strong>Uso CFDI:</strong> {{ $usoCfdiEtiqueta }}<br>
            <strong>Régimen fiscal:</strong> {{ $regimenReceptorEtiqueta }}<br>
            <strong>Fecha de emisión:</strong> {{ $fechaEmision ? $fechaEmision->format('d/m/Y') : '-' }}<br>
            @if(($f->metodo_pago ?? '') === 'PPD' && $f->cuentaPorCobrar)
            <strong>Vencimiento:</strong> {{ $f->cuentaPorCobrar->fecha_vencimiento ? $f->cuentaPorCobrar->fecha_vencimiento->format('d/m/Y') : '-' }}<br>
            <strong>Días de crédito:</strong> {{ $f->cuentaPorCobrar->fecha_vencimiento && $fechaEmision ? $fechaEmision->diffInDays($f->cuentaPorCobrar->fecha_vencimiento, false) : '-' }}<br>
            @endif
            <strong>Domicilio fiscal:</strong>
            @if($f->cliente && ($f->cliente->calle || $f->cliente->codigo_postal))
                {{ $f->cliente->calle ?? '' }} {{ $f->cliente->numero_exterior ?? '' }}{{ $f->cliente->numero_interior ? ' Int. ' . $f->cliente->numero_interior : '' }}{{ ($f->cliente->colonia ?? '') ? ', ' . $f->cliente->colonia : '' }}{{ ($f->cliente->municipio ?? '') ? ', ' . $f->cliente->municipio : '' }}{{ ($f->cliente->estado ?? '') ? ', ' . $f->cliente->estado : '' }}{{ ($f->cliente->codigo_postal ?? $f->domicilio_fiscal_receptor ?? '') ? ', C.P. ' . ($f->cliente->codigo_postal ?? $f->domicilio_fiscal_receptor) : '' }}
            @else
                C.P. {{ $f->domicilio_fiscal_receptor ?? '-' }}
            @endif
            @if(!empty($f->orden_compra))
            <br><strong>Orden de compra:</strong> {{ $f->orden_compra }}
            @endif
            @if(!empty($f->observaciones))
            <br><strong>Observaciones:</strong><br>{!! nl2br(e($f->observaciones)) !!}
            @endif
        </div>
    </td>
    <td width="50%" valign="top">
        <div class="info-box" style="font-size:6.5pt; line-height:1.2; padding:2px 6px; margin-bottom:2px;">
            <div class="section-title" style="font-size:7pt; margin-bottom:1px; padding-bottom:1px;">DATOS DEL COMPROBANTE</div>
            <strong>Serie / Folio:</strong> {{ $f->serie ?? '' }} {{ $f->folio }}<br>
            <strong>Fecha y hora de expedición:</strong> {{ $fechaExpedicionMostrar ? $fechaExpedicionMostrar->format('d/m/Y H:i:s') : '-' }}<br>
            <strong>Lugar de expedición:</strong> {{ $f->lugar_expedicion ?? $e->codigo_postal ?? '-' }}<br>
            <strong>Forma de pago:</strong> {{ $formaPagoEtiqueta }}<br>
            <strong>Método de pago:</strong> {{ $metodoPagoEtiqueta }}<br>
            <strong>Moneda:</strong> {{ $f->moneda ?? 'MXN' }}{{ $f->moneda !== 'MXN' ? ' (T.C. ' . number_format((float)($f->tipo_cambio ?? 1), 4, '.', ',') . ')' : '' }}<br>
            <strong>Tipo de comprobante:</strong> {{ $f->tipo_comprobante === 'I' ? 'Ingreso' : ($f->tipo_comprobante === 'E' ? 'Egreso' : $f->tipo_comprobante) }}<br>
            <strong>Versión:</strong> CFDI 4.0<br>
            @if($f->uuid)
            <strong>Folio fiscal (UUID):</strong> {{ $f->uuid }}<br>
            @endif
            @if($e->no_certificado ?? null)
            <strong>No. de serie del certificado del emisor:</strong> {{ $e->no_certificado }}<br>
            @endif
            @if($fechaCertificacionMostrar)
            <strong>Fecha y hora de certificación:</strong> {{ $fechaCertificacionMostrar->format('d/m/Y H:i:s') }}<br>
            @endif
            @if($f->no_certificado_sat)
            <strong>No. de serie del certificado del SAT:</strong> {{ $f->no_certificado_sat }}<br>
            @endif
            @if(!empty($f->uuid_referencia))
            @php
                $tipoRelacionDesc = match($f->tipo_relacion ?? '01') {
                    '01' => 'Nota de crédito de los documentos relacionados',
                    '02' => 'Nota de débito de los documentos relacionados',
                    '03' => 'Devolución de mercancía sobre facturas o traslados previos',
                    '04' => 'Sustitución de los CFDI previos',
                    '05' => 'Traslados de mercancías facturados previamente',
                    '06' => 'Factura generada por los traslados previos',
                    '07' => 'CFDI por aplicación de anticipo',
                    default => 'Nota de crédito de los documentos relacionados',
                };
            @endphp
            <strong>Tipo de relación:</strong> {{ $f->tipo_relacion ?? '01' }} - {{ $tipoRelacionDesc }}<br>
            <strong>Factura que se acredita (UUID):</strong> {{ $f->uuid_referencia }}<br>
            @endif
        </div>
    </td>
</tr>
</table>

{{-- CONCEPTOS (tabla con impuestos) --}}
<table class="productos-table">
<thead>
<tr>
    <th>Clave</th>
    <th>Cant.</th>
    <th>Unidad</th>
    <th>Descripción</th>
    <th class="right">Valor unit.</th>
    <th class="right">Importe</th>
    <th class="right">Descuento</th>
    <th class="right">Impuestos</th>
    <th class="right">Total</th>
</tr>
</thead>
<tbody>
@foreach($f->detalles ?? [] as $d)
@php
    $impuestosLinea = 0;
    foreach ($d->impuestos ?? [] as $imp) {
        $impuestosLinea += (float) $imp->importe;
    }
    $totalLinea = (float) $d->importe - (float) ($d->descuento ?? 0) + $impuestosLinea;
@endphp
<tr>
    <td>{{ $d->clave_prod_serv ?? '-' }}</td>
    <td class="center">{{ number_format($d->cantidad, 4, '.', ',') }}</td>
    <td>{{ $d->unidad ?? $d->clave_unidad ?? 'Pieza' }}</td>
    <td>{{ $d->descripcion }}</td>
    <td class="right">{{ formatMoney($d->valor_unitario) }}</td>
    <td class="right">{{ formatMoney($d->importe) }}</td>
    <td class="right">{{ formatMoney($d->descuento ?? 0) }}</td>
    <td class="right">{{ formatMoney($impuestosLinea) }}</td>
    <td class="right">{{ formatMoney($totalLinea) }}</td>
</tr>
@endforeach
</tbody>
</table>

{{-- TOTALES CFDI --}}
<table class="totales-table">
<tr><td>Subtotal:</td><td>{{ formatMoney($f->subtotal) }}</td></tr>
@if((float)($f->descuento ?? 0) > 0)
<tr><td>Descuento:</td><td style="color:#DC2626;">-{{ formatMoney($f->descuento) }}</td></tr>
@endif
@foreach($impuestosPorTasa as $datos)
@if($datos['importe'] > 0)
<tr><td>{{ $datos['nombre'] }}:</td><td>{{ formatMoney($datos['importe']) }}</td></tr>
@endif
@endforeach
@if(empty($impuestosPorTasa) && $totalIva > 0)
<tr><td>IVA (traslados):</td><td>{{ formatMoney($totalIva) }}</td></tr>
@endif
@if($totalRetenciones != 0)
<tr><td>ISR Retenido:</td><td>-{{ formatMoney($totalRetenciones) }}</td></tr>
@endif
<tr class="total-final">
    <td>Total:</td>
    <td>{{ formatMoney($f->total) }} {{ $f->moneda ?? 'MXN' }}</td>
</tr>
<tr>
    <td colspan="2" style="font-size:7pt; padding-top:4px; font-style:italic;">{{ importeEnLetra((float)($f->total ?? 0)) }}</td>
</tr>
</table>

{{-- Aviso de cancelación --}}
@if(($f->estado ?? '') === 'cancelada')
<div style="margin-top:4px; padding:4px; border:2px solid #DC2626; background:#FEE2E2; text-align:center;">
    <strong style="color:#DC2626; font-size:10pt;">ESTE COMPROBANTE HA SIDO CANCELADO</strong>
    @if($f->fecha_cancelacion)
    <br><span style="font-size:8pt;">Fecha de cancelación: {{ \Carbon\Carbon::parse($f->fecha_cancelacion)->format('d/m/Y H:i') }}</span>
    @endif
</div>
@endif

{{-- Sellos, cadena original y QR (85% textos, 15% QR) --}}
<div class="timbrado-section" style="margin-top:4px;">
    @if($f->uuid)
        @php
            // Fix: fallback a $e->rfc si $f->rfc_emisor está vacío
            $rfcEmisorQr = $f->rfc_emisor ?: ($e->rfc ?? null);
            if (!$qrVerificacionDataUri && $f->uuid && $rfcEmisorQr && $f->rfc_receptor) {
                $urlVerificacion = urlVerificacionSat($f->uuid, $rfcEmisorQr, $f->rfc_receptor, (float) $f->total, $f->sello_cfdi ?? null);
                $qrVerificacionDataUri = qrCodeDataUri($urlVerificacion, 110);
            }
        @endphp
        {{-- table-layout:fixed es CLAVE para que DomPDF respete los anchos y no desborde --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="table-layout:fixed;">
        <tr>
            @if($qrVerificacionDataUri)
            <td width="15%" valign="top" style="padding-right:8px; text-align:center;">
                <img src="{{ $qrVerificacionDataUri }}"
                    style="width:110px; height:110px; display:block; margin:0 auto;"
                    alt="QR Verificación SAT">
            </td>
            @endif
            <td width="{{ $qrVerificacionDataUri ? '85%' : '100%' }}" valign="top"
                style="overflow:hidden; word-break:break-all; overflow-wrap:break-word;">
                <div class="timbrado-label">Sello digital del CFDI:</div>
                <div class="timbrado-value"
                     style="font-size:5pt; word-break:break-all; overflow-wrap:break-word; white-space:normal;">
                    {{ $f->sello_cfdi ?: '—' }}
                </div>
                <div class="timbrado-label" style="margin-top:2px;">Sello del SAT:</div>
                <div class="timbrado-value"
                     style="font-size:5pt; word-break:break-all; overflow-wrap:break-word; white-space:normal;">
                    {{ $f->sello_sat ?: '—' }}
                </div>
                <div class="timbrado-label" style="margin-top:2px;">
                    Cadena original del complemento de certificación digital del SAT:
                </div>
                <div class="timbrado-value"
                     style="font-size:5pt; word-break:break-all; overflow-wrap:break-word; white-space:normal;">
                    {{ $f->cadena_original ?: '—' }}
                </div>
            </td>
        </tr>
        </table>
    @else
        <div style="color:#B45309; font-weight:bold;">DOCUMENTO EN BORRADOR</div>
    @endif
</div>

{{-- Leyenda obligatoria SAT --}}
<div style="margin-top:4px; font-size:8pt; text-align:center; color:#374151;">
    @if(($f->estado ?? '') === 'cancelada')
        <strong style="color:#DC2626;">Este comprobante fue cancelado y no tiene efectos fiscales.</strong>
    @elseif($f->uuid)
        <strong>Este documento es una representación impresa de un Comprobante Fiscal Digital por Internet (CFDI 4.0).</strong>
    @else
        <strong>Documento en borrador. No es válido como comprobante fiscal hasta su timbrado.</strong>
    @endif
</div>
