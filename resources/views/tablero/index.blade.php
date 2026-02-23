@extends('layouts.app')

@section('title', 'Tablero')
@section('page-title', '📈 Tablero')
@section('page-subtitle', 'Ventas, clientes, productos y compras en gráficas')

@php
$breadcrumbs = [
    ['title' => 'Dashboard', 'url' => route('dashboard')],
    ['title' => 'Tablero']
];
@endphp

@section('content')

{{-- 1. VENTAS (ventas + cobranza) --}}
<section class="tablero-section">
    <h2 class="tablero-section-title">💰 Ventas</h2>
    <div class="tablero-grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Ventas del año (facturas timbradas)</div>
            </div>
            <div class="card-body">
                <canvas id="chartVentas" height="220"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Cobranza</div>
            </div>
            <div class="card-body">
                <canvas id="chartCobranza" height="220"></canvas>
            </div>
        </div>
    </div>
</section>

{{-- 2. CLIENTES --}}
<section class="tablero-section">
    <h2 class="tablero-section-title">👥 Clientes</h2>
    <div class="tablero-grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Clientes más importantes (por ventas)</div>
                <a href="{{ route('clientes.index') }}" class="btn btn-light btn-sm">Ver clientes</a>
            </div>
            <div class="card-body">
                <canvas id="chartClientesImportantes" height="280"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Antigüedad de saldos</div>
                <a href="{{ route('cuentas-cobrar.index') }}" class="btn btn-light btn-sm">Ver CxC</a>
            </div>
            <div class="card-body">
                <canvas id="chartAntiguedadSaldos" height="280"></canvas>
            </div>
        </div>
    </div>
</section>

{{-- 3. PRODUCTOS --}}
<section class="tablero-section">
    <h2 class="tablero-section-title">📦 Productos</h2>
    <div class="tablero-grid-3">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Productos más vendidos</div>
                <a href="{{ route('productos.index') }}" class="btn btn-light btn-sm">Ver productos</a>
            </div>
            <div class="card-body">
                <canvas id="chartMasVendidos" height="260"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Mayor costo</div>
            </div>
            <div class="card-body">
                <div class="tablero-lista">
                    @forelse($mayorCosto as $p)
                        <div class="tablero-lista-item">
                            <span class="tablero-lista-nombre" title="{{ $p->nombre }}">{{ Str::limit($p->nombre, 28) }}</span>
                            <span class="text-mono fw-600">${{ number_format($p->costo, 2, '.', ',') }}</span>
                        </div>
                    @empty
                        <p class="text-muted">Sin datos</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Utilidad bruta</div>
            </div>
            <div class="card-body">
                <div class="tablero-lista">
                    @forelse($utilidadBruta as $p)
                        <div class="tablero-lista-item">
                            <span class="tablero-lista-nombre" title="{{ $p->nombre }}">{{ Str::limit($p->nombre, 28) }}</span>
                            <span class="text-mono fw-600" style="color: var(--color-success);">${{ number_format($p->utilidad_bruta ?? ($p->precio_venta - $p->costo), 2, '.', ',') }}</span>
                        </div>
                    @empty
                        <p class="text-muted">Sin datos</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</section>

{{-- 4. COMPRAS Y GASTOS --}}
<section class="tablero-section">
    <h2 class="tablero-section-title">🏭 Compras y gastos</h2>
    <div class="tablero-grid-2">
        <div class="card">
            <div class="card-header">
                <div class="card-title">Compras del año (órdenes recibidas/aceptadas)</div>
            </div>
            <div class="card-body">
                <canvas id="chartComprasMes" height="220"></canvas>
            </div>
        </div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">Proveedores / servicios más importantes</div>
                <a href="{{ route('ordenes-compra.index') }}" class="btn btn-light btn-sm">Ver órdenes</a>
            </div>
            <div class="card-body">
                <canvas id="chartComprasProveedor" height="260"></canvas>
            </div>
        </div>
    </div>
</section>

@endsection

