<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<style>
@page { margin: 15mm 20mm 25mm 20mm; size: letter; }
body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #1F2937; }
.header { border-bottom: 2px solid #0B3C5D; padding-bottom: 10px; margin-bottom: 15px; display: table; width: 100%; }
.header-left { display: table-cell; vertical-align: middle; width: 70%; }
.header-right { display: table-cell; vertical-align: middle; width: 30%; text-align: right; }
.nombre-comercial { font-size: 14pt; font-weight: bold; color: #0B3C5D; }
.titulo-lista { font-size: 11pt; font-weight: bold; margin-top: 8px; }
.productos-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
.productos-table thead { background: #0B3C5D; color: white; }
.productos-table th, .productos-table td { padding: 6px 10px; font-size: 8pt; border-bottom: 1px solid #E5E7EB; }
.productos-table th { text-align: left; }
.productos-table th.center, .productos-table td.center { text-align: center; }
.productos-table th.right, .productos-table td.right { text-align: right; font-variant-numeric: tabular-nums; }
.footer-num { font-size: 8pt; color: #6B7280; text-align: center; margin-top: 15px; }
</style>
</head>
<body>

@php
    $logoDataUri = null;
    $empresa = \App\Models\Empresa::principal() ?? (object)['nombre_comercial'=>'','razon_social'=>'EMPRESA','logo_path'=>null];
    if (($empresa->logo_path ?? null)) {
        $logoPath = storage_path('app/public/' . $empresa->logo_path);
        if (!file_exists($logoPath)) { $logoPath = public_path('storage/' . $empresa->logo_path); }
        if ($logoPath && file_exists($logoPath)) {
            $logoDataUri = 'data:' . mime_content_type($logoPath) . ';base64,' . base64_encode(file_get_contents($logoPath));
        }
    }
@endphp

<div class="header">
    <div class="header-left">
        <div class="nombre-comercial">{{ strtoupper($empresa->nombre_comercial ?? $empresa->razon_social ?? 'EMPRESA') }}</div>
        <div class="titulo-lista">Lista de Precios: {{ $listaPrecio->nombre }}</div>
    </div>
    <div class="header-right">
        @if($logoDataUri)
        <img src="{{ $logoDataUri }}" style="max-height:50px; max-width:120px;">
        @endif
    </div>
</div>

<table class="productos-table">
    <thead>
        <tr>
            <th style="width:35%;">Producto</th>
            <th class="right" style="width:15%;">Costo</th>
            <th class="center" style="width:18%;">Tipo utilidad</th>
            <th class="right" style="width:12%;">Valor %</th>
            <th class="right" style="width:20%;">Precio</th>
        </tr>
    </thead>
    <tbody>
        @forelse($listaPrecio->detalles->where('activo', true) as $d)
        @php $p = $d->producto; @endphp
        @if($p)
        <tr>
            <td>
                <div style="font-weight:bold;">{{ $p->nombre }}</div>
                <span style="font-size:7pt; color:#6B7280;">{{ $p->codigo }}</span>
            </td>
            <td class="right">${{ number_format($p->costo_promedio_mostrar ?? $p->costo ?? 0, 2, '.', ',') }}</td>
            <td class="center">{{ $d->tipo_utilidad === 'factorizado' ? 'Markup' : 'Margen' }}</td>
            <td class="right">{{ number_format(max(1, min(99, (float)$d->valor_utilidad)), 0) }}%</td>
            <td class="right" style="font-weight:bold;">${{ number_format($d->precio_resultante, 2, '.', ',') }}</td>
        </tr>
        @endif
        @empty
        <tr><td colspan="5" style="text-align:center; padding:20px; color:#6B7280;">Sin productos</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer-num">
    <script type="text/php">
    if (isset($pdf)) {
        $pdf->page_text(520, 770, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 8, [0.4, 0.4, 0.4]);
    }
    </script>
</div>

</body>
</html>
