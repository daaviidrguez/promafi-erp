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
                    <div class="info-row producto-show-meta-line" style="grid-column: 1 / -1; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px 24px; align-items: start; margin-bottom: 8px;">
                        <div>
                            <div class="info-label">Código</div>
                            <div class="info-value text-mono">{{ $producto->codigo }}</div>
                        </div>
                        <div>
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
                        <div>
                            <div class="info-label">Categoría padre</div>
                            <div style="margin-top: 4px;">
                                @if($producto->categoria && $producto->categoria->parent)
                                    <span class="badge" style="background: {{ $producto->categoria->parent->color }}20; color: {{ $producto->categoria->parent->color }};">
                                        {{ $producto->categoria->parent->icono }} {{ $producto->categoria->parent->nombre }}
                                    </span>
                                @else
                                    <span class="text-muted">Sin categoría padre</span>
                                @endif
                            </div>
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Marca</div>
                        <div class="info-value">{{ $producto->marca ?: 'Sin marca' }}</div>
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
                                                    data-proveedor-etiqueta="{{ ($cp->proveedor->nombre_comercial ?: $cp->proveedor->nombre) }}{{ $cp->proveedor->codigo ? ' ('.$cp->proveedor->codigo.')' : '' }}"
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

        {{-- Costos y Precios --}}
        @php
            $tienePrecio = (float) $producto->precio_venta > 0;
            $costoBaseShow = $producto->costo_base_para_precio;
            $fuenteCostoShow = $producto->fuente_costo_para_precio;
            $etiquetaFuenteShow = $producto->etiquetaFuenteCostoParaPrecio();
            $margenShow = $producto->margen;
        @endphp
        <div class="card">
            <div class="card-header">
                <div class="card-title">💰 Costos y Precios</div>
                <button type="button"
                        class="btn btn-primary btn-sm"
                        onclick="abrirModalPreciosCostos()">
                    ✏️ Editar
                </button>
            </div>
            <div class="card-body">
                @if(!$tienePrecio && $costoBaseShow <= 0)
                    <div class="empty-state" style="padding: 18px 12px;">
                        <div class="empty-state-icon">💰</div>
                        <div class="empty-state-title">Sin precios configurados</div>
                        <div class="empty-state-text">Define costo y precio de venta para cotizaciones y facturación.</div>
                    </div>
                @else
                    <div class="info-grid-2">
                        <div class="info-row">
                            <div class="info-label">Costo estándar</div>
                            <div class="info-value text-mono">
                                ${{ number_format((float) $producto->costo, 2, '.', ',') }}
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Costo promedio</div>
                            <div class="info-value text-mono">
                                @if($producto->costo_promedio !== null && (float) $producto->costo_promedio > 0)
                                    ${{ number_format((float) $producto->costo_promedio, 2, '.', ',') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Precio de venta (sin IVA)</div>
                            <div class="info-value text-mono" style="color: var(--color-primary); font-size: 18px;">
                                @if($tienePrecio)
                                    ${{ number_format((float) $producto->precio_venta, 2, '.', ',') }}
                                @else
                                    <span class="text-muted">Sin precio</span>
                                @endif
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Precio con IVA</div>
                            <div class="info-value text-mono" style="color: var(--color-success); font-size: 18px;">
                                @if($tienePrecio)
                                    ${{ number_format($producto->precio_con_iva, 2, '.', ',') }}
                                @else
                                    <span class="text-muted">—</span>
                                @endif
                            </div>
                        </div>
                        @if($tienePrecio && $costoBaseShow > 0)
                        <div class="info-row">
                            <div class="info-label">Margen de utilidad</div>
                            <div class="info-value" style="color: {{ $margenShow > 30 ? 'var(--color-success)' : 'var(--color-warning)' }}; font-size: 18px;">
                                {{ number_format($margenShow, 2) }}%
                            </div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Costo usado para precio</div>
                            <div style="margin-top: 4px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                <span class="text-mono fw-600">${{ number_format($costoBaseShow, 2, '.', ',') }}</span>
                                <span class="badge {{ $fuenteCostoShow === 'promedio' ? 'badge-warning' : 'badge-success' }}">
                                    Usando {{ $etiquetaFuenteShow }}
                                </span>
                            </div>
                        </div>
                        @endif
                    </div>
                    <p class="form-hint" style="margin: 12px 0 0;">
                        El precio usa el mayor entre costo estándar y costo promedio. Se carga en cotizaciones y facturación.
                    </p>
                @endif
            </div>
        </div>

        {{-- Inventario --}}
        <div class="card">
            <div class="card-header">
                <div class="card-title">📦 Inventario</div>
            </div>
            <div class="card-body">
                @if($producto->controla_inventario)
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
                @else
                    <div class="alert alert-info" style="margin-bottom: 0;">
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

{{-- Modal Costos y Precios --}}
@php
    $tasaIvaModal = (($producto->tipo_factor ?? 'Tasa') === 'Exento' || !$producto->aplica_iva)
        ? 0
        : (float) ($producto->tasa_iva ?? 0);
    $tasaIvaPctModal = round($tasaIvaModal * 100, 4);
    $margenInicialModal = round((float) $producto->margen, 2);
@endphp
<div id="modalPreciosCostos" class="modal">
    <div class="modal-box" style="max-width: 720px;">
        <div class="modal-header">
            <div class="modal-title">✏️ Editar Costos y Precios</div>
            <button type="button" class="modal-close" onclick="cerrarModalPreciosCostos()">✕</button>
        </div>

        <form method="POST" action="{{ route('productos.precios.update', $producto->id) }}" id="formPreciosCostos">
            @csrf
            @method('PUT')

            <div class="modal-body">
                <div class="pc-fields">
                    <div class="form-group pc-field" style="margin:0;">
                        <label class="form-label pc-label">Costo estándar (sin IVA) <span class="req">*</span></label>
                        <input type="number" name="costo" id="pc_costo" class="form-control" required
                               min="0" step="0.0001"
                               value="{{ old('costo', number_format((float) $producto->costo, 4, '.', '')) }}">
                        <span class="form-hint">El precio usa el mayor entre este y el costo promedio.</span>
                    </div>
                    <div class="form-group pc-field" style="margin:0;">
                        <label class="form-label pc-label">
                            <span>Costo promedio (sin IVA)</span>
                            <span class="badge badge-warning">Editable</span>
                        </label>
                        <input type="number" name="costo_promedio" id="pc_costo_promedio" class="form-control"
                               min="0" step="0.0001"
                               value="{{ old('costo_promedio', $producto->costo_promedio !== null ? number_format((float) $producto->costo_promedio, 4, '.', '') : '') }}"
                               placeholder="0.0000">
                        <span class="form-hint">Se actualiza con cada recepción. Editable para correcciones.</span>
                    </div>
                    <div class="form-group pc-field" style="margin:0;">
                        <label class="form-label pc-label">% IVA aplicable</label>
                        <input type="text" class="form-control" value="{{ number_format($tasaIvaPctModal, 2, '.', '') }} %" readonly
                               style="background: var(--color-gray-50);" tabindex="-1">
                        <span class="form-hint">Obtenido de la configuración fiscal del producto.</span>
                    </div>
                    <div class="form-group pc-field" style="margin:0;">
                        <label class="form-label pc-label">Margen de utilidad (%) <span class="req">*</span></label>
                        <input type="number" name="margen_utilidad" id="pc_margen" class="form-control" required
                               min="0" max="99.99" step="0.01"
                               value="{{ old('margen_utilidad', number_format($margenInicialModal, 2, '.', '')) }}">
                        <span class="form-hint">Porcentaje de utilidad sobre el precio (margen factorizado).</span>
                    </div>
                    <div class="form-group pc-field" style="margin:0; grid-column: 1 / -1;">
                        <label class="form-label pc-label">
                            <span>Precio base (sin IVA) <span class="req">*</span></span>
                            <span class="badge badge-success">Editable</span>
                        </label>
                        <input type="number" name="precio_venta" id="pc_precio" class="form-control" required
                               min="0" step="0.01"
                               value="{{ old('precio_venta', number_format((float) $producto->precio_venta, 2, '.', '')) }}">
                        <span class="form-hint">Este es el precio que se carga en cotizaciones y facturación. ( Precio de Venta )</span>
                    </div>
                </div>

                <div style="margin-top: 18px; padding: 14px 16px; background: var(--color-gray-50); border: 1px solid var(--color-gray-200); border-radius: var(--radius-md);">
                    <div class="fw-600" style="margin-bottom: 8px;">Cálculo del precio</div>
                    <p class="form-hint" style="margin: 0 0 10px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace; color: #9d174d;">
                        Precio = Costo base ÷ (1 − Margen%)
                    </p>
                    <div class="alert alert-info" style="margin-bottom: 12px; display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                        <span>ℹ️</span>
                        <div style="flex:1;">
                            Costo usado para precio:
                            <strong class="text-mono" id="pc_costo_usado_label">$0.00</strong>
                            <span id="pc_fuente_nombre">(Costo Estándar)</span>
                        </div>
                        <span class="badge badge-warning" id="pc_fuente_badge">Usando Estándar</span>
                    </div>
                    <div style="display:flex; flex-wrap:wrap; gap: 10px 18px; align-items: baseline; font-size: 13px;">
                        <div>Costo base: <strong class="text-mono" id="pc_brk_costo">$0.00</strong></div>
                        <div>+ Margen (<span id="pc_brk_margen_pct">0.00</span>%): <strong class="text-mono" id="pc_brk_margen_monto">$0.00</strong></div>
                        <div>= Precio base: <strong class="text-mono" style="color: var(--color-primary);" id="pc_brk_precio">$0.00</strong>
                            <span class="badge badge-success" style="margin-left:4px;">Sin IVA</span>
                        </div>
                        <div>+ IVA (<span id="pc_brk_iva_pct">{{ number_format($tasaIvaPctModal, 2, '.', '') }}</span>%):
                            <strong class="text-mono" id="pc_brk_iva_monto">$0.00</strong>
                        </div>
                    </div>
                    <div style="margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--color-gray-200);">
                        <div style="font-size: 16px; font-weight: 700; color: var(--color-success);">
                            Precio al público (con IVA): <span class="text-mono" id="pc_precio_publico">$0.00</span>
                        </div>
                        <div class="form-hint" style="margin-top: 4px;">Referencia — el IVA se aplica en cotización/venta según el cliente.</div>
                    </div>
                </div>

                <div class="alert alert-info" style="margin-top: 14px; margin-bottom: 0;">
                    <span>ℹ️</span>
                    <div style="font-size: 13px;">
                        Se guarda el <strong>precio base sin IVA</strong> para clientes exentos, distintas zonas y desglose CFDI.
                    </div>
                </div>
                @if($errors->has('costo') || $errors->has('precio_venta') || $errors->has('costo_promedio') || $errors->has('margen_utilidad'))
                    <div class="alert alert-danger" style="margin-top: 12px; margin-bottom: 0;">
                        <span>⚠️</span>
                        <div>
                            @foreach(['costo','costo_promedio','margen_utilidad','precio_venta'] as $campoErr)
                                @error($campoErr)<div>{{ $message }}</div>@enderror
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-light" onclick="cerrarModalPreciosCostos()">Cancelar</button>
                <button type="submit" class="btn btn-primary">💾 Guardar</button>
            </div>
        </form>
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
                    <div class="search-box" id="pp_proveedor_search_box" style="position: relative;">
                        <input type="text"
                               id="pp_proveedor_buscar"
                               class="form-control"
                               placeholder="Buscar proveedor (mín. 2 letras)…"
                               autocomplete="off">
                        <div id="pp_proveedor_results" class="autocomplete-results"></div>
                    </div>
                    <p class="form-control" id="pp_proveedor_readonly" style="display:none; margin: 0; background: var(--color-gray-50);"></p>
                    <p class="form-hint" id="pp_proveedor_hint" style="margin: 8px 0 0; color: var(--color-gray-600);">
                        Escribe para buscar en el catálogo de proveedores.
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
    var ppProveedorBuscar = document.getElementById('pp_proveedor_buscar');
    var ppProveedorResults = document.getElementById('pp_proveedor_results');
    var ppProveedorReadonly = document.getElementById('pp_proveedor_readonly');
    var ppProveedorSearchBox = document.getElementById('pp_proveedor_search_box');
    var ppProveedorHint = document.getElementById('pp_proveedor_hint');
    var buscarProveedoresUrl = @json(route('productos.buscar-proveedores'));
    var timerProveedor = null;
    var abortProveedor = null;
    var modoEdicion = false;

    function setProveedorSeleccionado(id, etiqueta) {
        ppProveedorHidden.value = String(id ?? '');
        if (ppProveedorBuscar) {
            ppProveedorBuscar.value = etiqueta || '';
        }
        if (ppProveedorResults) {
            ppProveedorResults.classList.remove('show');
        }
    }

    function mostrarBusqueda(activo) {
        if (ppProveedorSearchBox) ppProveedorSearchBox.style.display = activo ? '' : 'none';
        if (ppProveedorReadonly) ppProveedorReadonly.style.display = activo ? 'none' : '';
        if (ppProveedorBuscar) {
            ppProveedorBuscar.required = !!activo;
            if (!activo) ppProveedorBuscar.removeAttribute('required');
        }
    }

    window.abrirModalCodigoProveedorNuevo = function() {
        modoEdicion = false;
        ppId.value = '';
        ppCodigo.value = '';
        titulo.textContent = '➕ Nuevo Código de Proveedor';
        ppProveedorHint.textContent = 'Escribe para buscar en el catálogo de proveedores.';
        mostrarBusqueda(true);
        setProveedorSeleccionado('', '');
        modal.classList.add('show');
        if (ppProveedorBuscar) ppProveedorBuscar.focus();
    };

    window.abrirModalCodigoProveedorEditar = function(btn) {
        modoEdicion = true;
        ppId.value = btn.dataset.id || '';
        ppCodigo.value = btn.dataset.codigo || '';
        var provId = btn.dataset.proveedorId || '';
        var etq = btn.dataset.proveedorEtiqueta || '';
        titulo.textContent = '✏️ Editar Código de Proveedor';
        ppProveedorHint.textContent = 'El proveedor está bloqueado en edición (solo cambia el código).';
        mostrarBusqueda(false);
        if (ppProveedorReadonly) ppProveedorReadonly.textContent = etq || ('Proveedor #' + provId);
        setProveedorSeleccionado(provId, etq);
        modal.classList.add('show');
    };

    window.cerrarModalCodigoProveedor = function() {
        modal.classList.remove('show');
        if (abortProveedor) abortProveedor.abort();
    };

    function buscarProveedores(q) {
        if (abortProveedor) abortProveedor.abort();
        abortProveedor = new AbortController();
        fetch(buscarProveedoresUrl + '?q=' + encodeURIComponent(q), { signal: abortProveedor.signal })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!ppProveedorResults) return;
                if (!data.length) {
                    ppProveedorResults.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
                } else {
                    ppProveedorResults.innerHTML = data.map(function(item) {
                        var etq = (item.etiqueta || item.nombre || '').replace(/"/g, '&quot;');
                        return '<div class="autocomplete-item" data-id="' + item.id + '" data-etiqueta="' + etq + '">' +
                            '<div class="autocomplete-item-name">' + (item.etiqueta || item.nombre || '') + '</div>' +
                            '</div>';
                    }).join('');
                    ppProveedorResults.querySelectorAll('.autocomplete-item[data-id]').forEach(function(el) {
                        el.addEventListener('click', function() {
                            setProveedorSeleccionado(this.dataset.id, this.dataset.etiqueta);
                        });
                    });
                }
                ppProveedorResults.classList.add('show');
            })
            .catch(function(err) {
                if (err && err.name === 'AbortError') return;
                console.error(err);
            });
    }

    if (ppProveedorBuscar) {
        ppProveedorBuscar.addEventListener('input', function() {
            if (modoEdicion) return;
            clearTimeout(timerProveedor);
            ppProveedorHidden.value = '';
            var q = this.value.trim();
            if (q.length < 2) {
                if (ppProveedorResults) ppProveedorResults.classList.remove('show');
                return;
            }
            timerProveedor = setTimeout(function() { buscarProveedores(q); }, 400);
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('#pp_proveedor_search_box') && ppProveedorResults) {
            ppProveedorResults.classList.remove('show');
        }
    });

    var formPp = document.getElementById('formCodigoProveedor');
    if (formPp) {
        formPp.addEventListener('submit', function(e) {
            if (!ppProveedorHidden.value) {
                e.preventDefault();
                alert('Selecciona un proveedor de la lista.');
            }
        });
    }
})();

