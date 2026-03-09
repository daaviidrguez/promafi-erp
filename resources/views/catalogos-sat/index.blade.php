@extends('layouts.app')

@section('title', 'Catálogos SAT')
@section('page-title', '📑 Catálogos SAT')
@section('page-subtitle', 'Gestiona los catálogos de facturación electrónica')

@php
$breadcrumbs = [
    ['title' => 'Facturación', 'url' => route('facturas.index')],
    ['title' => 'Catálogos SAT']
];
@endphp

@section('content')

<div class="card">
    <div class="card-header">
        <div class="card-title">Catálogos disponibles</div>
    </div>
    <div class="card-body">
        <p class="text-muted mb-4">Desde aquí se administran los catálogos que utiliza el sistema para facturación (SAT). Usa estos valores en clientes, empresa, facturas y productos.</p>
        <div class="grid-2" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 12px;">
            <a href="{{ route('catalogos-sat.regimenes-fiscales.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px;">
                <strong>🏛️ Regímenes fiscales</strong>
                <p class="text-muted small mb-0 mt-1">Claves de régimen fiscal (601, 612, etc.)</p>
            </a>
            <a href="{{ route('catalogos-sat.usos-cfdi.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px;">
                <strong>📋 Usos de CFDI</strong>
                <p class="text-muted small mb-0 mt-1">G03, P01, S01, D01, etc.</p>
            </a>
            <a href="{{ route('catalogos-sat.formas-pago.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px;">
                <strong>💳 Formas de pago</strong>
                <p class="text-muted small mb-0 mt-1">01 Efectivo, 03 Transferencia, etc.</p>
            </a>
            <a href="{{ route('catalogos-sat.metodos-pago.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px;">
                <strong>📌 Métodos de pago</strong>
                <p class="text-muted small mb-0 mt-1">PUE, PPD</p>
            </a>
            <a href="{{ route('catalogos-sat.monedas.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px;">
                <strong>💵 Monedas</strong>
                <p class="text-muted small mb-0 mt-1">MXN, USD, etc.</p>
            </a>
            <a href="{{ route('catalogos-sat.unidades-medida.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px;">
                <strong>📐 Unidades de medida</strong>
                <p class="text-muted small mb-0 mt-1">H87 Pieza, KGM Kilogramo, etc.</p>
            </a>
            <a href="{{ route('catalogos-sat.claves-producto-servicio.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px;">
                <strong>📦 Clave producto/servicio</strong>
                <p class="text-muted small mb-0 mt-1">Catálogo SAT de productos y servicios (carga por Excel)</p>
            </a>
            <a href="{{ route('catalogos-sat.isr-resico.index') }}" class="card" style="text-decoration: none; color: inherit; padding: 16px; border-left: 4px solid #0B3C5D;">
                <strong>📊 Tabla ISR RESICO</strong>
                <p class="text-muted small mb-0 mt-1">Aplica a régimen 626 - Régimen Simplificado de Confianza (persona física). Tasas aproximadas por ingreso mensual.</p>
            </a>
        </div>
    </div>
</div>

@endsection
