@extends('layouts.app')

@section('title', 'Dashboard de Ventas')
@section('page-title', '🎯 Dashboard de Ventas')
@section('page-subtitle', 'Meta vs facturación sin IVA — {{ $mesLabel }}')

@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => route('dashboard')],
    ['title' => 'Dashboard de Ventas'],
];
@endphp

@section('content')

{{-- Filtros mes / año --}}
<div class="card" style="margin-bottom: 20px;">
    <div class="card-body" style="padding: 16px 20px;">
        <form method="GET" action="{{ route('ventas.dashboard') }}" style="display:flex; gap:12px; flex-wrap:wrap; align-items:flex-end;">
            @if ($clienteId > 0)
                <input type="hidden" name="cliente_id" value="{{ $clienteId }}">
            @endif
            <div class="form-group" style="margin:0;">
                <label class="form-label">Mes</label>
                <select name="mes" class="form-control" style="min-width:140px;">
                    @for ($m = 1; $m <= 12; $m++)
                        <option value="{{ $m }}" @selected($mes === $m)>
                            {{ \Illuminate\Support\Carbon::create(null, $m, 1)->locale('es')->translatedFormat('F') }}
                        </option>
                    @endfor
                </select>
            </div>
            <div class="form-group" style="margin:0;">
                <label class="form-label">Año</label>
                <input type="number" name="anio" class="form-control" value="{{ $anio }}" min="2020" max="2035" style="width:100px;">
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Actualizar</button>
            <div style="flex:1;"></div>
            <a href="{{ route('facturas.index') }}" class="btn btn-light btn-sm">Ir a facturación</a>
            <a href="{{ route('reportes.ventas') }}?mes={{ $mes }}&año={{ $anio }}" class="btn btn-outline btn-sm">Reporte ventas</a>
        </form>
    </div>
</div>

{{-- Panel Meta --}}
<div class="card ven-meta-panel" style="margin-bottom: 20px;">
    <div class="card-header" style="flex-wrap:wrap; gap:12px;">
        <div style="flex:1; min-width:200px;">
            <div class="card-title">Meta de ventas — {{ $metaVentas['mes_label'] }}</div>
            <div style="font-size:13px; color:var(--color-gray-500); margin-top:4px;">
                {{ $metaVentas['subtitulo'] ?? '' }}
                @if (($metaVentas['modo'] ?? '') === 'cliente' && !empty($metaVentas['cliente_nombre']))
                    <strong>{{ $metaVentas['cliente_nombre'] }}</strong>
                @endif
            </div>
        </div>
        <form method="GET" action="{{ route('ventas.dashboard') }}" style="display:flex; align-items:center; gap:8px; margin:0;">
            <input type="hidden" name="mes" value="{{ $mes }}">
            <input type="hidden" name="anio" value="{{ $anio }}">
            <label class="form-label" style="margin:0; white-space:nowrap;">Cliente</label>
            <select name="cliente_id" class="form-control" style="min-width:200px;" onchange="this.form.submit()">
                <option value="0" @selected($clienteId === 0)>
                    Todos ({{ $clientesMeta->count() }} con meta)
                </option>
                @foreach ($clientesMeta as $c)
                    <option value="{{ $c->id }}" @selected($clienteId === (int) $c->id)>
                        {{ $c->nombre_comercial ?: $c->nombre }}
                    </option>
                @endforeach
            </select>
        </form>
        <div class="ven-meta-progress">
            <span style="font-size:12px; color:var(--color-gray-500);">Progreso</span>
            <div class="ven-meta-progress__track">
                <div class="ven-meta-progress__fill" style="width: {{ min(100, $metaVentas['pct_avance']) }}%;"></div>
            </div>
            <strong style="font-size:13px; white-space:nowrap;">{{ number_format($metaVentas['pct_avance'], 1) }}%</strong>
        </div>
    </div>
    <div class="card-body">
        <div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); margin-bottom: 20px;">
            <div class="stat-card">
                <div class="stat-info-box">
                    <div class="stat-label">Meta del mes</div>
                    <div class="stat-value" style="font-size:18px;">${{ number_format($metaVentas['meta'], 0) }}</div>
                    <div class="stat-sub">Sin IVA</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--color-success);">
                <div class="stat-info-box">
                    <div class="stat-label">Facturado</div>
                    <div class="stat-value" style="font-size:18px; color:var(--color-success);">${{ number_format($metaVentas['facturado'], 0) }}</div>
                    <div class="stat-sub">{{ $metaVentas['num_facturas'] }} fact.</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--color-info);">
                <div class="stat-info-box">
                    <div class="stat-label">Avance</div>
                    <div class="stat-value" style="font-size:18px;">{{ number_format($metaVentas['pct_avance'], 1) }}%</div>
                    <div class="stat-sub">Del 100%</div>
                </div>
            </div>
            <div class="stat-card" style="border-left-color: var(--color-warning);">
                <div class="stat-info-box">
                    <div class="stat-label">Faltante</div>
                    <div class="stat-value" style="font-size:18px; color:var(--color-warning);">${{ number_format($metaVentas['faltante'], 0) }}</div>
                    <div class="stat-sub">Para meta</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-info-box">
                    <div class="stat-label">Producción diaria</div>
                    <div class="stat-value" style="font-size:18px;">${{ number_format($metaVentas['produccion_diaria'], 0) }}</div>
                    <div class="stat-sub">
                        @if ($metaVentas['dias_restantes'] > 0)
                            {{ $metaVentas['dias_restantes'] }} días
                        @else
                            —
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="tablero-grid-2">
            <div>
                <div style="font-size:13px; font-weight:600; color:var(--color-gray-600); margin-bottom:8px; text-align:center;">Avance vs meta</div>
                <div style="position:relative; height:180px; max-width:420px; margin:0 auto;">
                    <canvas id="chartMetaAvance"></canvas>
                </div>
            </div>
            <div>
                <div style="font-size:13px; font-weight:600; color:var(--color-gray-600); margin-bottom:8px;">Acumulado del mes vs objetivo lineal</div>
                <div style="position:relative; height:200px;">
                    <canvas id="chartMetaTendencia"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- KPIs operativos --}}
