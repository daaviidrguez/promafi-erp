@extends('layouts.app')

@section('title', 'Nuevo Producto')
@section('page-title', '➕ Nuevo Producto')
@section('page-subtitle', 'Agrega un producto o servicio al catálogo')

@php
$breadcrumbs = [
    ['title' => 'Productos', 'url' => route('productos.index')],
    ['title' => 'Nuevo Producto']
];
@endphp

@section('content')

<form method="POST" action="{{ route('productos.store') }}">
    @csrf

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

        {{-- Columna izquierda --}}
        <div>

            {{-- Información Básica --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📋 Información Básica</div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Código <span class="req">*</span></label>
                            <input type="text" id="codigo" name="codigo" class="form-control text-mono"
                                   value="{{ old('codigo') }}" required style="text-transform: uppercase;">
                            @error('codigo')
                                <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Nombre del Producto <span class="req">*</span></label>
                            <input type="text" name="nombre" class="form-control"
                                   value="{{ old('nombre') }}" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control" rows="3">{{ old('descripcion') }}</textarea>
                    </div>
                        <div class="form-group">
                            <label class="form-label">Categoría</label>
                            <select name="categoria_id" class="form-control">
                                <option value="">Sin categoría</option>
                                @foreach($categorias as $categoria)
                                    <option value="{{ $categoria->id }}"
                                        {{ old('categoria_id') == $categoria->id ? 'selected' : '' }}>
                                        {{ $categoria->icono }} {{ $categoria->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                </div>
            </div>

            {{-- Datos Fiscales SAT --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🏛️ Datos Fiscales (SAT)</div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Clave Prod./Serv. <span class="req">*</span></label>
                            <input type="text" name="clave_sat" class="form-control text-mono"
                                   value="{{ old('clave_sat', '01010101') }}" maxlength="8" required>
                            <span class="form-hint">8 dígitos SAT</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Clave Unidad <span class="req">*</span></label>
                            <input type="text" name="clave_unidad_sat" class="form-control text-mono"
                                   value="{{ old('clave_unidad_sat', 'H87') }}" maxlength="3" required>
                            <span class="form-hint">H87 = Pieza</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unidad <span class="req">*</span></label>
                            <input type="text" name="unidad" class="form-control"
                                   value="{{ old('unidad', 'Pieza') }}" required>
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 16px; margin-top: 16px;">
                        <div class="form-group">
                            <label class="form-label">Objeto del impuesto <span class="req">*</span></label>
                            <select name="objeto_impuesto" class="form-control" required>
                                <option value="01" {{ old('objeto_impuesto', '02') == '01' ? 'selected' : '' }}>01 No objeto</option>
                                <option value="02" {{ old('objeto_impuesto', '02') == '02' ? 'selected' : '' }}>02 Sí objeto</option>
                                <option value="03" {{ old('objeto_impuesto', '02') == '03' ? 'selected' : '' }}>03 Sí objeto y no obligado al desglose</option>
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
                            <select name="tipo_factor" id="tipo_factor_create" class="form-control" onchange="actualizarTasaCreate()">
                                <option value="Tasa" {{ old('tipo_factor', 'Tasa') == 'Tasa' ? 'selected' : '' }}>Tasa</option>
                                <option value="Exento" {{ old('tipo_factor') == 'Exento' ? 'selected' : '' }}>Exento</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Tasa <span class="req">*</span></label>
                            <select name="tasa_iva" id="tasa_iva_create" class="form-control" onchange="actualizarPrecioIva()">
                                <option value="0.160000" {{ old('tasa_iva', '0.16') == '0.16' || old('tasa_iva') == '0.160000' ? 'selected' : '' }}>0.160000 (16%)</option>
                                <option value="0.080000" {{ old('tasa_iva') == '0.08' || old('tasa_iva') == '0.080000' ? 'selected' : '' }}>0.080000 (8%)</option>
                                <option value="0.000000" {{ old('tasa_iva') == '0' || old('tasa_iva') == '0.000000' ? 'selected' : '' }}>0.000000 (0%)</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Columna derecha --}}
        <div>

            {{-- Precios --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">💰 Precios</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Precio de Venta <span class="req">*</span></label>
                        <input type="number" id="precio_venta" name="precio_venta" class="form-control"
                               value="{{ old('precio_venta', 0) }}" min="0" step="0.01" required
                               oninput="actualizarPrecioIva()">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Costo</label>
                        <input type="number" name="costo" class="form-control"
                               value="{{ old('costo', 0) }}" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="aplica_iva" value="1" id="aplica_iva_create"
                                   {{ old('aplica_iva', true) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;"
                                   onchange="actualizarPrecioIva()">
                            Aplica IVA (según tasa del producto)
                        </label>
                    </div>
                    <div class="totales-panel">
                        <div class="totales-row">
                            <span>Precio con IVA</span>
                            <span class="monto" id="precioConIvaDisplay">$0.00</span>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Inventario --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📊 Inventario</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Stock Inicial</label>
                        <input type="number" name="stock" class="form-control"
                               value="{{ old('stock', 0) }}" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Stock Mínimo</label>
                        <input type="number" name="stock_minimo" class="form-control"
                               value="{{ old('stock_minimo', 0) }}" min="0" step="0.01">
                        <span class="form-hint">Se generará alerta cuando caiga por debajo</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="controla_inventario" value="1"
                                   {{ old('controla_inventario', true) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;">
                            Controlar inventario
                        </label>
                        <span class="form-hint">Desactivar para servicios o consumibles</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('productos.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Producto</button>
        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
    document.getElementById('codigo').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    function actualizarTasaCreate() {
        const tipoFactor = document.getElementById('tipo_factor_create').value;
        const tasaSelect = document.getElementById('tasa_iva_create');
        if (tipoFactor === 'Exento') {
            tasaSelect.value = '0.000000';
            tasaSelect.disabled = true;
        } else {
            tasaSelect.disabled = false;
        }
        actualizarPrecioIva();
    }

    function actualizarPrecioIva() {
        const precio  = parseFloat(document.getElementById('precio_venta').value) || 0;
        const aplica  = document.getElementById('aplica_iva_create').checked;
        const tipoFactor = document.getElementById('tipo_factor_create').value;
        const tasaVal = document.getElementById('tasa_iva_create').value;
        const tasa = (tipoFactor === 'Exento' || !aplica) ? 0 : parseFloat(tasaVal) || 0;
        const total = precio * (1 + tasa);
        document.getElementById('precioConIvaDisplay').textContent =
            '$' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
    }

    actualizarTasaCreate();
    actualizarPrecioIva();
</script>
@endpush