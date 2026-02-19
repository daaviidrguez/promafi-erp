@extends('layouts.app')

@section('title', 'Editar Producto')
@section('page-title', '✏️ Editar Producto')
@section('page-subtitle', $producto->nombre)

@php
$breadcrumbs = [
    ['title' => 'Productos', 'url' => route('productos.index')],
    ['title' => 'Editar Producto']
];
@endphp

@section('content')

<form method="POST" action="{{ route('productos.update', $producto->id) }}">
    @csrf
    @method('PUT')

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

        {{-- Columna izquierda --}}
        <div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">📋 Información Básica</div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Código <span class="req">*</span></label>
                            <input type="text" id="codigo" name="codigo" class="form-control text-mono"
                                   value="{{ old('codigo', $producto->codigo) }}" required
                                   style="text-transform: uppercase;">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre del Producto <span class="req">*</span></label>
                            <input type="text" name="nombre" class="form-control"
                                   value="{{ old('nombre', $producto->nombre) }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control"
                                  rows="3">{{ old('descripcion', $producto->descripcion) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoría</label>
                        <select name="categoria_id" class="form-control">
                            <option value="">Sin categoría</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}"
                                    {{ old('categoria_id', $producto->categoria_id) == $categoria->id ? 'selected' : '' }}>
                                    {{ $categoria->icono }} {{ $categoria->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">🏛️ Datos Fiscales (SAT)</div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Clave Prod./Serv. <span class="req">*</span></label>
                            <input type="text" name="clave_sat" class="form-control text-mono"
                                   value="{{ old('clave_sat', $producto->clave_sat) }}" maxlength="8" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Clave Unidad <span class="req">*</span></label>
                            <input type="text" name="clave_unidad_sat" class="form-control text-mono"
                                   value="{{ old('clave_unidad_sat', $producto->clave_unidad_sat) }}" maxlength="3" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unidad <span class="req">*</span></label>
                            <input type="text" name="unidad" class="form-control"
                                   value="{{ old('unidad', $producto->unidad) }}" required>
                        </div>
                    </div>
                    @php
                        $tasaVal = old('tasa_iva', $producto->tasa_iva);
                        $tasaOption = $tasaVal == 0.08 ? '0.080000' : ($tasaVal == 0 ? '0.000000' : '0.160000');
                    @endphp
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; margin-top: 16px;">
                        <div class="form-group">
                            <label class="form-label">Objeto del impuesto <span class="req">*</span></label>
                            <select name="objeto_impuesto" class="form-control" required>
                                <option value="01" {{ old('objeto_impuesto', $producto->objeto_impuesto ?? '02') == '01' ? 'selected' : '' }}>01 No objeto</option>
                                <option value="02" {{ old('objeto_impuesto', $producto->objeto_impuesto ?? '02') == '02' ? 'selected' : '' }}>02 Sí objeto</option>
                                <option value="03" {{ old('objeto_impuesto', $producto->objeto_impuesto ?? '02') == '03' ? 'selected' : '' }}>03 Sí objeto y no obligado al desglose</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipo de impuesto</label>
                            <select name="tipo_impuesto" class="form-control">
                                <option value="002" selected>IVA</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tipo factor <span class="req">*</span></label>
                            <select name="tipo_factor" id="tipo_factor_edit" class="form-control" onchange="actualizarTasaEdit()">
                                <option value="Tasa" {{ old('tipo_factor', $producto->tipo_factor ?? 'Tasa') == 'Tasa' ? 'selected' : '' }}>Tasa</option>
                                <option value="Exento" {{ old('tipo_factor', $producto->tipo_factor ?? 'Tasa') == 'Exento' ? 'selected' : '' }}>Exento</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tasa <span class="req">*</span></label>
                            <select name="tasa_iva" id="tasa_iva_edit" class="form-control" onchange="actualizarPrecioIva()">
                                <option value="0.160000" {{ old('tasa_iva', $tasaOption) == '0.160000' ? 'selected' : '' }}>0.160000 (16%)</option>
                                <option value="0.080000" {{ old('tasa_iva', $tasaOption) == '0.080000' ? 'selected' : '' }}>0.080000 (8%)</option>
                                <option value="0.000000" {{ old('tasa_iva', $tasaOption) == '0.000000' ? 'selected' : '' }}>0.000000 (0%)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Columna derecha --}}
        <div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">💰 Precios</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Precio de Venta <span class="req">*</span></label>
                        <input type="number" id="precio_venta" name="precio_venta" class="form-control"
                               value="{{ old('precio_venta', $producto->precio_venta) }}" min="0" step="0.01" required
                               oninput="actualizarPrecioIva()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Costo</label>
                        <input type="number" name="costo" class="form-control"
                               value="{{ old('costo', $producto->costo) }}" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="aplica_iva" value="1" id="aplica_iva_edit"
                                   {{ old('aplica_iva', $producto->aplica_iva) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;"
                                   onchange="actualizarPrecioIva()">
                            Aplica IVA (según tasa del producto)
                        </label>
                    </div>
                    <div class="totales-panel">
                        <div class="totales-row">
                            <span>Precio con IVA</span>
                            <span class="monto" id="precioConIvaDisplay">
                                ${{ number_format($producto->precio_con_iva, 2, '.', ',') }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <div class="card-title">📊 Inventario</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Stock Actual</label>
                        <input type="number" name="stock" class="form-control"
                               value="{{ old('stock', $producto->stock) }}" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" class="form-control"
                               value="{{ old('stock_minimo', $producto->stock_minimo) }}" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="controla_inventario" value="1"
                                   {{ old('controla_inventario', $producto->controla_inventario) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;">
                            Controlar inventario
                        </label>
                        <span class="form-hint">Desactivar para servicios</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="activo" value="1"
                                   {{ old('activo', $producto->activo) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;">
                            Producto Activo
                        </label>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('productos.show', $producto->id) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Actualizar Producto</button>
        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
    document.getElementById('codigo').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    function actualizarTasaEdit() {
        const tipoFactor = document.getElementById('tipo_factor_edit').value;
        const tasaSelect = document.getElementById('tasa_iva_edit');
        if (tipoFactor === 'Exento') {
            tasaSelect.value = '0.000000';
            tasaSelect.disabled = true;
        } else {
            tasaSelect.disabled = false;
        }
        actualizarPrecioIva();
    }

    function actualizarPrecioIva() {
        const precio = parseFloat(document.getElementById('precio_venta').value) || 0;
        const aplica = document.getElementById('aplica_iva_edit').checked;
        const tipoFactor = document.getElementById('tipo_factor_edit').value;
        const tasaVal = document.getElementById('tasa_iva_edit').value;
        const tasa = (tipoFactor === 'Exento' || !aplica) ? 0 : parseFloat(tasaVal) || 0;
        const total = precio * (1 + tasa);
        document.getElementById('precioConIvaDisplay').textContent =
            '$' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    actualizarTasaEdit();
</script>
@endpush