<div class="stats-grid" style="grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); margin-bottom: 20px;">
    <div class="stat-card stat-info">
        <div class="stat-info-box">
            <div class="stat-label">Facturado del mes</div>
            <div class="stat-value" style="font-size:18px;">${{ number_format($kpis['facturado_mes'], 0) }}</div>
            <div class="stat-sub">Sin IVA · fecha emisión</div>
        </div>
        <div class="stat-icon">💰</div>
    </div>
    <div class="stat-card stat-success">
        <div class="stat-info-box">
            <div class="stat-label">Facturas timbradas</div>
            <div class="stat-value">{{ $kpis['num_facturas'] }}</div>
        </div>
        <div class="stat-icon">🧾</div>
    </div>
    <div class="stat-card">
        <div class="stat-info-box">
            <div class="stat-label">Vendedores con venta</div>
            <div class="stat-value">{{ $kpis['num_vendedores'] }}</div>
        </div>
        <div class="stat-icon">👤</div>
    </div>
    <div class="stat-card">
        <div class="stat-info-box">
            <div class="stat-label">Ticket promedio</div>
            <div class="stat-value" style="font-size:18px;">${{ number_format($kpis['ticket_promedio'], 0) }}</div>
            <div class="stat-sub">Sin IVA</div>
        </div>
        <div class="stat-icon">🎫</div>
    </div>
    <div class="stat-card" style="border-left-color: var(--color-warning);">
        <div class="stat-info-box">
            <div class="stat-label">Mejor vendedor</div>
            <div class="stat-value" style="font-size:15px; line-height:1.3;">{{ $kpis['top_vendedor'] }}</div>
            <div class="stat-sub">${{ number_format($kpis['top_vendedor_monto'], 0) }}</div>
        </div>
        <div class="stat-icon">🏆</div>
    </div>
</div>

