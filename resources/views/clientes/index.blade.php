@extends('layouts.app')

@section('title', 'Clientes')
@section('page-title', '👥 Clientes')
@section('page-subtitle', 'Gestión de clientes registrados')

@php
$breadcrumbs = [
    ['title' => 'Clientes']
];
@endphp

@section('content')

{{-- Búsqueda + Acción --}}
<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('clientes.index') }}"
              style="display: flex; justify-content: space-between; align-items: center; gap: 16px; flex-wrap: wrap;">
            <div style="display: flex; gap: 12px; flex: 1; flex-wrap: wrap;">
                <input type="text"
                       name="search"
                       value="{{ $search ?? '' }}"
                       placeholder="Buscar por nombre, RFC, email..."
                       class="form-control"
                       style="flex: 1; min-width: 240px;">
                <button type="submit"
                        style="padding: 9px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">
                    🔍 Buscar
                </button>
                @if($search ?? false)
                <a href="{{ route('clientes.index') }}"
                   style="padding: 9px 16px; border: 1.5px solid var(--color-gray-300); border-radius: var(--radius-md); color: var(--color-gray-600); font-weight: 600;">
                    ✕ Limpiar
                </a>
                @endif
            </div>
            <a href="{{ route('clientes.create') }}" class="btn btn-primary">
                ➕ Nuevo Cliente
            </a>
        </form>
    </div>
</div>

{{-- Tabla --}}
<div class="table-container">
    @if($clientes->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Cliente</th>
                <th>RFC</th>
                <th>Contacto</th>
                <th class="td-center">Tipo</th>
                <th class="td-right">Límite Crédito</th>
                @can('clientes.ver_saldos')
                <th class="td-right">Saldo</th>
                @endcan
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($clientes as $cliente)
            <tr>
                <td>
                    <div class="fw-600 text-primary">{{ $cliente->nombre }}</div>
                    @if($cliente->nombre_comercial)
                        <div class="text-muted" style="font-size: 12px;">{{ $cliente->nombre_comercial }}</div>
                    @endif
                </td>
                <td class="text-mono" style="font-size: 13px;">{{ $cliente->rfc }}</td>
                <td>
                    @if($cliente->email)
                        <div style="font-size: 13px;">📧 {{ $cliente->email }}</div>
                    @endif
                    @if($cliente->telefono)
                        <div style="font-size: 13px;">📱 {{ $cliente->telefono }}</div>
                    @endif
                </td>
                <td class="td-center">
                    @if($cliente->dias_credito > 0)
                        <span class="badge badge-warning">💳 Crédito ({{ $cliente->dias_credito }}d)</span>
                    @else
                        <span class="badge badge-success">💵 Contado</span>
                    @endif
                </td>
                <td class="td-right text-mono">${{ number_format($cliente->limite_credito, 2, '.', ',') }}</td>
                @can('clientes.ver_saldos')
                <td class="td-right text-mono fw-600"
                    style="color: {{ $cliente->saldo_actual_coherente > 0 ? 'var(--color-warning)' : 'var(--color-success)' }}">
                    ${{ number_format($cliente->saldo_actual_coherente, 2, '.', ',') }}
                </td>
                @endcan
                <td class="td-center">
                    @if($cliente->activo)
                        <span class="badge badge-success">✓ Activo</span>
                    @else
                        <span class="badge badge-danger">✗ Inactivo</span>
                    @endif
                </td>
                <td class="td-actions">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <a href="{{ route('clientes.show', $cliente->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                        <a href="{{ route('clientes.edit', $cliente->id) }}"
                           class="btn btn-warning btn-sm btn-icon" title="Editar">✏️</a>
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $clientes->withQueryString()->links() }}
    </div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">👥</div>
        <div class="empty-state-title">No hay clientes registrados</div>
        <div class="empty-state-text">Comienza agregando tu primer cliente</div>
        <div style="margin-top: 20px;">
            <a href="{{ route('clientes.create') }}" class="btn btn-primary">➕ Crear Primer Cliente</a>
        </div>
    </div>
    @endif
</div>

@endsection