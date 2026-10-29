@extends('layouts.app')

@section('title', 'Cuentas por Cobrar')
@section('page-title', '💰 Cuentas por Cobrar')
@section('page-subtitle', 'Gestión de cobranza')

@php
$breadcrumbs = [
    ['title' => 'Cuentas por Cobrar']
];
@endphp

@section('content')

{{-- Stats de totales --}}
<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="stat-card stat-warning">
        <div class="stat-info-box">
            <div class="stat-label">Cartera total vigente</div>
            <div class="stat-value" style="font-size: 22px;">
                ${{ number_format($totales['pendiente'], 2, '.', ',') }}
            </div>
            <div class="stat-sub">Saldo abierto, incluye vencida</div>
        </div>
        <div class="stat-icon">⏳</div>
    </div>
    <div class="stat-card stat-danger">
        <div class="stat-info-box">
            <div class="stat-label">Cartera vencida</div>
            <div class="stat-value" style="font-size: 22px;">
                ${{ number_format($totales['vencido'], 2, '.', ',') }}
            </div>
            <div class="stat-sub">Saldo con fecha de vencimiento pasada</div>
        </div>
        <div class="stat-icon">⚠️</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box">
            <div class="stat-label">Total cobrado</div>
            <div class="stat-value" style="font-size: 22px;">
                ${{ number_format($totales['pagado'], 2, '.', ',') }}
            </div>
            <div class="stat-sub">{{ ucfirst($cobradoMes['mes_label']) }}</div>
        </div>
        <div class="stat-icon">✅</div>
    </div>
</div>

{{-- Total cobrado del mes --}}
@php
    $variacion = $cobradoMes['variacion_pct'];
    $variacionColor = $variacion === null
        ? 'var(--color-gray-500)'
        : ($variacion >= 0 ? 'var(--color-success)' : 'var(--color-warning)');
@endphp
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header cxc-panel-header">
        <form method="GET" action="{{ route('cuentas-cobrar.index') }}" class="cxc-periodo">
            @if($estado)<input type="hidden" name="estado" value="{{ $estado }}">@endif
            @if($cliente_id)<input type="hidden" name="cliente_id" value="{{ $cliente_id }}">@endif
            <label for="cxc_mes" class="cxc-periodo__label">Mes</label>
            <select id="cxc_mes" name="mes" class="form-control cxc-periodo__mes" onchange="this.form.submit()">
                @for ($m = 1; $m <= 12; $m++)
                    <option value="{{ $m }}" @selected($mes === $m)>
                        {{ \Illuminate\Support\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}
                    </option>
                @endfor
            </select>
            <label for="cxc_anio" class="cxc-periodo__label">Año</label>
            <input id="cxc_anio" type="number" name="anio" class="form-control cxc-periodo__anio"
                   value="{{ $anio }}" min="2020" max="2035" onchange="this.form.submit()">
        </form>
        <div class="cxc-panel-header__text">
            <div class="card-title">Total cobrado — {{ $cobradoMes['mes_label'] }}</div>
            <div class="cxc-panel-lead">Pagos aplicados en complementos de pago timbrados, por fecha de pago.</div>
        </div>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 20px;">
            <div class="stat-card" style="border-left-color: var(--color-success);">
                <div class="stat-info-box">
                    <div class="stat-label">Total cobrado</div>
                    <div class="stat-value" style="font-size: 18px; color: var(--color-success);">${{ number_format($cobradoMes['total_cobrado'], 2, '.', ',') }}</div>
                    <div class="stat-sub">{{ $cobradoMes['num_facturas'] }} fact.</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info-box">
                    <div class="stat-label">Total cobrado (sin IVA)</div>
                    <div class="stat-value" style="font-size: 18px;">${{ number_format($cobradoMes['total_sin_iva'], 2, '.', ',') }}</div>
                    <div class="stat-sub">Subtotal</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--color-info);">
                <div class="stat-info-box">
                    <div class="stat-label">Mes anterior</div>
                    <div class="stat-value" style="font-size: 18px;">${{ number_format($cobradoMes['total_cobrado_anterior'], 2, '.', ',') }}</div>
                    <div class="stat-sub">{{ $cobradoMes['mes_anterior_label'] }}</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: {{ $variacionColor }};">
                <div class="stat-info-box">
                    <div class="stat-label">Vs mes anterior</div>
                    <div class="stat-value" style="font-size: 18px; color: {{ $variacionColor }};">
                        @if ($variacion === null)
                            —
                        @else
                            {{ $variacion > 0 ? '+' : '' }}{{ number_format($variacion, 1) }}%
                        @endif
                    </div>
                    <div class="stat-sub">Total cobrado</div>
                </div>
            </div>
        </div>

        <div class="cxc-chart-title">Mes pagado vs mes anterior</div>
        <div style="position: relative; height: 240px;">
            <canvas id="chartCobradoComparativo"></canvas>
        </div>
    </div>
</div>

{{-- Cartera vencida por mes --}}
<div class="card" style="margin-bottom: 20px;">
    <div class="card-header">
        <div class="card-title">Cartera vencida por mes ({{ $vencidoPorMes['anio'] }})</div>
    </div>
    <div class="card-body">
        <p class="cxc-panel-lead" style="margin: 0 0 16px;">
            Saldo pendiente vencido agrupado por mes de vencimiento.
            Total: <strong>${{ number_format($vencidoPorMes['total'], 2) }}</strong>
            (cuadra con cartera vencida: ${{ number_format($totales['vencido'], 2) }}).
        </p>
        @if ($vencidoPorMes['total'] <= 0)
            <div class="empty-state" style="padding: 24px 20px;">
                <div class="empty-state-icon">✅</div>
                <div class="empty-state-title">No hay saldo vencido en este momento.</div>
            </div>
        @else
            <div class="cxc-vencido-grid">
                <div style="position: relative; height: 260px; min-width: 0;">
                    <canvas id="chartVencidoPorMes"></canvas>
                </div>
                <div style="position: relative; height: 260px; min-width: 0;">
                    <canvas id="chartVencidoPorMesPct"></canvas>
                </div>
                <div class="table-container" style="margin: 0; min-width: 0;">
                    <table>
                        <thead>
                            <tr>
                                <th>Mes</th>
                                <th class="td-right">Facturas</th>
                                <th class="td-right">Saldo</th>
                                <th class="td-right">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($vencidoPorMes['filas'] as $fila)
                                @if ($fila['monto'] > 0 || $fila['facturas'] > 0)
                                    <tr>
                                        <td>{{ $fila['mes'] }}</td>
                                        <td class="td-right">{{ $fila['facturas'] }}</td>
                                        <td class="td-right text-mono">${{ number_format($fila['monto'], 2) }}</td>
                                        <td class="td-right">{{ number_format($fila['porcentaje'], 1) }}%</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Filtros --}}
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('cuentas-cobrar.index') }}"
              style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <input type="hidden" name="anio" value="{{ $anio }}">
            @include('partials.cliente-search-field', [
                'required' => false,
                'allowEmpty' => true,
                'label' => '',
                'showLabel' => false,
                'compact' => true,
                'placeholder' => 'Buscar cliente...',
                'clienteIdValue' => $cliente_id ?? '',
                'clientes' => $clientes,
            ])
            <select name="estado" class="form-control" style="min-width: 180px;">
                <option value="todos" {{ $estado === 'todos' ? 'selected' : '' }}>Todos los estados</option>
                <option value="pendiente" {{ ($estado ?? '') == 'pendiente' ? 'selected' : '' }}>⏳ Pendiente</option>
                <option value="parcial"   {{ ($estado ?? '') == 'parcial'   ? 'selected' : '' }}>📊 Parcial</option>
                <option value="vencidas"  {{ ($estado ?? '') == 'vencidas'  ? 'selected' : '' }}>⚠️ Vencidas</option>
                <option value="pagada"    {{ ($estado ?? '') == 'pagada'    ? 'selected' : '' }}>✅ Pagadas</option>
                <option value="cancelada" {{ ($estado ?? '') == 'cancelada' ? 'selected' : '' }}>❌ Canceladas</option>
            </select>
            <button type="submit"
                    style="padding: 9px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">
                🔍 Filtrar
            </button>
            @if($estado !== 'vencidas' || ($cliente_id ?? false))
            <a href="{{ route('cuentas-cobrar.index', ['mes' => $mes, 'anio' => $anio]) }}"
               style="padding: 9px 16px; border: 1.5px solid var(--color-gray-300); border-radius: var(--radius-md); color: var(--color-gray-600); font-weight: 600;">
                ✕ Limpiar
            </a>
            @endif
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="table-container">
    @if($cuentas->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Factura</th>
                <th>Cliente</th>
                <th>Emisión</th>
                <th>Vencimiento</th>
                <th class="td-right">Monto Total</th>
                <th class="td-right">Pendiente</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cuentas as $cuenta)
            <tr style="{{ $cuenta->estado_display === 'vencida' ? 'background: rgba(239,68,68,0.04);' : '' }}">
                <td>
                    <a href="{{ route('facturas.show', [$cuenta->factura->id, 'from' => ($estado ?? '') === 'vencidas' ? 'cuentas-vencidas' : 'cuentas-cobrar']) }}"
                       class="text-mono fw-600" style="color: var(--color-primary);">
                        {{ $cuenta->factura->folio_completo }}
                    </a>
                </td>
                <td>
                    <div class="fw-600">{{ $cuenta->cliente->nombre }}</div>
                    @if($cuenta->cliente->telefono)
                        <div class="text-muted" style="font-size: 12px;">📱 {{ $cuenta->cliente->telefono }}</div>
                    @endif
                </td>
                <td>{{ $cuenta->fecha_emision->format('d/m/Y') }}</td>
                <td>
                    <div>{{ $cuenta->fecha_vencimiento->format('d/m/Y') }}</div>
                    @php $diff = $cuenta->dias_contra_vencimiento_realtime; @endphp
                    @if($diff !== null && $diff < 0)
                        <div style="font-size: 11px; font-weight: 600; color: var(--color-danger);">
                            ⚠️ {{ $diff }} días
                        </div>
                    @endif
                </td>
                <td class="td-right text-mono">
                    ${{ number_format($cuenta->monto_total, 2, '.', ',') }}
                </td>
                <td class="td-right text-mono fw-600"
                    style="color: {{ $cuenta->saldo_pendiente_real > 0 ? 'var(--color-warning)' : 'var(--color-success)' }};">
                    ${{ number_format($cuenta->saldo_pendiente_real, 2, '.', ',') }}
                </td>
                <td class="td-center">
                    @if($cuenta->estado_display === 'pagada')
                        <span class="badge badge-success">✅ Pagada</span>
                    @elseif($cuenta->estado_display === 'cancelada')
                        <span class="badge badge-gray">❌ Cancelada</span>
                    @elseif($cuenta->estado_display === 'vencida')
                        <span class="badge badge-danger">⚠️ Vencida</span>
                    @elseif($cuenta->estado_display === 'parcial')
                        <span class="badge badge-warning">📊 Parcial</span>
                    @else
                        <span class="badge badge-info">⏳ Pendiente</span>
                    @endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('cuentas-cobrar.show', $cuenta->id) }}"
                       class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $cuentas->withQueryString()->links() }}
    </div>
    @else
    <div class="empty-state">
        @if($estado === 'vencidas')
            <div class="empty-state-icon">✅</div>
            <div class="empty-state-title">No hay cuentas vencidas</div>
            <div class="empty-state-text">
                <a href="{{ route('cuentas-cobrar.index', array_filter(['estado' => 'todos', 'cliente_id' => $cliente_id, 'mes' => $mes, 'anio' => $anio])) }}"
                   style="color: var(--color-primary); font-weight: 600;">Ver todas las cuentas →</a>
            </div>
        @elseif($estado === 'todos' && !$cliente_id)
            <div class="empty-state-icon">💰</div>
            <div class="empty-state-title">No hay cuentas por cobrar</div>
            <div class="empty-state-text">Las facturas a crédito (PPD) aparecerán aquí automáticamente</div>
        @else
            <div class="empty-state-icon">💰</div>
            <div class="empty-state-title">No hay cuentas con este filtro</div>
            <div class="empty-state-text">Prueba con otro estado o cliente</div>
        @endif
    </div>
    @endif