{{-- Gráficos --}}
<section class="tablero-section">
    <div class="tablero-grid-2" style="margin-bottom:20px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Facturado por vendedor — {{ $mesLabel }}</div>
            </div>
            <div class="card-body">
                <div style="position:relative; height:280px;">
                    <canvas id="chartPorVendedor"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Participación del mes</div>
            </div>
            <div class="card-body">
                <div style="position:relative; height:280px;">
                    <canvas id="chartParticipacion"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="tablero-grid-2" style="margin-bottom:20px;">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Top 10 clientes — {{ $mesLabel }}</div>
            </div>
            <div class="card-body">
                <div style="position:relative; height:300px;">
                    <canvas id="chartTopClientes"></canvas>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Tendencia facturado (6 meses)</div>
            </div>
            <div class="card-body">
                <div style="position:relative; height:300px;">
                    <canvas id="chartTendencia"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="card" style="margin-bottom:20px;">
        <div class="card-header">
            <div class="card-title">Top 5 vendedores — evolución (6 meses)</div>
        </div>
        <div class="card-body">
            <div style="position:relative; height:280px;">
                <canvas id="chartTopTendencia"></canvas>
            </div>
        </div>
    </div>
</section>

{{-- Ranking --}}
<div class="card">
    <div class="card-header">
        <div class="card-title">Ranking de vendedores — {{ $mesLabel }}</div>
    </div>
    @if (count($ranking_vendedores) === 0)
        <div class="card-body">
            <div class="empty-state">
                <div class="empty-state-icon">📄</div>
                <div class="empty-state-title">No hay facturas timbradas en este período</div>
            </div>
        </div>
    @else
        <div class="table-container" style="border:none; box-shadow:none;">
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Vendedor</th>
                        <th class="td-center">Facturas</th>
                        <th class="td-right">Facturado s/IVA</th>
                        <th class="td-right">% del total</th>
                        <th class="td-right">Mes anterior</th>
                        <th class="td-right">Variación</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totalRanking = collect($ranking_vendedores)->sum('monto'); @endphp
                    @foreach ($ranking_vendedores as $i => $row)
                        @php
                            $pct = $totalRanking > 0 ? round(($row['monto'] / $totalRanking) * 100, 1) : 0;
                            $var = $row['variacion_pct'];
                        @endphp
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td><strong>{{ $row['nombre'] }}</strong></td>
                            <td class="td-center">{{ $row['num_facturas'] }}</td>
                            <td class="td-right text-mono">${{ number_format($row['monto'], 2) }}</td>
                            <td class="td-right">{{ $pct }}%</td>
                            <td class="td-right text-mono">${{ number_format($row['monto_anterior'], 2) }}</td>
                            <td class="td-right">
                                @if ($var > 0)
                                    <span class="ven-var ven-var--up">+{{ $var }}%</span>
                                @elseif ($var < 0)
                                    <span class="ven-var ven-var--down">{{ $var }}%</span>
                                @else
                                    <span style="color:var(--color-gray-400);">0%</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-body" style="padding-top:0;">
            <p style="font-size:12px; color:var(--color-gray-500); margin:0;">
                Montos por <code>fecha_emision</code> de facturas timbradas (subtotal sin IVA). La variación compara con el mes anterior.
                Las metas se configuran en la ficha del cliente → Gestión comercial.
            </p>
        </div>
    @endif
</div>

@endsection

