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
        {{-- Datos Generales --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Datos Generales</div>
                <a href="{{ route('clientes.edit', $cliente->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Nombre / Razón Social</div>
                        <div class="info-value">{{ $cliente->nombre }}</div>
                    </div>
                    @if($cliente->nombre_comercial)
                    <div class="info-row">
                        <div class="info-label">Nombre Comercial</div>
                        <div class="info-value">{{ $cliente->nombre_comercial }}</div>
                    </div>
                    @endif
                    <div class="info-row">
                        <div class="info-label">Tipo de Persona</div>
                        <div class="info-value">
                            @if($cliente->tipo_persona === 'moral')
                                <span class="badge badge-info">Persona Moral</span>
                            @else
                                <span class="badge badge-info">Persona Física</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Contacto --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📞 Contacto</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
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
                    @if($cliente->contacto_nombre || $cliente->contacto_puesto)
                    <div class="info-row">
                        <div class="info-label">Contacto</div>
                        <div class="info-value-sm">{{ $cliente->contacto_nombre ?? '—' }}{{ $cliente->contacto_puesto ? ' · ' . $cliente->contacto_puesto : '' }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Domicilio Fiscal --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📍 Domicilio Fiscal</div>
            </div>
            <div class="card-body">
                @if($cliente->calle || $cliente->ciudad)
                <div class="info-grid-2">
                    @if($cliente->calle)
                    <div class="info-row">
                        <div class="info-label">Calle</div>
                        <div class="info-value">{{ $cliente->calle }}{{ $cliente->numero_exterior ? ' ' . $cliente->numero_exterior : '' }}{{ $cliente->numero_interior ? ' Int. ' . $cliente->numero_interior : '' }}</div>
                    </div>
                    @endif
                    @if($cliente->colonia)
                    <div class="info-row">
                        <div class="info-label">Colonia</div>
                        <div class="info-value">{{ $cliente->colonia }}</div>
                    </div>
                    @endif
                    @if($cliente->ciudad || $cliente->estado)
                    <div class="info-row">
                        <div class="info-label">Ciudad / Estado</div>
                        <div class="info-value">{{ $cliente->ciudad ?? '—' }}{{ $cliente->estado ? ', ' . $cliente->estado : '' }}</div>
                    </div>
                    @endif
                    @if($cliente->codigo_postal)
                    <div class="info-row">
                        <div class="info-label">C.P.</div>
                        <div class="info-value text-mono">{{ $cliente->codigo_postal }}</div>
                    </div>
                    @endif
                    @if($cliente->pais)
                    <div class="info-row">
                        <div class="info-label">País</div>
                        <div class="info-value">{{ $cliente->pais }}</div>
                    </div>
                    @endif
                </div>
                @else
                <p class="text-muted" style="margin:0;">Sin domicilio registrado</p>
                @endif
            </div>
        </div>

        {{-- Contactos del Cliente --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📇 Contactos del Cliente</div>
                <a href="{{ route('clientes.contactos.create', $cliente) }}"
                class="btn btn-primary btn-sm">➕ Nuevo</a>
            </div>

            @if($cliente->contactos->count())
                <div class="table-container" style="border:none; box-shadow:none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Puesto</th>
                                <th>Email</th>
                                <th>Celular</th>
                                <th class="td-center">Estado</th>
                                <th class="td-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cliente->contactos as $contacto)
                                <tr>
                                    <td>
                                        {{ $contacto->nombre }}
                                        @if($contacto->principal)
                                            <span class="badge badge-success">Principal</span>
                                        @endif
                                    </td>
                                    <td>{{ $contacto->puesto ?? '—' }}</td>
                                    <td>{{ $contacto->email ?? '—' }}</td>
                                    <td>{{ $contacto->celular ?? '—' }}</td>
                                    <td class="td-center">
                                        @if($contacto->activo)
                                            <span class="badge badge-success">Activo</span>
                                        @else
                                            <span class="badge badge-danger">Inactivo</span>
                                        @endif
                                    </td>
                                    <td class="td-center">
                                        <a href="{{ route('clientes.contactos.edit', [$cliente, $contacto]) }}"
                                        class="btn btn-light btn-sm">✏️</a>
                                        <form method="POST"
                                            action="{{ route('clientes.contactos.destroy', [$cliente, $contacto]) }}"
                                            style="display:inline;">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="btn btn-danger btn-sm"
                                                    onclick="return confirm('¿Eliminar contacto?')">
                                                🗑
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">👤</div>
                        <div class="empty-state-title">Sin contactos registrados</div>
                    </div>
                </div>
            @endif
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
        {{-- Información Fiscal --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📑 Información Fiscal</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">RFC</div>
                        <div class="info-value text-mono">{{ $cliente->rfc }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Régimen Fiscal</div>
                        <div class="info-value">{{ $cliente->regimen_fiscal ? (config('regimenes_fiscales.'.$cliente->regimen_fiscal) ?? $cliente->regimen_fiscal) : '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Uso de CFDI</div>
                        <div class="info-value">{{ $cliente->uso_cfdi_default ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

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
