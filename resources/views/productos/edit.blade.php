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

<form method="POST" action="{{ route('productos.update', $producto->id) }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div class="producto-create-layout responsive-grid">

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
                        <label class="form-label">Marca</label>
                        <input type="text" name="marca" class="form-control"
                               value="{{ old('marca', $producto->marca) }}" placeholder="Ej: 3M, Truper, Bosch...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descripción</label>
                        <textarea name="descripcion" class="form-control"
                                  rows="3">{{ old('descripcion', $producto->descripcion) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Categoría</label>
                        <select name="categoria_id" id="categoria_id" class="form-control" onchange="actualizarCategoriaPadreEdit()">
                            <option value="">Sin categoría</option>
                            @foreach($categorias as $categoria)
                                <option value="{{ $categoria->id }}"
                                    data-parent-nombre="{{ $categoria->parent?->nombre ?? '' }}"
                                    {{ old('categoria_id', $producto->categoria_id) == $categoria->id ? 'selected' : '' }}>
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

            @php
                $rutasImagenesProducto = $producto->rutasImagenes();
                $slotsLibresImagenes = max(0, 3 - count($rutasImagenesProducto));
            @endphp
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📷 Imagen</div>
                </div>
                <div class="card-body">
                    @if(count($rutasImagenesProducto))
                        <p class="form-hint" style="margin-top:0;">Imágenes actuales. Marca <strong>Quitar</strong> para eliminar del producto.</p>
                        <div class="producto-imagenes-edit-grid">
                            @foreach($rutasImagenesProducto as $idx => $ruta)
                                <div class="producto-imagen-edit-item">
                                    <img src="{{ asset('storage/'.$ruta) }}" alt="" class="producto-imagen-thumb">
                                    <label class="producto-imagen-quitar">
                                        <input type="checkbox" name="quitar_imagen[]" value="{{ $idx }}"> Quitar
                                    </label>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted" style="margin:0 0 12px;">Sin imágenes guardadas.</p>
                    @endif

                    <div class="form-group" style="margin-bottom:0;">
                        <label class="form-label">Agregar imágenes</label>
                        <input type="file"
                               name="imagenes[]"
                               id="imagenesProductoEdit"
                               class="form-control"
                               accept="image/jpeg,image/png,image/gif,image/webp,.jpg,.jpeg,.png,.gif,.webp"
                               multiple>
                        <span class="form-hint">
                            Opcional. Puedes subir hasta <strong>{{ $slotsLibresImagenes }}</strong> imagen(es) nuevas (máx. 3 en total).
                        </span>
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

            <div class="card">
                <div class="card-header">
                    <div class="card-title">🏛️ Datos Fiscales (SAT)</div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px;">
                        <div class="form-group search-box">
                            <label class="form-label">Clave Prod./Serv. <span class="req">*</span></label>
                            <input type="hidden" name="clave_sat" id="clave_sat_hidden" value="{{ $claveSat ?? $producto->clave_sat }}" required>
                            <input type="text" id="clave_sat_input" class="form-control text-mono"
                                   value="{{ $claveSatEtiqueta ?? $producto->clave_sat }}"
                                   placeholder="Escribe clave o descripción..."
                                   autocomplete="off">
                            <div id="claveSatResults" class="autocomplete-results"></div>
                            <span class="form-hint">Escribe para buscar en el catálogo SAT (se muestra clave y nombre)</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Unidad <span class="req">*</span></label>
                            <select id="unidad_medida_select" class="form-control" required>
                                @php
                                    $claveActual = old('clave_unidad_sat', $producto->clave_unidad_sat);
                                    $unidadActual = old('unidad', $producto->unidad);
                                    $estaEnCatalogo = ($unidadesMedida ?? collect())->contains('clave', $claveActual);
                                @endphp
                                @if(!$estaEnCatalogo && $claveActual)
                                    <option value="{{ $claveActual }}" data-descripcion="{{ strlen($unidadActual) > 20 ? substr($unidadActual, 0, 20) : $unidadActual }}" selected>{{ $claveActual }} - {{ $unidadActual }}</option>
                                @endif
                                @foreach($unidadesMedida ?? [] as $u)
                                    <option value="{{ $u->clave }}" data-descripcion="{{ strlen($u->descripcion) > 20 ? substr($u->descripcion, 0, 20) : $u->descripcion }}"
                                        {{ old('clave_unidad_sat', $producto->clave_unidad_sat) == $u->clave ? 'selected' : '' }}>
                                        {{ $u->etiqueta }}
                                    </option>
                                @endforeach
                            </select>
                            <span class="form-hint">Catálogo unidades de medida SAT</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Clave Unidad <span class="req">*</span></label>
                            <input type="text" name="clave_unidad_sat" id="clave_unidad_sat_input" class="form-control text-mono"
                                   value="{{ old('clave_unidad_sat', $producto->clave_unidad_sat) }}" maxlength="3" required readonly
                                   style="background: var(--color-gray-50);">
                            <input type="hidden" name="unidad" id="unidad_input" value="{{ old('unidad', $producto->unidad) }}">
                            <span class="form-hint">Se llena al elegir la unidad</span>
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
                        <label class="form-label">Costo promedio</label>
                        <div class="form-control text-mono" style="background: var(--color-gray-50); font-weight: 600;" readonly tabindex="-1">
                            @if($producto->costo_promedio_mostrar !== null)
                                ${{ number_format($producto->costo_promedio_mostrar, 2, '.', ',') }}
                            @else
                                —
                            @endif
                        </div>
                        <span class="form-hint">Promedio sobre compras recibidas</span>
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
                        <label class="form-label">Stock actual</label>
                        <div style="background:var(--color-gray-50);border:1px solid var(--color-gray-200);border-radius:var(--radius-md);padding:10px 14px;font-weight:600;">{{ number_format($producto->stock, 2) }} {{ $producto->unidad }}</div>
                        <span class="form-hint">Gestionar desde <a href="{{ route('inventario.index') }}">Inventario</a> (entradas/salidas manuales o desde facturación/compras/remisiones)</span>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div class="form-group">
                            <label class="form-label">Stock Mínimo</label>
                            <input type="number" name="stock_minimo" class="form-control"
                                   value="{{ old('stock_minimo', $producto->stock_minimo) }}" min="0" step="0.01">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Stock Máximo</label>
                            <input type="number" name="stock_maximo" class="form-control"
                                   value="{{ old('stock_maximo', $producto->stock_maximo) }}" min="0" step="0.01" placeholder="Opcional">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="controla_inventario" value="1"
                                   {{ old('controla_inventario', $producto->controla_inventario) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;">
                            Controlar inventario
                        </label>
                        <span class="form-hint">Desactivar para servicios o consumibles</span>
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
    @if($errors->has('codigo'))
        window.addEventListener('load', function() {
            alert(@json($errors->first('codigo')));
        });
    @endif

    document.getElementById('codigo').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });

    (function() {
        var inputImg = document.getElementById('imagenesProductoEdit');
        if (!inputImg) return;
        var actuales = {{ count($rutasImagenesProducto) }};
        function maxNuevasPermitidas() {
            var quitando = document.querySelectorAll('input[name="quitar_imagen[]"]:checked').length;
            var restantes = Math.max(0, actuales - quitando);
            return Math.max(0, 3 - restantes);
        }
        inputImg.addEventListener('change', function() {
            var max = maxNuevasPermitidas();
            if (this.files && this.files.length > max) {
                alert('Solo puedes subir hasta ' + max + ' imagen(es) nueva(s) (máximo 3 en total).');
                this.value = '';
            }
        });
        document.querySelectorAll('input[name="quitar_imagen[]"]').forEach(function(cb) {
            cb.addEventListener('change', function() {
                if (inputImg.files && inputImg.files.length > maxNuevasPermitidas()) {
                    inputImg.value = '';
                }
            });
        });
    })();

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

    function actualizarCategoriaPadreEdit() {
        var categoriaSel = document.getElementById('categoria_id');
        var padreInput = document.getElementById('categoria_padre_nombre');
        if (!categoriaSel || !padreInput) return;
        var opt = categoriaSel.options[categoriaSel.selectedIndex];
        var parentNombre = opt ? (opt.getAttribute('data-parent-nombre') || '') : '';
        padreInput.value = parentNombre !== '' ? parentNombre : 'Sin categoría padre';
    }

    actualizarTasaEdit();
    actualizarCategoriaPadreEdit();

    // Unidad de medida: al elegir, precargar clave y nombre
    var sel = document.getElementById('unidad_medida_select');
    var claveUnidadInput = document.getElementById('clave_unidad_sat_input');
    var unidadInput = document.getElementById('unidad_input');
    function syncUnidadDesdeSelect() {
        var opt = sel && sel.options[sel.selectedIndex];
        if (opt) {
            claveUnidadInput.value = opt.value;
            unidadInput.value = opt.getAttribute('data-descripcion') || opt.text;
        }
    }
    if (sel) { sel.addEventListener('change', syncUnidadDesdeSelect); syncUnidadDesdeSelect(); }

    // Autocomplete Clave Prod./Serv. SAT
    var timerClaveSat = null;
    var claveSatInput = document.getElementById('clave_sat_input');
    var claveSatResults = document.getElementById('claveSatResults');
    if (claveSatInput && claveSatResults) {
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
    }
    function buscarClaveSat(q) {
        fetch('{{ route("productos.buscar-clave-sat") }}?q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!claveSatResults) return;
                if (!data.length) {
                    claveSatResults.innerHTML = '<div class="autocomplete-item"><div class="autocomplete-item-name text-muted">Sin resultados</div></div>';
                } else {
                    claveSatResults.innerHTML = data.map(function(item) {
                        var clave = (item.clave || '').replace(/"/g, '&quot;');
                        var etiqueta = (item.clave || '') + ' - ' + (item.descripcion || '');
                        return '<div class="autocomplete-item" data-clave="' + clave + '" data-etiqueta="' + etiqueta.replace(/"/g, '&quot;') + '">' +
                            '<div class="autocomplete-item-name">' + (item.clave || '') + ' - ' + (item.descripcion || '') + '</div>' +
                            '</div>';
                    }).join('');
                    claveSatResults.querySelectorAll('.autocomplete-item').forEach(function(el) {
                        el.addEventListener('click', function() {
                            var hid = document.getElementById('clave_sat_hidden');
                            if (hid) hid.value = this.dataset.clave || '';
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