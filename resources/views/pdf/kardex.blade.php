<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Kardex {{ $producto->codigo ?? '' }}</title>
<style>
@page { margin: 10mm; size: letter landscape; }
body { font-family: Arial, sans-serif; font-size: 8pt; color: #1F2937; margin: 0; padding: 8px; }
.header { border-bottom: 2px solid #0B3C5D; padding-bottom: 4px; margin-bottom: 8px; }
.header h1 { margin: 0; font-size: 12pt; }
.header .sub { font-size: 9pt; color: #6B7280; margin-top: 2px; }
.kardex-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
.kardex-table th { background: #0B3C5D; color: white; padding: 4px 6px; text-align: left; font-size: 7.5pt; }
.kardex-table th.td-right { text-align: right; }
.kardex-table td { padding: 3px 6px; border-bottom: 1px solid #E5E7EB; }
.kardex-table td.td-right { text-align: right; }
.kardex-table tr.saldo-inicial { background: #F3F4F6; font-weight: bold; }
.kardex-table tbody tr:nth-child(even) { background: #F9FAFB; }
</style>
</head>
<body>

<div class="header">
    <h1>Kardex de inventario</h1>
    <div class="sub">
        @if($empresa)
            {{ $empresa->nombre_comercial ?? $empresa->razon_social ?? 'Empresa' }}
        @endif
    </div>
    <div class="sub">
        Producto: <strong>{{ $producto->codigo ?? '' }}</strong> — {{ $producto->nombre ?? '' }}
        &nbsp;|&nbsp;
        Del {{ $fechaDesde->format('d/m/Y') }} al {{ $fechaHasta->format('d/m/Y') }}
    </div>
    <div class="sub producto-datos" style="margin-top: 6px;">
        Existencia: <strong>{{ number_format((float)($producto->stock ?? 0), 2, '.', ',') }}</strong>
        &nbsp;|&nbsp; Stock mínimo: {{ number_format((float)($producto->stock_minimo ?? 0), 2, '.', ',') }}
        &nbsp;|&nbsp; Unidad: {{ $producto->unidad ?? '—' }}
        &nbsp;|&nbsp; Costo promedio: ${{ number_format((float)($producto->costo_promedio ?? $producto->costo ?? 0), 2, '.', ',') }}
        &nbsp;|&nbsp; Costo: ${{ number_format((float)($producto->costo ?? 0), 2, '.', ',') }}
    </div>
</div>

<table class="kardex-table">
    <thead>
        <tr>
            <th>Fecha</th>
            <th>Tipo / Referencia</th>
            <th class="td-right">Entrada</th>
            <th class="td-right">Salida</th>
            <th class="td-right">Saldo</th>
        </tr>
    </thead>
    <tbody>
        <tr class="saldo-inicial">
            <td colspan="2">Saldo inicial</td>
            <td class="td-right">—</td>
            <td class="td-right">—</td>
            <td class="td-right">{{ number_format($saldoInicial, 2, '.', ',') }}</td>
        </tr>
        @foreach($movimientos as $m)
        <tr>
            <td>{{ $m->created_at->format('d/m/Y H:i') }}</td>
            <td>{{ $m->etiqueta_tipo }}{{ $m->folio ? ' — ' . $m->folio : '' }}{{ $m->observaciones ? ' ' . Str::limit($m->observaciones, 35) : '' }}</td>
            <td class="td-right">
                @if(\App\Models\InventarioMovimiento::esEntrada($m->tipo))
                    {{ number_format($m->cantidad, 2, '.', ',') }}
                @else
                    —
                @endif
            </td>
            <td class="td-right">
                @if(!\App\Models\InventarioMovimiento::esEntrada($m->tipo))
                    {{ number_format($m->cantidad, 2, '.', ',') }}
                @else
                    —
                @endif
            </td>
            <td class="td-right">{{ number_format($m->stock_resultante, 2, '.', ',') }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

@if($movimientos->isEmpty())
<p style="margin-top:12px; color:#6B7280;">No hay movimientos en el rango de fechas.</p>
@endif

</body>
</html>
