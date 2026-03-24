@extends('layouts.app')

@section('title', 'Producto: ' . $producto->nombre)
@section('page-title', $producto->nombre)
@section('page-subtitle', 'Código: ' . $producto->codigo)

@php
$breadcrumbs = [
    ['title' => 'Productos', 'url' => route('productos.index')],
    ['title' => $producto->codigo]
];
@endphp

@section('content')

<div class="producto-show-layout responsive-grid">

    {{-- Columna izquierda --}}
    <div>

        {{-- Información General --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Información del Producto</div>
                <a href="{{ route('productos.edit', $producto->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Código</div>
                        <div class="info-value text-mono">{{ $producto->codigo }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Categoría</div>
                        <div style="margin-top: 4px;">
                            @if($producto->categoria)
                                <span class="badge" style="background: {{ $producto->categoria->color }}20; color: {{ $producto->categoria->color }};">
                                    {{ $producto->categoria->icono }} {{ $producto->categoria->nombre }}
                                </span>
                            @else
                                <span class="text-muted">Sin categoría</span>
                            @endif
                        </div>
                    </div>
                    @if($producto->descripcion)
                    <div class="info-row" style="grid-column: 1 / -1;">
                        <div class="info-label">Descripción</div>
                        <div class="info-value-sm" style="line-height: 1.7;">{{ $producto->descripcion }}</div>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Código de proveedor --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Código Proveedor</div>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        onclick="abrirModalCodigoProveedorNuevo()">
                    ➕ Nuevo
                </button>
            </div>
            <div class="card-body">
                @if($producto->codigosProveedores->count())
                    <div class="table-container table-container--scroll" style="border:none; box-shadow:none;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Proveedor</th>
                                    <th>Código</th>
                                    <th class="td-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($producto->codigosProveedores as $cp)
                                    <tr>
                                        <td>
                                            {{ $cp->proveedor->nombre_comercial ?: $cp->proveedor->nombre }}
                                            @if($cp->proveedor->codigo)
                                                <span class="text-muted" style="font-size: 12px; margin-left: 6px;">({{ $cp->proveedor->codigo }})</span>
                                            @endif
                                        </td>
                                        <td class="text-mono fw-600">{{ $cp->codigo }}</td>
                                        <td class="td-center">
                                            <button type="button"
                                                    class="btn btn-light btn-sm"
                                                    onclick="abrirModalCodigoProveedorEditar(this)"
                                                    data-id="{{ $cp->id }}"
                                                    data-proveedor-id="{{ $cp->proveedor_id }}"
                                                    data-codigo="{{ $cp->codigo }}">
                                                ✏️
                                            </button>
                                            <form method="POST"
                                                  action="{{ route('productos.proveedores-codigo.destroy', [$producto->id, $cp->id]) }}"
                                                  style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="btn btn-danger btn-sm"
                                                        onclick="return confirm('¿Eliminar este código de proveedor?');"
                                                        title="Eliminar">
                                                    🗑️
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="empty-state">
                        <div class="empty-state-icon">📦</div>
                        <div class="empty-state-title">Sin códigos de proveedor</div>
                        <div class="empty-state-text">Agrega el código del producto para cada proveedor.</div>
                    </div>
                @endif
            </div>
        </div>

        {{-- Datos Fiscales --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">🏛️ Datos Fiscales (SAT)</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Clave Prod./Serv.</div>
                        <div class="info-value">{{ $claveSatEtiqueta ?? $producto->clave_sat }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Clave Unidad</div>
                        <div class="info-value text-mono">{{ $producto->clave_unidad_sat }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Unidad de Medida</div>
                        <div class="info-value">{{ $producto->unidad }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Objeto del impuesto</div>
                        <div class="info-value text-mono">
                            @php
                                $objetos = ['01' => '01 No objeto', '02' => '02 Sí objeto', '03' => '03 Sí objeto y no obligado al desglose'];
                            @endphp
                            {{ $objetos[$producto->objeto_impuesto ?? '02'] ?? $producto->objeto_impuesto }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipo de impuesto</div>
                        <div class="info-value">IVA</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tipo factor</div>
                        <div class="info-value">{{ $producto->tipo_factor ?? 'Tasa' }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Tasa</div>
                        <div class="info-value text-mono">{{ number_format((float)($producto->tasa_iva ?? 0), 6, '.', '') }}</div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">IVA (resumen)</div>
                        <div style="margin-top: 4px;">
                            @if(($producto->tipo_factor ?? 'Tasa') === 'Exento' || !$producto->aplica_iva)
                                <span class="badge badge-warning">Exento</span>
                            @else
                                <span class="badge badge-success">✓ IVA {{ number_format(($producto->tasa_iva ?? 0) * 100, 0) }}%</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Precios e Inventario --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">💰 Precios e Inventario</div>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Costo</div>
                        <div class="info-value text-mono">
                            ${{ number_format($producto->costo, 2, '.', ',') }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Costo promedio</div>
                        <div class="info-value text-mono">
                            @if($producto->costo_promedio_mostrar !== null)
                                ${{ number_format($producto->costo_promedio_mostrar, 2, '.', ',') }}
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Precio de Venta</div>
                        <div class="info-value text-mono" style="color: var(--color-primary); font-size: 18px;">
                            ${{ number_format($producto->precio_venta, 2, '.', ',') }}
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Precio con IVA</div>
                        <div class="info-value text-mono" style="color: var(--color-success); font-size: 18px;">
                            ${{ number_format($producto->precio_con_iva, 2, '.', ',') }}
                        </div>
                    </div>
                    @if($producto->costo > 0)
                    <div class="info-row">
                        <div class="info-label">Margen de Ganancia</div>
                        <div class="info-value" style="color: {{ $producto->margen > 30 ? 'var(--color-success)' : 'var(--color-warning)' }}; font-size: 18px;">
                            {{ number_format($producto->margen, 1) }}%
                        </div>
                    </div>
                    @endif
                </div>

                @if($producto->controla_inventario)
                    <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--color-gray-100);">
                        <div class="info-grid-2">
                            <div class="info-row">
                                <div class="info-label">Stock Actual</div>
                                <div class="info-value" style="font-size: 28px; color: {{ $producto->bajoEnStock() ? 'var(--color-danger)' : 'var(--color-success)' }};">
                                    {{ number_format($producto->stock, 0) }}
                                </div>
                            </div>
                            <div class="info-row">
                                <div class="info-label">Stock Mínimo</div>
                                <div class="info-value" style="font-size: 22px;">
                                    {{ number_format($producto->stock_minimo, 0) }}
                                </div>
                            </div>
                            @if($producto->stock_maximo !== null)
                            <div class="info-row">
                                <div class="info-label">Stock Máximo</div>
                                <div class="info-value">{{ number_format($producto->stock_maximo, 0) }}</div>
                            </div>
                            @endif
                        </div>
                        @if($producto->bajoEnStock())
                        <div class="alert alert-danger" style="margin-top: 12px; margin-bottom: 0;">
                            <span>⚠️</span>
                            <div>
                                <div class="fw-600">Stock bajo</div>
                                <div style="font-size: 12px;">Es necesario reabastecer este producto</div>
                            </div>
                        </div>
                        @endif
                        <a href="{{ route('inventario.show-producto', $producto->id) }}" class="btn btn-outline btn-sm" style="margin-top:12px;">📋 Ver movimientos en Inventario</a>
                    </div>
                @else
                    <div class="alert alert-info" style="margin-top: 16px; margin-bottom: 0;">
                        <span>ℹ️</span>
                        <div>No controla inventario — servicio o consumible</div>
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Columna derecha --}}
    <div>

        {{-- Estado y Datos Extra --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📊 Información Adicional</div>
            </div>
            <div class="card-body">
                <div class="info-row">
                    <div class="info-label">Estado</div>
                    <div style="margin-top: 4px;">
                        @if($producto->activo)
                            <span class="badge badge-success">✓ Activo</span>
                        @else
                            <span class="badge badge-danger">✗ Inactivo</span>
                        @endif
                    </div>
                </div>

                @if($producto->codigo_barras)
                <div class="info-row" style="margin-top: 16px;">
                    <div class="info-label">Código de Barras</div>
                    <div class="info-value text-mono">{{ $producto->codigo_barras }}</div>
                </div>
                @endif
            </div>
        </div>

        {{-- Acciones --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">⚡ Acciones Rápidas</div>
            </div>
            <div class="card-body" style="display: flex; flex-direction: column; gap: 10px;">
                <a href="{{ route('productos.edit', $producto->id) }}"
                   class="btn btn-primary w-full">✏️ Editar Producto</a>

                <a href="{{ route('facturas.create') }}?producto_id={{ $producto->id }}"
                   class="btn btn-outline w-full">🧾 Crear Factura</a>

                <form method="POST" action="{{ route('productos.destroy', $producto->id) }}"
                      onsubmit="return confirm('¿Eliminar este producto? Esta acción no se puede deshacer.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar Producto</button>
                </form>
            </div>
        </div>

        {{-- Imágenes del producto --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📷 Imagen</div>
            </div>
            <div class="card-body">
                @if(count($producto->imagenes_urls))
                    <div class="producto-imagenes-grid">
                        @foreach($producto->imagenes_urls as $url)
                            <a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="producto-imagen-link">
                                <img src="{{ $url }}" alt="" class="producto-imagen-thumb" loading="lazy">
                            </a>
                        @endforeach
                    </div>
                @else
                    <div class="empty-state" style="padding: 20px 12px;">
                        <div class="empty-state-icon">📷</div>
                        <div class="empty-state-title">Sin imágenes</div>
                        <div class="empty-state-text">Puedes agregarlas al editar el producto.</div>
                    </div>
                @endif
            </div>
        </div>

    </div>
</div>

{{-- Modal Código de Proveedor --}}
<div id="modalCodigoProveedor" class="modal">
    <div class="modal-box" style="max-width: 560px;">
        <div class="modal-header">
            <div class="modal-title" id="modalCodigoProveedorTitulo">➕ Nuevo Código de Proveedor</div>
            <button class="modal-close" onclick="cerrarModalCodigoProveedor()">✕</button>
        </div>

        <form method="POST" action="{{ route('productos.proveedores-codigo.save', $producto->id) }}" id="formCodigoProveedor">
            @csrf
            <input type="hidden" name="producto_proveedor_id" id="pp_producto_proveedor_id" value="">
            <input type="hidden" name="proveedor_id" id="pp_proveedor_id" value="">

            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Código <span class="req">*</span></label>
                    <input type="text" name="codigo" id="pp_codigo" class="form-control" required>
                </div>

                <div class="form-group" style="margin-top: 12px;">
                    <label class="form-label">Proveedor <span class="req">*</span></label>
                    <select id="pp_proveedor_select" class="form-control" required>
                        @foreach($proveedores as $prov)
                            <option value="{{ $prov->id }}">{{ $prov->nombre_comercial ?: $prov->nombre }} ({{ $prov->codigo ?? '—' }})</option>
                        @endforeach
                    </select>
                    <p class="form-hint" id="pp_proveedor_hint" style="margin: 8px 0 0; color: var(--color-gray-600);">
                        Selecciona el proveedor del catálogo.
                    </p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="cerrarModalCodigoProveedor()">Cancelar</button>
                <button type="submit" class="btn btn-primary">✓ Guardar</button>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('modalCodigoProveedor');
    var titulo = document.getElementById('modalCodigoProveedorTitulo');
    var ppId = document.getElementById('pp_producto_proveedor_id');
    var ppCodigo = document.getElementById('pp_codigo');
    var ppProveedorHidden = document.getElementById('pp_proveedor_id');
    var ppProveedorSelect = document.getElementById('pp_proveedor_select');
    var ppProveedorHint = document.getElementById('pp_proveedor_hint');

    function setProveedorSeleccionado(id) {
        ppProveedorSelect.value = String(id ?? '');
        ppProveedorHidden.value = String(id ?? '');
    }

    window.abrirModalCodigoProveedorNuevo = function() {
        ppId.value = '';
        ppCodigo.value = '';
        titulo.textContent = '➕ Nuevo Código de Proveedor';
        ppProveedorSelect.disabled = false;
        ppProveedorHint.textContent = 'Selecciona el proveedor del catálogo.';

        // Predeterminar al primer proveedor si existe.
        var first = ppProveedorSelect.options[0];
        if (first) {
            setProveedorSeleccionado(first.value);
        } else {
            ppProveedorHidden.value = '';
        }

        modal.classList.add('show');
    };

    window.abrirModalCodigoProveedorEditar = function(btn) {
        ppId.value = btn.dataset.id || '';
        ppCodigo.value = btn.dataset.codigo || '';

        var provId = btn.dataset.proveedorId || '';
        titulo.textContent = '✏️ Editar Código de Proveedor';

        // En edición asumimos que solo cambia el código (no el proveedor).
        ppProveedorSelect.disabled = true;
        ppProveedorHint.textContent = 'El proveedor está bloqueado en edición (solo cambia el código).';

        setProveedorSeleccionado(provId);
        modal.classList.add('show');
    };

    window.cerrarModalCodigoProveedor = function() {
        modal.classList.remove('show');
    };

    // Cuando el usuario cambia el proveedor en modo "Nuevo", sincronizamos el hidden.
    ppProveedorSelect.addEventListener('change', function() {
        if (!ppProveedorSelect.disabled) {
            ppProveedorHidden.value = ppProveedorSelect.value;
        }
    });
})();
</script>

@endsection