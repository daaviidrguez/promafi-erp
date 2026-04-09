<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 10mm 12mm; size: letter; }
body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9pt; color: #1F2937; margin: 0; }
.section-title { font-size: 10pt; font-weight: bold; border-bottom: 2px solid #0B3C5D; margin: 12px 0 6px; padding-bottom: 2px; }
.box { border: 1px solid #E5E7EB; padding: 8px 10px; margin-bottom: 8px; font-size: 9pt; line-height: 1.35; }
table.items { width: 100%; border-collapse: collapse; margin-top: 6px; }
table.items th { background: #0B3C5D; color: #fff; text-align: left; padding: 5px 8px; font-size: 8.5pt; }
table.items td { border-bottom: 1px solid #E5E7EB; padding: 5px 8px; font-size: 8.5pt; }
td.right { text-align: right; }
td.center { text-align: center; }
.firma-grid { display: table; width: 100%; margin-top: 28px; }
.firma-cell { display: table-cell; width: 50%; vertical-align: bottom; padding: 0 12px; }
.linea-firma { border-top: 1px solid #111; margin-top: 40px; padding-top: 4px; font-size: 8.5pt; text-align: center; }
.small { font-size: 8pt; color: #6B7280; }
</style>
</head>
<body>

@include('pdf.partials.header-empresa-logo', [
    'empresa' => $empresa ?? null,
    'titulo' => 'Comprobante de envío / entrega',
])
<div class="sub" style="font-size: 9pt; color: #6B7280; margin-bottom: 10px;">
    Folio logística: <strong>{{ $envio->folio }}</strong>
</div>

<div class="section-title">Envío</div>
<div class="box">
    <strong>Estado:</strong> {{ $envio->estado_etiqueta }}<br>
    @if($envio->factura)
        <strong>Factura:</strong> {{ $envio->factura->folio_completo }}<br>
    @endif
    @if($envio->remision)
        <strong>Remisión:</strong> {{ $envio->remision->folio }}<br>
    @endif
    <strong>Cliente:</strong> {{ $envio->cliente->nombre ?? '—' }}<br>
    @if($envio->chofer)<strong>Chofer:</strong> {{ $envio->chofer }}<br>@endif
    @if($envio->recibido_almacen)<strong>Recibió (almacén):</strong> {{ $envio->recibido_almacen }}<br>@endif
    @if($envio->lugar_entrega)<strong>Lugar de entrega:</strong> {{ $envio->lugar_entrega }}<br>@endif
</div>

<div class="section-title">Dirección de entrega</div>
<div class="box" style="white-space:pre-wrap;">{{ $envio->direccion_entrega ?: '—' }}</div>

<div class="section-title">Mercancía</div>
<table class="items">
    <thead>
        <tr>
            <th>#</th>
            <th>Descripción</th>
            <th class="center">Cant.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($envio->items as $i => $it)
        <tr>
            <td class="center">{{ $i + 1 }}</td>
            <td>{{ $it->descripcion }}</td>
            <td class="right text-mono">{{ $it->cantidad }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@if($envio->notas)
<div class="section-title">Notas</div>
<div class="box" style="white-space:pre-wrap;">{{ $envio->notas }}</div>
@endif

<div class="firma-grid">
    <div class="firma-cell">
        <div class="linea-firma">
            Entrega<br>
            <span class="small">{{ $empresa->nombre_comercial ?? $empresa->razon_social ?? '' }}</span>
        </div>
    </div>
    <div class="firma-cell">
        <div class="linea-firma">
            Recibe conforme<br>
            <span class="small">
                @if($envio->entrega_recibido_por)
                    Nombre: {{ $envio->entrega_recibido_por }}
                @else
                    Nombre y firma
                @endif
            </span>
        </div>
    </div>
</div>

<p class="small" style="margin-top:16px;">
    Documento generado {{ now()->format('d/m/Y H:i') }} — Uso interno de trazabilidad logística.
</p>

</body>
</html>
