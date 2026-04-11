@extends('layouts.app')

@section('title', 'Reporte de utilidad')
@section('page-title', '📈 Reporte de utilidad')
@section('page-subtitle', 'Ingresos, costos y utilidad por ventas (facturado o cobrado)')

@php
$breadcrumbs = [
    ['title' => 'Reportes', 'url' => route('reportes.fiscal')],
    ['title' => 'Utilidad']
];
@endphp

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title">Filtros</div>
    </div>
    <div class="card-body">
        <form id="formFiltrosUtilidad" method="GET" action="{{ route('reportes.utilidad') }}" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 16px; align-items: end;">
            <div class="form-group">
                <label class="form-label">📅 Fecha desde</label>
                <input type="date" name="fecha_desde" class="form-control" value="{{ $fechaDesde ?? '' }}">
            </div>
            <div class="form-group">
                <label class="form-label">📅 Fecha hasta</label>
                <input type="date" name="fecha_hasta" class="form-control" value="{{ $fechaHasta ?? '' }}">
            </div>
            <div class="form-group">
                <label class="form-label">👤 Cliente</label>
                <select name="cliente_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($clientes ?? [] as $c)
                        <option value="{{ $c->id }}" {{ ($clienteId ?? '') == $c->id ? 'selected' : '' }}>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">📦 Producto</label>
                <select name="producto_id" class="form-control">
                    <option value="">Todos</option>
                    @foreach($productos ?? [] as $p)
                        <option value="{{ $p->id }}" {{ ($productoId ?? '') == $p->id ? 'selected' : '' }}>{{ $p->codigo ? $p->codigo . ' - ' : '' }}{{ Str::limit($p->nombre, 40) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">🧾 Factura</label>
                <select name="factura_id" class="form-control">
                    <option value="">Todas</option>
                    @foreach($facturas ?? [] as $f)
                        <option value="{{ $f->id }}" {{ ($facturaId ?? '') == $f->id ? 'selected' : '' }}>
                            {{ $f->serie }}-{{ str_pad($f->folio, 4, '0', STR_PAD_LEFT) }} ({{ $f->fecha_emision->format('d/m/Y') }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">📊 Tipo de utilidad</label>
                <select name="modo" class="form-control">
                    <option value="facturado" {{ ($modoUtilidad ?? 'facturado') === 'facturado' ? 'selected' : '' }}>Facturado</option>
                    <option value="cobrado" {{ ($modoUtilidad ?? '') === 'cobrado' ? 'selected' : '' }}>Cobrado (pagos / complementos)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">💳 Complemento de pago <span class="text-muted small">(opcional)</span></label>
                <select name="complemento_pago_id" class="form-control">
                    <option value="">Todos los pagos del período (acumulado)</option>
                    @foreach($complementosPago ?? [] as $cp)
                        <option value="{{ $cp->id }}" {{ (string)($complementoPagoId ?? '') === (string)$cp->id ? 'selected' : '' }}>
                            {{ $cp->folio_completo }}
                            @if($cp->fecha_timbrado)
                                — {{ $cp->fecha_timbrado->format('d/m/Y') }}
                            @endif
                            — ${{ number_format((float)$cp->monto_total, 2, '.', ',') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" style="visibility: hidden; user-select: none;" aria-hidden="true">Acciones</label>
                <div style="display: flex; gap: 10px; flex-wrap: nowrap; align-items: center;">
                    <button type="submit" class="btn btn-primary">Filtrar</button>
                    <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('modalExportUtilidad').classList.add('show')">Exportar</button>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Resumen</div>
    </div>
    <div class="card-body">
        <table class="table" style="max-width: 520px;">
            <tr>
                <td>
                    <strong>{{ ($modoUtilidad ?? 'facturado') === 'cobrado' ? 'Total cobrado (proporcional)' : 'Total facturado' }}</strong>
                    <span class="text-muted small">(suma monto total venta por línea)</span>
                </td>
                <td class="text-end text-mono">${{ number_format($totalFacturado ?? (($totalIngreso ?? 0) + ($totalIvaXPagar ?? 0) + ($totalIsrReten ?? 0)), 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Subtotal</strong></td>
                <td class="text-end text-mono">${{ number_format($totalIngreso ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>IVA trasladado (línea)</strong></td>
                <td class="text-end text-mono">${{ number_format($totalIvaXPagar ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Total IVA</strong> <span class="text-muted small">(Imp. IVA acred. 16%)</span></td>
                <td class="text-end text-mono">${{ number_format($totalIvaAcreditable ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Total costos</strong></td>
                <td class="text-end text-mono">${{ number_format($totalCosto ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Total monto venta</strong></td>
                <td class="text-end text-mono">${{ number_format($totalMontoVenta ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>ISR retenido (línea)</strong></td>
                <td class="text-end text-mono" style="color: {{ ($totalIsrReten ?? 0) <= 0 ? 'var(--color-danger)' : 'inherit' }};">${{ number_format($totalIsrReten ?? 0, 2, '.', ',') }}</td>
            </tr>
            <tr>
                <td><strong>Total ganancia</strong></td>
                <td class="text-end text-mono fw-600" style="color: {{ ($totalGanancia ?? 0) >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                    ${{ number_format($totalGanancia ?? 0, 2, '.', ',') }}
                </td>
            </tr>
            <tr>
                <td><strong>Margen %</strong></td>
                <td class="text-end text-mono">{{ number_format($margen ?? 0, 2) }}%</td>
            </tr>
        </table>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <div class="card-title">Detalle</div>
    </div>
    <div class="card-body" style="padding: 0;">
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Pedido</th>
                        <th>Factura</th>
                        <th>Fecha factura</th>
                        <th>Cliente</th>
                        <th>Producto / Concepto</th>
                        <th class="td-right">Costo unit.</th>
                        <th class="td-right">Venta unit.</th>
                        <th class="td-right">Margen %</th>
                        <th class="td-right">Utilidad unit.</th>
                        <th class="td-center">Cant.</th>
                        <th class="td-right">Costo</th>
                        <th class="td-right">Imp. IVA acred. (16%)</th>
                        <th class="td-right">Total costo c/IVA</th>
                        <th class="td-right">Venta</th>
                        <th class="td-right">IVA trasladado</th>
                        <th class="td-right">ISR retenido</th>
                        <th class="td-right">Monto total Venta</th>
                        <th class="td-right">Ganancia</th>
                        <th class="td-center">Entregado</th>
                        <th class="td-center">Pagada</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($filas ?? [] as $fila)
                    <tr>
                        <td class="text-mono" style="font-size: 12px;">{{ $fila['detalle']->factura->orden_compra ? Str::limit($fila['detalle']->factura->orden_compra, 32) : '—' }}</td>
                        <td>
                            <a href="{{ route('facturas.show', $fila['detalle']->factura_id) }}" class="text-mono" style="color: var(--color-primary);">
                                {{ $fila['detalle']->factura->folio_completo ?? $fila['detalle']->factura->serie . '-' . $fila['detalle']->factura->folio }}
                            </a>
                        </td>
                        <td>{{ $fila['detalle']->factura->fecha_emision->format('d/m/Y') }}</td>
                        <td>{{ optional($fila['detalle']->factura->cliente)->nombre ?? $fila['detalle']->factura->nombre_receptor ?? '—' }}</td>
                        <td>
                            @if($fila['detalle']->producto)
                                {{ $fila['detalle']->producto->codigo ? $fila['detalle']->producto->codigo . ' - ' : '' }}{{ Str::limit($fila['detalle']->descripcion ?? $fila['detalle']->producto->nombre, 35) }}
                            @else
                                {{ Str::limit($fila['detalle']->descripcion ?? 'Concepto', 35) }}
                            @endif
                        </td>
                        <td class="td-right text-mono">${{ number_format($fila['costo_unitario'] ?? 0, 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['ingreso_unitario'] ?? 0, 2, '.', ',') }}</td>
                        <td class="td-right text-mono">{{ number_format($fila['margen_pct'] ?? 0, 2) }}%</td>
                        <td class="td-right text-mono fw-600" style="color: {{ ($fila['utilidad_unitaria'] ?? 0) >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                            ${{ number_format($fila['utilidad_unitaria'] ?? 0, 2, '.', ',') }}
                        </td>
                        <td class="td-center text-mono">{{ number_format($fila['detalle']->cantidad, 2) }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['costo'], 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['iva_acreditable'] ?? 0, 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['costo_con_iva'] ?? 0, 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['ingreso'], 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['iva_x_pagar'] ?? 0, 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['isr_reten'] ?? 0, 2, '.', ',') }}</td>
                        <td class="td-right text-mono">${{ number_format($fila['monto_total_venta'] ?? 0, 2, '.', ',') }}</td>
                        <td class="td-right text-mono fw-600" style="color: {{ ($fila['ganancia'] ?? 0) >= 0 ? 'var(--color-success)' : 'var(--color-danger)' }};">
                            ${{ number_format($fila['ganancia'] ?? 0, 2, '.', ',') }}
                        </td>
                        <td class="td-center">{{ $fila['entregado_destino'] ?? '—' }}</td>
                        <td class="td-center">{{ $fila['pagada'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="20" class="text-center text-muted" style="padding: 40px;">No hay datos con los filtros aplicados.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<p class="text-muted small mt-2">
    <strong>Nota:</strong> En facturas timbradas tras esta mejora, el <strong>costo unitario queda guardado en la línea al timbrar</strong> (mismo criterio que antes: costo del producto o costo promedio). Facturas antiguas sin ese dato siguen usando el catálogo actual. Conceptos sin producto: costo 0 al timbrar.
    <strong>IVA x pagar e ISR</strong> provienen de <strong>facturas_impuestos</strong> (timbrado), no de tasas fijas en el reporte.
    @if(($modoUtilidad ?? 'facturado') === 'cobrado')
        <strong>Modo cobrado:</strong> cada línea se escala con <code>monto pagado ÷ total factura</code> (complementos timbrados; sin complemento elegido se acumulan pagos por <code>fecha_timbrado</code> del complemento). Las facturas <strong>PUE</strong> en el período cuentan como cobro 100% al timbrar. <strong>Ganancia</strong> = monto total venta proporcional − costo c/IVA proporcional.
    @else
        <strong>Venta unit.</strong> es el precio unitario de venta de la línea (base gravable / cantidad). <strong>IVA acreditable:</strong> 16% sobre costo; <strong>total costo c/IVA</strong> = costo + IVA acreditable.
        <strong>Utilidad unit.</strong> = Venta unit. − Costo unit. <strong>Margen %</strong> = (Venta unit. − Costo unit.) ÷ Venta unit. × 100 (si venta unit. es 0, margen 0%).
        <strong>Venta:</strong> base gravable de la línea. <strong>Monto total Venta</strong> según CFDI (base + traslados − retenciones). <strong>Ganancia</strong> = monto total Venta − total costo c/IVA.
    @endif
</p>

<div id="modalExportUtilidad" class="modal">
    <div class="modal-box" style="max-width: 420px;">
        <div class="modal-header">
            <div class="modal-title">Exportar reporte</div>
            <button type="button" class="modal-close" onclick="document.getElementById('modalExportUtilidad').classList.remove('show')" aria-label="Cerrar">✕</button>
        </div>
        <form id="formExportUtilidad" method="GET" action="{{ route('reportes.utilidad.export') }}">
            <input type="hidden" name="fecha_desde" value="">
            <input type="hidden" name="fecha_hasta" value="">
            <input type="hidden" name="cliente_id" value="">
            <input type="hidden" name="producto_id" value="">
            <input type="hidden" name="factura_id" value="">
            <input type="hidden" name="modo" value="">
            <input type="hidden" name="complemento_pago_id" value="">
            <div class="modal-body">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label">Formato</label>
                    <select name="formato" id="exportUtilidadFormato" class="form-control" required>
                        <option value="pdf">PDF</option>
                        <option value="xlsx">Excel</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="document.getElementById('modalExportUtilidad').classList.remove('show')">Cancelar</button>
                <button type="submit" class="btn btn-primary">Descargar</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    var formFiltros = document.getElementById('formFiltrosUtilidad');
    var formExport = document.getElementById('formExportUtilidad');
    if (!formFiltros || !formExport) return;

    function val(name) {
        var el = formFiltros.querySelector('[name="' + name + '"]');
        return el ? el.value : '';
    }

    formExport.addEventListener('submit', function () {
        formExport.querySelector('[name="fecha_desde"]').value = val('fecha_desde');
        formExport.querySelector('[name="fecha_hasta"]').value = val('fecha_hasta');
        formExport.querySelector('[name="cliente_id"]').value = val('cliente_id');
        formExport.querySelector('[name="producto_id"]').value = val('producto_id');
        formExport.querySelector('[name="factura_id"]').value = val('factura_id');
        formExport.querySelector('[name="modo"]').value = val('modo');
        formExport.querySelector('[name="complemento_pago_id"]').value = val('complemento_pago_id');
    });
})();
</script>
@endpush

@endsection
