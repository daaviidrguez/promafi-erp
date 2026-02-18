@extends('layouts.app')

@section('title', 'Cliente: ' . $cliente->nombre)
@section('page-title', $cliente->nombre)
@section('page-subtitle', 'RFC: ' . $cliente->rfc)

@php
$breadcrumbs = [
    ['title' => 'Clientes', 'url' => route('clientes.index')],
    ['title' => $cliente->nombre]
];
@endphp

@section('content')

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

    {{-- Columna izquierda --}}
    <div>
        {{-- Información General --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información General</div>
                <a href="{{ route('clientes.edit', $cliente->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">RFC</div>
                        <div class="info-value text-mono">{{ $cliente->rfc }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Régimen Fiscal</div>
                        <div class="info-value">{{ $cliente->regimen_fiscal ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Uso de CFDI</div>
                        <div class="info-value">{{ $cliente->uso_cfdi_default }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Email</div>
                        <div class="info-value-sm">{{ $cliente->email ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Teléfono</div>
                        <div class="info-value-sm">{{ $cliente->telefono ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Celular</div>
                        <div class="info-value-sm">{{ $cliente->celular ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Facturas Recientes --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🧾 Facturas Recientes</div>
            </div>
            @if($cliente->facturas->count() > 0)
            <div class="table-container" style="border: none; box-shadow: none; border-radius: 0;">
                <table>
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th class="td-right">Total</th>
                            <th class="td-center">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($cliente->facturas as $factura)
                        <tr>
                            <td class="text-mono fw-600">{{ $factura->folio_completo }}</td>
                            <td>{{ $factura->fecha_emision->format('d/m/Y') }}</td>
                            <td class="td-right text-mono">${{ number_format($factura->total, 2, '.', ',') }}</td>
                            <td class="td-center">
                                @if($factura->estado === 'timbrada')
                                    <span class="badge badge-success">✓ Timbrada</span>
                                @elseif($factura->estado === 'borrador')
                                    <span class="badge badge-warning">📝 Borrador</span>
                                @else
                                    <span class="badge badge-danger">✗ Cancelada</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <div class="card-body">
                <div class="empty-state" style="padding: 32px 20px;">
                    <div class="empty-state-icon">📄</div>
                    <div class="empty-state-title">Sin facturas registradas</div>
                </div>
            </div>
            @endif
        </div>
    </div>

    {{-- Columna derecha --}}
    <div>
        {{-- Estadísticas --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📊 Estadísticas</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Tipo de Cliente</div>
                    <div style="margin-top: 4px;">
                        @if($cliente->esCredito())
                            <span class="badge badge-warning">💳 Crédito ({{ $cliente->dias_credito }} días)</span>
                        @else
                            <span class="badge badge-success">💵 Contado</span>
                        @endif
                    </div>
                </div>

                @if($cliente->esCredito())
                <div class="info-row">
                    <div class="info-label">Límite de Crédito</div>
                    <div class="info-value">${{ number_format($cliente->limite_credito, 2, '.', ',') }}</div>
                </div>
                <div class="info-row">
                    <div class="info-label">Saldo Actual</div>
                    <div class="info-value" style="color: {{ $cliente->saldo_actual > 0 ? 'var(--color-warning)' : 'var(--color-success)' }}">
                        ${{ number_format($cliente->saldo_actual, 2, '.', ',') }}
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Crédito Disponible</div>
                    <div class="info-value" style="color: var(--color-success);">
                        ${{ number_format($cliente->limite_credito - $cliente->saldo_actual, 2, '.', ',') }}
                    </div>
                </div>
                @endif

                <div class="info-row" style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-200);">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @if($cliente->activo)
                            <span class="badge badge-success">✓ Activo</span>
                        @else
                            <span class="badge badge-danger">✗ Inactivo</span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Acciones Rápidas</div>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">
                <a href="{{ route('facturas.create') }}?cliente_id={{ $cliente->id }}"
                   class="btn btn-primary w-full">🧾 Nueva Factura</a>

                <a href="{{ route('clientes.edit', $cliente->id) }}"
                   class="btn btn-outline w-full">✏️ Editar Cliente</a>

                <form method="POST" action="{{ route('clientes.destroy', $cliente->id) }}"
                      onsubmit="return confirm('¿Eliminar este cliente?');">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar Cliente</button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection