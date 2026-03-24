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

<form method="POST" action="{{ route('productos.store') }}" enctype="multipart/form-data">
    @csrf

    <div class="producto-create-layout responsive-grid">

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
                                   value="{{ old('codigo', $codigoConsecutivo ?? '') }}" required style="text-transform: uppercase;">
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
                            <select name="categoria_id" id="categoria_id" class="form-control" onchange="actualizarCategoriaPadreCreate()">
                                <option value="">Sin categoría</option>
                                @foreach($categorias as $categoria)
                                    <option value="{{ $categoria->id }}"
                                        data-parent-nombre="{{ $categoria->parent?->nombre ?? '' }}"
                                        {{ old('categoria_id') == $categoria->id ? 'selected' : '' }}>
                                        {{ $categoria->icono }} {{ $categoria->nombre }}{{ $categoria->parent ? ' (Hija de: ' . $categoria->parent->nombre . ')' : '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Categoría Padre</label>
                            <input type="text" id="categoria_padre_nombre" class="form-control" value="Sin categoría padre" readonly style="background: var(--color-gray-50);">
                        </div>
                </div>
            </div>

            {{-- Imágenes (opcional, máx. 3) --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📷 Imagen</div>
                </div>
                <div class="card-body">
                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Seleccionar archivos</label>
                        <input type="file"
                               name="imagenes[]"
                               id="imagenesProducto"
                               class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp"
                               multiple>
                        <span class="form-hint">Opcional. Hasta <strong>3</strong> imágenes (JPG, PNG, GIF o WebP, máx. 5 MB c/u).</span>
                        @error('imagenes')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                        @foreach ($errors->getMessages() as $field => $messages)
                            @if(\Illuminate\Support\Str::startsWith($field, 'imagenes.'))
                                @foreach($messages as $m)
                                    <span class="form-hint" style="color: var(--color-danger); display:block;">{{ $m }}</span>
                                @endforeach
                            @endif
                        @endforeach
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
                        <div class="form-group search-box">
                            <label class="form-label">Clave Prod./Serv. <span class="req">*</span></label>
                            <input type="hidden" name="clave_sat" id="clave_sat_hidden" value="{{ $claveSatDefault ?? '01010101' }}" required>
                            <input type="text" id="clave_sat_input" class="form-control text-mono"
                                   value="{{ $claveSatEtiqueta ?? $claveSatDefault ?? '01010101' }}"
                                   placeholder="Escribe clave o descripción..."
                                   autocomplete="off">
                            <div id="claveSatResults" class="autocomplete-results"></div>
                            <span class="form-hint">Escribe para buscar en el catálogo SAT (se muestra clave y nombre)</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unidad <span class="req">*</span></label>
                            <select id="unidad_medida_select" class="form-control" required>
                                @foreach($unidadesMedida ?? [] as $u)
                                    <option value="{{ $u->clave }}" data-descripcion="{{ strlen($u->descripcion) > 20 ? substr($u->descripcion, 0, 20) : $u->descripcion }}"
                                        {{ old('clave_unidad_sat', 'H87') == $u->clave ? 'selected' : '' }}>
                                        {{ $u->etiqueta }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="form-hint">Catálogo unidades de medida SAT</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Clave Unidad <span class="req">*</span></label>
                            <input type="text" name="clave_unidad_sat" id="clave_unidad_sat_input" class="form-control text-mono"
                                   value="{{ old('clave_unidad_sat', 'H87') }}" maxlength="3" required readonly
                                   style="background: var(--color-gray-50);">
                            <input type="hidden" name="unidad" id="unidad_input" value="{{ old('unidad', 'Pieza') }}">
                            <span class="form-hint">Se llena al elegir la unidad</span>
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
                        <label class="form-label">Costo promedio</label>
                        <div class="form-control" style="background: var(--color-gray-50); color: var(--color-gray-600);" readonly tabindex="-1">—</div>
                        <span class="form-hint">Promedio sobre compras recibidas (se calcula automáticamente)</span>
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
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" name="stock_minimo" class="form-control"
                                   value="{{ old('stock_minimo', 0) }}" min="0" step="0.01">
                            <span class="form-hint">Alerta cuando el stock caiga por debajo</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Máximo</label>
                            <input type="number" name="stock_maximo" class="form-control"
                                   value="{{ old('stock_maximo') }}" min="0" step="0.01" placeholder="Opcional">
                            <span class="form-hint">Límite de control (opcional)</span>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="controla_inventario" value="1"
                                   {{ old('controla_inventario', true) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;">
                            Controlar inventario
                        </label>
                        <span class="form-hint">El stock se gestiona desde el módulo Inventario (entradas/salidas). Desactivar para servicios o consumibles.</span>
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
    @if($errors->has('codigo'))
        window.addEventListener('load', function() {
            alert(@json($errors->first('codigo')));
        });
    @endif

    document.getElementById('codigo').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    document.getElementById('imagenesProducto')?.addEventListener('change', function() {
        if (this.files && this.files.length > 3) {
            alert('Solo puedes seleccionar hasta 3 imágenes.');
            this.value = '';
        }
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

    function actualizarCategoriaPadreCreate() {
        var categoriaSel = document.getElementById('categoria_id');
        var padreInput = document.getElementById('categoria_padre_nombre');
        if (!categoriaSel || !padreInput) return;
        var opt = categoriaSel.options[categoriaSel.selectedIndex];
        var parentNombre = opt ? (opt.getAttribute('data-parent-nombre') || '') : '';
        padreInput.value = parentNombre !== '' ? parentNombre : 'Sin categoría padre';
    }

    actualizarTasaCreate();
    actualizarPrecioIva();
    actualizarCategoriaPadreCreate();

    // Unidad de medida: al elegir, precargar clave y nombre
    var sel = document.getElementById('unidad_medida_select');
    var claveUnidadInput = document.getElementById('clave_unidad_sat_input');
    var unidadInput = document.getElementById('unidad_input');
    function syncUnidadDesdeSelect() {
        var opt = sel.options[sel.selectedIndex];
        if (opt) {
            claveUnidadInput.value = opt.value;
            unidadInput.value = opt.getAttribute('data-descripcion') || opt.text;
        }
    }
    sel.addEventListener('change', syncUnidadDesdeSelect);
    syncUnidadDesdeSelect();

    // Autocomplete Clave Prod./Serv. SAT
    var timerClaveSat = null;
    var claveSatInput = document.getElementById('clave_sat_input');
    var claveSatResults = document.getElementById('claveSatResults');
    claveSatInput.addEventListener('input', function() {
        clearTimeout(timerClaveSat);
        var q = this.value.trim();
        if (q.length < 2) { claveSatResults.classList.remove('show'); return; }
        timerClaveSat = setTimeout(function() { buscarClaveSat(q); }, 280);
    });
    claveSatInput.addEventListener('focus', function() {
        var v = this.value.trim();
        if (v.length < 2) return;
        var q = v.indexOf(' - ') !== -1 ? v.split(' - ')[0].trim() : v;
        if (q.length >= 2) buscarClaveSat(q);
    });
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.search-box')) claveSatResults.classList.remove('show');
    });
    function buscarClaveSat(q) {
        fetch('{{ route("productos.buscar-clave-sat") }}?q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.length) {
                    claveSatResults.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
                } else {
                    claveSatResults.innerHTML = data.map(function(item) {
                        var desc = (item.descripcion || '').replace(/</g, '&lt;').replace(/"/g, '&quot;');
                        var clave = (item.clave || '').replace(/"/g, '&quot;');
                        var etiqueta = (item.clave || '') + ' - ' + (item.descripcion || '');
                        return '<div class="autocomplete-item" data-clave="' + clave + '" data-etiqueta="' + etiqueta.replace(/"/g, '&quot;') + '">' +
                            '<div class="autocomplete-item-name">' + (item.clave || '') + ' - ' + (item.descripcion || '') + '</div>' +
                            '</div>';
                    }).join('');
                    claveSatResults.querySelectorAll('.autocomplete-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            document.getElementById('clave_sat_hidden').value = this.dataset.clave || '';
                            claveSatInput.value = this.dataset.etiqueta || this.dataset.clave || '';
                            claveSatResults.classList.remove('show');
                            claveSatInput.focus();
                        });
                    });
                }
                claveSatResults.classList.add('show');
            })
            .catch(function(err) { console.error(err); });
    }
</script>
@endpush