@push('styles')
<style>
.tablero-section { margin-bottom: 8px; }
.tablero-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.ven-meta-progress { display:flex; align-items:center; gap:8px; min-width:140px; }
.ven-meta-progress__track { flex:1; min-width:64px; height:6px; border-radius:999px; background:var(--color-gray-200); overflow:hidden; }
.ven-meta-progress__fill { height:100%; border-radius:999px; background:linear-gradient(90deg, var(--color-primary), var(--color-success)); }
.ven-var { font-weight:600; font-family:var(--font-mono, ui-monospace, monospace); }
.ven-var--up { color: var(--color-success); }
.ven-var--down { color: var(--color-danger); }
@media (max-width: 1024px) {
    .tablero-grid-2 { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const colores = [
        'rgba(11, 60, 93, 0.85)',
        'rgba(31, 95, 139, 0.85)',
        'rgba(16, 185, 129, 0.85)',
        'rgba(245, 158, 11, 0.85)',
        'rgba(239, 68, 68, 0.75)',
        'rgba(139, 92, 246, 0.85)',
        'rgba(236, 72, 153, 0.85)',
        'rgba(247, 240, 15, 0.75)',
    ];
    const fmtMoney = (v) => '$' + Number(v).toLocaleString('es-MX');
    const accent = 'rgb(11, 60, 93)';
    const success = 'rgb(16, 185, 129)';
    const warning = 'rgb(245, 158, 11)';
    const muted = 'rgb(203, 213, 225)';

    const avance = @json($metaVentas['chart_avance']);
    new Chart(document.getElementById('chartMetaAvance'), {
        type: 'doughnut',
        data: {
            labels: ['Avance', 'Restante'],
            datasets: [{
                data: [avance.avance, avance.restante],
                backgroundColor: [success, muted],
                borderWidth: 0,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 11 } } },
                tooltip: { callbacks: { label: (ctx) => ctx.label + ': ' + ctx.raw + '%' } },
            },
        },
    });

    const tendMeta = @json($metaVentas['chart_tendencia']);
    new Chart(document.getElementById('chartMetaTendencia'), {
        type: 'line',
        data: {
            labels: tendMeta.labels,
            datasets: [
                {
                    label: 'Facturado acumulado',
                    data: tendMeta.acumulado,
                    borderColor: accent,
                    backgroundColor: 'rgba(11, 60, 93, 0.12)',
                    fill: true,
                    tension: 0.3,
                    spanGaps: false,
                },
                {
                    label: 'Objetivo lineal',
                    data: tendMeta.objetivo,
                    borderColor: warning,
                    borderDash: [6, 4],
                    fill: false,
                    tension: 0,
                    pointRadius: 0,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, padding: 8, font: { size: 11 } } } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: fmtMoney, maxTicksLimit: 6 } },
                x: { ticks: { maxTicksLimit: 16 } },
            },
        },
    });

    const porVendedor = @json($chart_por_vendedor);
    if (porVendedor.labels.length) {
        new Chart(document.getElementById('chartPorVendedor'), {
            type: 'bar',
            data: {
                labels: porVendedor.labels,
                datasets: [{
                    label: 'Facturado',
                    data: porVendedor.montos,
                    backgroundColor: 'rgba(11, 60, 93, 0.7)',
                    borderRadius: 4,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { callback: fmtMoney } } },
            },
        });
    }

    const part = @json($chart_participacion);
    if (part.labels.length) {
        new Chart(document.getElementById('chartParticipacion'), {
            type: 'doughnut',
            data: {
                labels: part.labels,
                datasets: [{ data: part.montos, backgroundColor: colores, borderWidth: 0 }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom' },
                    tooltip: { callbacks: { label: (ctx) => ctx.label + ': ' + fmtMoney(ctx.raw) } },
                },
            },
        });
    }

    const topClientes = @json($chart_top_clientes);
    if (topClientes.labels.length) {
        new Chart(document.getElementById('chartTopClientes'), {
            type: 'bar',
            data: {
                labels: topClientes.labels,
                datasets: [{
                    label: 'Facturado',
                    data: topClientes.montos,
                    backgroundColor: 'rgba(31, 95, 139, 0.75)',
                    borderRadius: 4,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: { x: { beginAtZero: true, ticks: { callback: fmtMoney } } },
            },
        });
    }

    const tend = @json($chart_tendencia);
    new Chart(document.getElementById('chartTendencia'), {
        type: 'line',
        data: {
            labels: tend.labels,
            datasets: [{
                label: 'Facturado',
                data: tend.montos,
                borderColor: accent,
                backgroundColor: 'rgba(11, 60, 93, 0.15)',
                fill: true,
                tension: 0.3,
            }],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: fmtMoney } } },
        },
    });

    const topT = @json($chart_top_vendedores_tendencia);
    if (topT.datasets.length) {
        new Chart(document.getElementById('chartTopTendencia'), {
            type: 'bar',
            data: {
                labels: topT.labels,
                datasets: topT.datasets.map((ds) => ({
                    label: ds.label,
                    data: ds.data,
                    backgroundColor: ds.color,
                    borderRadius: 4,
                })),
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: 'bottom' } },
                scales: { y: { beginAtZero: true, ticks: { callback: fmtMoney } } },
            },
        });
    }
});
</script>
@endpush