</div>

@endsection

@push('styles')
<style>
.cxc-panel-header { flex-wrap: wrap; gap: 16px; justify-content: flex-start; }
.cxc-panel-header__text { flex: 1; min-width: 220px; }
.cxc-panel-lead { font-size: 13px; color: var(--color-gray-500); margin-top: 4px; }
.cxc-periodo { display: flex; align-items: center; gap: 8px; margin: 0; }
.cxc-periodo__label { font-size: 12px; color: var(--color-gray-500); margin: 0; }
.cxc-periodo__mes { min-width: 130px; padding: 6px 10px; }
.cxc-periodo__anio { width: 90px; padding: 6px 10px; }
.cxc-chart-title { font-size: 13px; font-weight: 600; color: var(--color-gray-600); margin-bottom: 8px; }
.cxc-vencido-grid { display: grid; grid-template-columns: 1.4fr 1fr 1.2fr; gap: 20px; align-items: center; }
@media (max-width: 1200px) {
    .cxc-vencido-grid { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
@include('partials.cliente-search-js')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    ClienteSearch.init({ required: false, allowEmpty: true });

    const fmtMoney = (v) => '$' + Number(v).toLocaleString('es-MX', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const accent = 'rgb(11, 60, 93)';
    const success = 'rgb(16, 185, 129)';

    const cmp = @json($cobradoMes['chart_comparativo']);
    new Chart(document.getElementById('chartCobradoComparativo'), {
        type: 'bar',
        data: {
            labels: cmp.labels,
            datasets: [
                { label: 'Total cobrado', data: cmp.total, backgroundColor: accent, borderRadius: 4, maxBarThickness: 48 },
                { label: 'Sin IVA', data: cmp.sin_iva, backgroundColor: success, borderRadius: 4, maxBarThickness: 48 },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 11 } } },
                tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + fmtMoney(ctx.raw) } },
            },
            scales: {
                y: { beginAtZero: true, ticks: { callback: fmtMoney, maxTicksLimit: 6 } },
                x: { grid: { display: false }, ticks: { font: { size: 11 } } },
            },
        },
    });

    const vpm = @json($vencidoPorMes);
    if (vpm.total > 0) {
        const paleta = [
            'rgba(139, 92, 246, 0.85)',
            'rgba(168, 85, 247, 0.85)',
            'rgba(217, 70, 239, 0.85)',
            'rgba(236, 72, 153, 0.85)',
            'rgba(239, 68, 68, 0.8)',
            'rgba(245, 158, 11, 0.85)',
            'rgba(31, 95, 139, 0.85)',
            'rgba(11, 60, 93, 0.85)',
        ];
        const colorBarra = vpm.montos.map((_, i) => paleta[i % paleta.length]);

        new Chart(document.getElementById('chartVencidoPorMes'), {
            type: 'bar',
            data: {
                labels: vpm.labels,
                datasets: [{ label: 'Saldo vencido', data: vpm.montos, backgroundColor: colorBarra, borderRadius: 4, maxBarThickness: 36 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: { callbacks: { label: (ctx) => fmtMoney(ctx.raw) } },
                },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: fmtMoney, maxTicksLimit: 6 } },
                    x: { grid: { display: false }, ticks: { font: { size: 11 } } },
                },
            },
        });

        const donutLabels = [];
        const donutData = [];
        const donutColors = [];
        vpm.filas.forEach((fila, i) => {
            if (fila.monto > 0) {
                donutLabels.push(fila.mes);
                donutData.push(fila.monto);
                donutColors.push(colorBarra[i]);
            }
        });

        new Chart(document.getElementById('chartVencidoPorMesPct'), {
            type: 'doughnut',
            data: {
                labels: donutLabels,
                datasets: [{ data: donutData, backgroundColor: donutColors, borderWidth: 0 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '55%',
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: (ctx) => {
                                const pct = vpm.total > 0 ? (ctx.raw / vpm.total * 100).toFixed(1) : '0.0';
                                return ctx.label + ': ' + fmtMoney(ctx.raw) + ' (' + pct + '%)';
                            },
                        },
                    },
                },
            },
        });
    }
});
</script>
@endpush