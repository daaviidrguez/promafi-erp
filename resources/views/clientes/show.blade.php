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
                <button type="button"
                        class="btn btn-primary btn-sm"
                        onclick="abrirModalContactoCrear()">➕ Nuevo</button>
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
                                        <button type="button"
                                                class="btn btn-light btn-sm btn-editar-contacto"
                                                data-id="{{ $contacto->id }}"
                                                data-nombre="{{ $contacto->nombre }}"
                                                data-puesto="{{ $contacto->puesto ?? '' }}"
                                                data-departamento="{{ $contacto->departamento ?? '' }}"
                                                data-email="{{ $contacto->email ?? '' }}"
                                                data-telefono="{{ $contacto->telefono ?? '' }}"
                                                data-celular="{{ $contacto->celular ?? '' }}"
                                                data-principal="{{ $contacto->principal ? 1 : 0 }}"
                                                data-activo="{{ $contacto->activo ? 1 : 0 }}"
                                                data-notas="{{ str_replace(['\r','\n'], '\\n', $contacto->notas ?? '') }}"
                                                data-update-url="{{ route('clientes.contactos.update', [$cliente, $contacto]) }}"
                                                onclick="abrirModalContactoEditar(this)">
                                            ✏️
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-sm btn-eliminar-contacto"
                                                data-delete-url="{{ route('clientes.contactos.destroy', [$cliente, $contacto]) }}"
                                                onclick="eliminarContacto(this)">
                                            🗑
                                        </button>
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

        {{-- Dirección de entrega --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📍🚚 Dirección de entrega</div>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        onclick="abrirModalDireccionCrear()">➕ Nuevo</button>
            </div>

            @if($cliente->direccionesEntrega->count())
                <div class="table-container" style="border:none; box-shadow:none;">
                    <table>
                        <thead>
                            <tr>
                                <th>Sucursal / Almacén</th>
                                <th>Dirección completa</th>
                                <th class="td-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($cliente->direccionesEntrega as $dir)
                                <tr>
                                    <td>{{ $dir->sucursal_almacen }}</td>
                                    <td style="white-space: pre-wrap;">{{ $dir->direccion_completa }}</td>
                                    <td class="td-center">
                                        <button type="button"
                                                class="btn btn-light btn-sm btn-editar-direccion"
                                                data-id="{{ $dir->id }}"
                                                data-sucursal="{{ $dir->sucursal_almacen }}"
                                                data-direccion="{{ str_replace(['\r','\n'], '\\n', $dir->direccion_completa) }}"
                                                data-update-url="{{ route('clientes.direcciones-entrega.update', [$cliente, $dir]) }}"
                                                onclick="abrirModalDireccionEditar(this)">
                                            ✏️
                                        </button>
                                        <button type="button"
                                                class="btn btn-danger btn-sm btn-eliminar-direccion"
                                                data-delete-url="{{ route('clientes.direcciones-entrega.destroy', [$cliente, $dir]) }}"
                                                onclick="eliminarDireccion(this)">
                                            🗑
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">🚚</div>
                        <div class="empty-state-title">Sin direcciones de entrega</div>
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
                        <div class="info-value">{{ $regimenEtiqueta ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Uso de CFDI</div>
                        <div class="info-value">{{ $usoCfdiEtiqueta ?? '—' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Forma de pago</div>
                        <div class="info-value">{{ $formaPagoEtiqueta ?? '—' }}</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Estadísticas (visible solo con permiso Ver saldos) --}}
        @can('clientes.ver_saldos')
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
                    <div class="info-value" style="color: {{ $cliente->saldo_actual_coherente > 0 ? 'var(--color-warning)' : 'var(--color-success)' }}">
                        ${{ number_format($cliente->saldo_actual_coherente, 2, '.', ',') }}
                    </div>
                </div>
                <div class="info-row">
                    <div class="info-label">Crédito Disponible</div>
                    <div class="info-value" style="color: var(--color-success);">
                        ${{ number_format($cliente->limite_credito - $cliente->saldo_actual_coherente, 2, '.', ',') }}
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
        @endcan

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

{{-- ===== Modales: Contactos + Direcciones de entrega ===== --}}
<div id="modalContacto" class="modal" style="z-index: 3000;">
    <div class="modal-box" style="max-width: 720px;">
        <div class="modal-header">
            <div class="modal-title" id="modalContactoTitle">Nuevo Contacto</div>
            <button type="button" class="modal-close" onclick="cerrarModalContacto()">✕</button>
        </div>

        <form id="formContacto"
              action="{{ route('clientes.contactos.store', $cliente) }}"
              method="POST">
            @csrf
            <input type="hidden" name="principal" id="contactoPrincipalValue" value="0">
            <input type="hidden" name="activo" id="contactoActivoValue" value="1">

            <div class="modal-body">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📇 Información del Contacto</div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Nombre Completo <span class="req">*</span></label>
                            <input type="text" id="contactoNombre" name="nombre" class="form-control" required>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Puesto</label>
                                <input type="text" id="contactoPuesto" name="puesto" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Departamento</label>
                                <input type="text" id="contactoDepartamento" name="departamento" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" id="contactoEmail" name="email" class="form-control">
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label class="form-label">Teléfono</label>
                                <input type="text" id="contactoTelefono" name="telefono" class="form-control">
                            </div>
                            <div class="form-group">
                                <label class="form-label">Celular</label>
                                <input type="text" id="contactoCelular" name="celular" class="form-control">
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" id="contactoPrincipalCheckbox">
                                Contacto Principal
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label" style="display:flex; gap:8px; align-items:center;">
                                <input type="checkbox" id="contactoActivoCheckbox" checked>
                                Activo
                            </label>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Notas</label>
                            <textarea id="contactoNotas" name="notas" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="cerrarModalContacto()">Cancelar</button>
                <button type="submit" class="btn btn-primary">✓ Guardar</button>
            </div>
        </form>
    </div>
</div>

<div id="modalDireccionEntrega" class="modal" style="z-index: 3000;">
    <div class="modal-box" style="max-width: 720px;">
        <div class="modal-header">
            <div class="modal-title" id="modalDireccionEntregaTitle">Nueva Dirección</div>
            <button type="button" class="modal-close" onclick="cerrarModalDireccionEntrega()">✕</button>
        </div>

        <form id="formDireccionEntrega"
              action="{{ route('clientes.direcciones-entrega.store', $cliente) }}"
              method="POST">
            @csrf
            <div class="modal-body">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">📍🚚 Dirección de entrega</div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label class="form-label">Sucursal / Almacén <span class="req">*</span></label>
                            <input type="text" id="direccionEntregaSucursal" name="sucursal_almacen" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Dirección completa <span class="req">*</span></label>
                            <textarea id="direccionEntregaCompleta" name="direccion_completa" class="form-control" rows="4" required></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="cerrarModalDireccionEntrega()">Cancelar</button>
                <button type="submit" class="btn btn-primary">✓ Guardar</button>
            </div>
        </form>
    </div>
</div>

@endsection

@push('scripts')
<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function cerrarModalContacto() {
        document.getElementById('modalContacto')?.classList.remove('show');
    }

    function abrirModalContactoCrear() {
        document.getElementById('modalContactoTitle').textContent = 'Nuevo Contacto';
        const form = document.getElementById('formContacto');
        form.action = @json(route('clientes.contactos.store', $cliente));

        document.getElementById('contactoNombre').value = '';
        document.getElementById('contactoPuesto').value = '';
        document.getElementById('contactoDepartamento').value = '';
        document.getElementById('contactoEmail').value = '';
        document.getElementById('contactoTelefono').value = '';
        document.getElementById('contactoCelular').value = '';
        document.getElementById('contactoNotas').value = '';

        document.getElementById('contactoPrincipalCheckbox').checked = false;
        document.getElementById('contactoActivoCheckbox').checked = true;
        document.getElementById('contactoPrincipalValue').value = '0';
        document.getElementById('contactoActivoValue').value = '1';

        form.querySelector('input[name="_method"]')?.remove();
        document.getElementById('modalContacto')?.classList.add('show');
    }

    function abrirModalContactoEditar(btn) {
        document.getElementById('modalContactoTitle').textContent = 'Editar Contacto';
        const form = document.getElementById('formContacto');
        form.action = btn.dataset.updateUrl;

        document.getElementById('contactoNombre').value = btn.dataset.nombre || '';
        document.getElementById('contactoPuesto').value = btn.dataset.puesto || '';
        document.getElementById('contactoDepartamento').value = btn.dataset.departamento || '';
        document.getElementById('contactoEmail').value = btn.dataset.email || '';
        document.getElementById('contactoTelefono').value = btn.dataset.telefono || '';
        document.getElementById('contactoCelular').value = btn.dataset.celular || '';
        document.getElementById('contactoNotas').value = (btn.dataset.notas || '').replace(/\\n/g, '\n');

        const principal = (btn.dataset.principal ?? '0') === '1';
        const activo = (btn.dataset.activo ?? '0') === '1';
        document.getElementById('contactoPrincipalCheckbox').checked = principal;
        document.getElementById('contactoActivoCheckbox').checked = activo;
        document.getElementById('contactoPrincipalValue').value = principal ? '1' : '0';
        document.getElementById('contactoActivoValue').value = activo ? '1' : '0';

        // Método override para update (PUT)
        if (!form.querySelector('input[name="_method"]')) {
            const m = document.createElement('input');
            m.type = 'hidden';
            m.name = '_method';
            m.value = 'PUT';
            form.appendChild(m);
        } else {
            form.querySelector('input[name="_method"]').value = 'PUT';
        }

        document.getElementById('modalContacto')?.classList.add('show');
    }

    function eliminarContacto(btn) {
        if (!confirm('¿Eliminar contacto?')) return;
        const url = btn.dataset.deleteUrl;
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new URLSearchParams({ _method: 'DELETE', _token: csrfToken })
        })
        .then(r => r.ok ? r.json().catch(() => ({})) : Promise.reject(r))
        .then(() => window.location.reload())
        .catch(err => {
            console.error(err);
            alert('Error al eliminar contacto.');
        });
    }

    document.getElementById('contactoPrincipalCheckbox')?.addEventListener('change', function() {
        document.getElementById('contactoPrincipalValue').value = this.checked ? '1' : '0';
    });
    document.getElementById('contactoActivoCheckbox')?.addEventListener('change', function() {
        document.getElementById('contactoActivoValue').value = this.checked ? '1' : '0';
    });

    document.getElementById('formContacto')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const actionUrl = form.action;

        // Asegurar valores boolean siempre
        document.getElementById('contactoPrincipalValue').value = document.getElementById('contactoPrincipalCheckbox').checked ? '1' : '0';
        document.getElementById('contactoActivoValue').value = document.getElementById('contactoActivoCheckbox').checked ? '1' : '0';

        const formData = new FormData(form);

        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(r => r.ok ? r.json().catch(() => ({})) : Promise.reject(r))
        .then(() => {
            cerrarModalContacto();
            window.location.reload();
        })
        .catch(err => {
            console.error(err);
            alert('Error al guardar contacto.');
        });
    });

    function cerrarModalDireccionEntrega() {
        document.getElementById('modalDireccionEntrega')?.classList.remove('show');
    }

    function abrirModalDireccionCrear() {
        document.getElementById('modalDireccionEntregaTitle').textContent = 'Nueva Dirección';
        const form = document.getElementById('formDireccionEntrega');
        form.action = @json(route('clientes.direcciones-entrega.store', $cliente));

        document.getElementById('direccionEntregaSucursal').value = '';
        document.getElementById('direccionEntregaCompleta').value = '';

        form.querySelector('input[name="_method"]')?.remove();
        document.getElementById('modalDireccionEntrega')?.classList.add('show');
    }

    function abrirModalDireccionEditar(btn) {
        document.getElementById('modalDireccionEntregaTitle').textContent = 'Editar Dirección';
        const form = document.getElementById('formDireccionEntrega');
        form.action = btn.dataset.updateUrl;

        document.getElementById('direccionEntregaSucursal').value = btn.dataset.sucursal || '';
        document.getElementById('direccionEntregaCompleta').value = (btn.dataset.direccion || '').replace(/\\n/g, '\n');

        // Método override para update (PUT)
        if (!form.querySelector('input[name="_method"]')) {
            const m = document.createElement('input');
            m.type = 'hidden';
            m.name = '_method';
            m.value = 'PUT';
            form.appendChild(m);
        } else {
            form.querySelector('input[name="_method"]').value = 'PUT';
        }

        document.getElementById('modalDireccionEntrega')?.classList.add('show');
    }

    function eliminarDireccion(btn) {
        if (!confirm('¿Eliminar dirección de entrega?')) return;
        const url = btn.dataset.deleteUrl;
        fetch(url, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: new URLSearchParams({ _method: 'DELETE', _token: csrfToken })
        })
        .then(r => r.ok ? r.json().catch(() => ({})) : Promise.reject(r))
        .then(() => window.location.reload())
        .catch(err => {
            console.error(err);
            alert('Error al eliminar dirección.');
        });
    }

    document.getElementById('formDireccionEntrega')?.addEventListener('submit', function(e) {
        e.preventDefault();
        const form = e.target;
        const actionUrl = form.action;

        const formData = new FormData(form);
        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': csrfToken,
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: formData
        })
        .then(r => r.ok ? r.json().catch(() => ({})) : Promise.reject(r))
        .then(() => {
            cerrarModalDireccionEntrega();
            window.location.reload();
        })
        .catch(err => {
            console.error(err);
            alert('Error al guardar dirección.');
        });
    });
</script>
@endpush