(function() {
    var modal = document.getElementById('modalPreciosCostos');
    var inputCosto = document.getElementById('pc_costo');
    var inputPromedio = document.getElementById('pc_costo_promedio');
    var inputMargen = document.getElementById('pc_margen');
    var inputPrecio = document.getElementById('pc_precio');
    var tasaIva = {{ json_encode($tasaIvaModal) }};
    var ultimoOrigen = 'margen'; // 'margen' | 'precio'
    var sincronizando = false;

    function money(n) {
        var v = isFinite(n) ? n : 0;
        return '$' + v.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    function num(el) {
        if (!el) return 0;
        var v = parseFloat(el.value);
        return isFinite(v) ? v : 0;
    }

    function costoBaseYFuente() {
        var est = num(inputCosto);
        var prom = num(inputPromedio);
        if (prom > est) {
            return { costo: prom, fuente: 'promedio', etiqueta: 'Costo Promedio', badge: 'Usando Promedio' };
        }
        return { costo: est, fuente: 'estandar', etiqueta: 'Costo Estándar', badge: 'Usando Estándar' };
    }

    function precioDesdeMargen(costo, margenPct) {
        if (costo <= 0) return 0;
        if (margenPct <= 0) return Math.round(costo * 100) / 100;
        if (margenPct >= 99.99) return Math.round(costo * 100) / 100;
        return Math.round((costo / (1 - (margenPct / 100))) * 100) / 100;
    }

    function margenDesdePrecio(costo, precio) {
        if (precio <= 0 || costo <= 0 || costo >= precio) return 0;
        return Math.round((1 - (costo / precio)) * 10000) / 100;
    }

    function actualizarCalculo() {
        if (sincronizando) return;
        sincronizando = true;

        var info = costoBaseYFuente();
        var costo = info.costo;
        var margen = num(inputMargen);
        var precio = num(inputPrecio);

        if (ultimoOrigen === 'margen') {
            precio = precioDesdeMargen(costo, margen);
            if (inputPrecio) inputPrecio.value = precio.toFixed(2);
        } else {
            margen = margenDesdePrecio(costo, precio);
            if (inputMargen) inputMargen.value = margen.toFixed(2);
        }

        var montoMargen = Math.max(0, precio - costo);
        var montoIva = precio * (tasaIva || 0);
        var publico = precio + montoIva;

        var elCostoUsado = document.getElementById('pc_costo_usado_label');
        var elFuenteNombre = document.getElementById('pc_fuente_nombre');
        var elFuenteBadge = document.getElementById('pc_fuente_badge');
        if (elCostoUsado) elCostoUsado.textContent = money(costo);
        if (elFuenteNombre) elFuenteNombre.textContent = '(' + info.etiqueta + ')';
        if (elFuenteBadge) {
            elFuenteBadge.textContent = info.badge;
            elFuenteBadge.className = 'badge ' + (info.fuente === 'promedio' ? 'badge-warning' : 'badge-success');
        }

        var elBrkCosto = document.getElementById('pc_brk_costo');
        var elBrkMargenPct = document.getElementById('pc_brk_margen_pct');
        var elBrkMargenMonto = document.getElementById('pc_brk_margen_monto');
        var elBrkPrecio = document.getElementById('pc_brk_precio');
        var elBrkIvaMonto = document.getElementById('pc_brk_iva_monto');
        var elPublico = document.getElementById('pc_precio_publico');

        if (elBrkCosto) elBrkCosto.textContent = money(costo);
        if (elBrkMargenPct) elBrkMargenPct.textContent = margen.toFixed(2);
        if (elBrkMargenMonto) elBrkMargenMonto.textContent = money(montoMargen);
        if (elBrkPrecio) elBrkPrecio.textContent = money(precio);
        if (elBrkIvaMonto) elBrkIvaMonto.textContent = money(montoIva);
        if (elPublico) elPublico.textContent = money(publico);

        sincronizando = false;
    }

    function bindOrigen(el, origen) {
        if (!el) return;
        el.addEventListener('input', function() {
            ultimoOrigen = origen;
            actualizarCalculo();
        });
        el.addEventListener('change', function() {
            ultimoOrigen = origen;
            actualizarCalculo();
        });
    }

    bindOrigen(inputCosto, 'margen');
    bindOrigen(inputPromedio, 'margen');
    bindOrigen(inputMargen, 'margen');
    bindOrigen(inputPrecio, 'precio');

    window.abrirModalPreciosCostos = function() {
        if (!modal) return;
        ultimoOrigen = num(inputPrecio) > 0 ? 'precio' : 'margen';
        modal.classList.add('show');
        actualizarCalculo();
        if (inputCosto) inputCosto.focus();
    };

    window.cerrarModalPreciosCostos = function() {
        if (modal) modal.classList.remove('show');
    };

    @if($errors->has('costo') || $errors->has('precio_venta') || $errors->has('costo_promedio') || $errors->has('margen_utilidad'))
    window.addEventListener('load', function() {
        abrirModalPreciosCostos();
    });
    @endif

    actualizarCalculo();
})();
</script>

@push('styles')
<style>
#modalPreciosCostos .pc-fields {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 14px 16px;
    align-items: start;
}
#modalPreciosCostos .pc-field {
    display: flex;
    flex-direction: column;
}
#modalPreciosCostos .pc-label {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: nowrap;
    min-height: 28px;
    margin-bottom: 6px;
    line-height: 1.25;
}
#modalPreciosCostos .pc-label .badge {
    flex-shrink: 0;
}
@media (max-width: 768px) {
    .producto-show-meta-line {
        grid-template-columns: 1fr !important;
    }
    #modalPreciosCostos .pc-fields {
        grid-template-columns: 1fr !important;
    }
}
</style>
@endpush

@endsection