@push('styles')
<style>
.tablero-section { margin-bottom: 32px; }
.tablero-section-title { font-size: 1.1rem; font-weight: 700; color: var(--color-dark); margin-bottom: 16px; padding-bottom: 8px; border-bottom: 2px solid var(--color-primary); }
.tablero-grid-2 { display: grid; grid-template-columns: repeat(2, 1fr); gap: 20px; }
.tablero-grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; }
.tablero-lista { display: flex; flex-direction: column; gap: 8px; }
.tablero-lista-item { display: flex; justify-content: space-between; align-items: center; padding: 6px 0; border-bottom: 1px solid var(--color-gray-100); font-size: 13px; }
.tablero-lista-nombre { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 70%; }
@media (max-width: 1024px) {
    .tablero-grid-2, .tablero-grid-3 { grid-template-columns: 1fr; }
}
</style>
@endpush

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const colores = [
        'rgba(11, 60, 93, 0.85)',
        'rgba(31, 95, 139, 0.85)',
        'rgba(247, 240, 15, 0.75)',
        'rgba(16, 185, 129, 0.85)',
        'rgba(245, 158, 11, 0.85)',
        'rgba(239, 68, 68, 0.75)',
        'rgba(139, 92, 246, 0.85)',
        'rgba(236, 72, 153, 0.85)',
    ];
    const coloresFondo = colores.map(c => c.replace('0.85', '0.35').replace('0.75', '0.3'));

    // Ventas por mes
    new Chart(document.getElementById('chartVentas'), {
        type: 'bar',
        data: {
            labels: @json($labelsVentas),
            datasets: [{
                label: 'Ventas ($)',
                data: @json($dataVentas),
                backgroundColor: 'rgba(11, 60, 93, 0.6)',
                borderColor: 'rgb(11, 60, 93)',
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + (v/1000).toFixed(0) + 'k' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Cobranza (pendiente vs cobrado mes)
    new Chart(document.getElementById('chartCobranza'), {
        type: 'doughnut',
        data: {
            labels: @json($cobranzaLabels),
            datasets: [{
                data: @json($cobranzaData),
                backgroundColor: ['rgba(245, 158, 11, 0.8)', 'rgba(16, 185, 129, 0.8)'],
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // Clientes más importantes
    new Chart(document.getElementById('chartClientesImportantes'), {
        type: 'bar',
        data: {
            labels: @json($clientesImportantes->map(fn($c) => Str::limit($c->nombre, 20))),
            datasets: [{
                label: 'Ventas ($)',
                data: @json($clientesImportantes->pluck('total_ventas')),
                backgroundColor: 'rgba(31, 95, 139, 0.6)',
                borderColor: 'rgb(31, 95, 139)',
                borderWidth: 1,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { callback: v => '$' + (v/1000).toFixed(0) + 'k' } },
                y: { grid: { display: false } }
            }
        }
    });

    // Antigüedad de saldos
    new Chart(document.getElementById('chartAntiguedadSaldos'), {
        type: 'bar',
        data: {
            labels: @json($antiguedadLabels),
            datasets: [{
                label: 'Monto ($)',
                data: @json($antiguedadData),
                backgroundColor: ['rgba(16, 185, 129, 0.6)', 'rgba(59, 130, 246, 0.6)', 'rgba(245, 158, 11, 0.6)', 'rgba(239, 68, 68, 0.6)', 'rgba(139, 92, 246, 0.6)'],
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + (v/1000).toFixed(0) + 'k' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Productos más vendidos
    new Chart(document.getElementById('chartMasVendidos'), {
        type: 'bar',
        data: {
            labels: @json($masVendidos->map(fn($d) => $d->producto ? Str::limit($d->producto->nombre ?? $d->producto->codigo ?? 'N/A', 18) : 'N/A')),
            datasets: [{
                label: 'Cantidad',
                data: @json($masVendidos->pluck('cantidad')),
                backgroundColor: 'rgba(11, 60, 93, 0.6)',
                borderColor: 'rgb(11, 60, 93)',
                borderWidth: 1,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true },
                y: { grid: { display: false } }
            }
        }
    });

    // Compras por mes
    new Chart(document.getElementById('chartComprasMes'), {
        type: 'bar',
        data: {
            labels: @json($labelsCompras),
            datasets: [{
                label: 'Compras ($)',
                data: @json($dataCompras),
                backgroundColor: 'rgba(31, 95, 139, 0.6)',
                borderColor: 'rgb(31, 95, 139)',
                borderWidth: 1,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                y: { beginAtZero: true, ticks: { callback: v => '$' + (v/1000).toFixed(0) + 'k' } },
                x: { grid: { display: false } }
            }
        }
    });

    // Compras por proveedor
    new Chart(document.getElementById('chartComprasProveedor'), {
        type: 'bar',
        data: {
            labels: @json($comprasPorProveedor->map(fn($c) => Str::limit($c->proveedor_nombre, 18))),
            datasets: [{
                label: 'Total ($)',
                data: @json($comprasPorProveedor->pluck('total')),
                backgroundColor: 'rgba(139, 92, 246, 0.6)',
                borderColor: 'rgb(139, 92, 246)',
                borderWidth: 1,
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, ticks: { callback: v => '$' + (v/1000).toFixed(0) + 'k' } },
                y: { grid: { display: false } }
            }
        }
    });
});
</script>
@endpush
