<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 14mm 16mm; size: letter; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10pt; color: #1F2937; }
h1 { font-size: 14pt; color: #0B3C5D; margin: 0 0 4px; }
.muted { color: #6B7280; font-size: 8.5pt; }
.box { border: 1px solid #D1D5DB; padding: 10px 12px; margin: 10px 0; }
.row { margin: 4px 0; }
.label { color: #6B7280; font-size: 8pt; }
.value { font-size: 10pt; }
.mono { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; word-break: break-all; }
.banner { border: 2px solid #DC2626; color: #DC2626; text-align: center; padding: 8px; font-weight: bold; margin: 12px 0; }
</style>
</head>
<body>
    <h1>Acuse de cancelación de CFDI</h1>
    <div class="muted">{{ $empresa->nombre ?? $empresa->razon_social ?? 'Promafi' }} · Representación impresa del acuse de cancelación</div>

    <div class="banner">CFDI CANCELADO</div>

    <div class="box">
        <div class="row"><div class="label">Folio interno</div><div class="value">{{ $doc->folio_completo ?? ($doc->serie.'-'.$doc->folio) }}</div></div>
        <div class="row"><div class="label">UUID</div><div class="value mono">{{ $datos['uuid'] ?? $doc->uuid }}</div></div>
        <div class="row"><div class="label">RFC emisor</div><div class="value">{{ $datos['rfc_emisor'] ?? $doc->rfc_emisor }}</div></div>
        <div class="row"><div class="label">RFC receptor</div><div class="value">{{ $doc->rfc_receptor }}</div></div>
        @if(!empty($datos['fecha']))
        <div class="row"><div class="label">Fecha del acuse</div><div class="value">{{ $datos['fecha'] }}</div></div>
        @endif
        @if($doc->fecha_solicitud_cancelacion ?? null)
        <div class="row"><div class="label">Solicitud enviada</div><div class="value">{{ $doc->fecha_solicitud_cancelacion->format('d/m/Y H:i') }}</div></div>
        @endif
        <div class="row"><div class="label">Estado de cancelación</div><div class="value">{{ \App\Services\EstatusCancelacionCfdi::estatusSatParaUsuario($doc->estatus_cancelacion_pac, $doc->estatus_sat, $doc->codigo_estatus_cancelacion) }}</div></div>
        @if(!empty($datos['codigo']) || !empty($doc->codigo_estatus_cancelacion))
        <div class="row"><div class="label">Código de estatus</div><div class="value">{{ $datos['codigo'] ?? $doc->codigo_estatus_cancelacion }} — {{ \App\Services\EstatusCancelacionCfdi::descripcionCodigo($datos['codigo'] ?? $doc->codigo_estatus_cancelacion) }}</div></div>
        @endif
        @if($doc->motivo_cancelacion ?? null)
        <div class="row"><div class="label">Motivo de cancelación</div><div class="value">{{ \App\Services\EstatusCancelacionCfdi::descripcionMotivoSat($doc->motivo_cancelacion) }}</div></div>
        @endif
        @if($doc->uuid_sustitucion_cancelacion ?? null)
        <div class="row"><div class="label">UUID que sustituye</div><div class="value mono">{{ $doc->uuid_sustitucion_cancelacion }}</div></div>
        @endif
    </div>

    <p class="muted">Documento generado a partir del acuse XML recibido durante el proceso de cancelación. El archivo XML permanece disponible como respaldo del expediente fiscal.</p>
</body>
</html>
