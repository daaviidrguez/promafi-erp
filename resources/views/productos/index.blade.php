@extends('layouts.app')

@section('title', 'Productos')
@section('page-title', '📦 Productos')
@section('page-subtitle', 'Catálogo de productos y servicios')
@section('page-actions')
    <div style="display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;">
        @can('productos.importar')
        <a href="{{ route('productos.plantilla') }}" class="btn btn-light">📄 Plantilla</a>
        <button type="button" class="btn btn-light" onclick="toggleImportarProductos()">📥 Importar</button>
        @endcan
        <a href="{{ route('productos.create') }}" class="btn btn-primary">➕ Nuevo Producto</a>
    </div>
@endsection

@php
$breadcrumbs = [
    ['title' => 'Productos']
];
$qsBase = request()->except('page');
$sortLink = function (string $col, string $d) use ($qsBase) {
    return route('productos.index', array_merge($qsBase, ['sort' => $col, 'dir' => $d]));
};
$isSorted = fn (string $col) => ($sort ?? 'nombre') === $col;
$dirAsc = ($dir ?? 'asc') === 'asc';
@endphp

@section('content')

@can('productos.importar')
<div id="formImportarProductos" class="card" style="display:none; margin-bottom:16px;">
    <div class="card-body">
        <p class="text-muted" style="margin:0 0 12px; font-size:13px;">
            Usa la <strong>plantilla</strong> o el Excel exportado desde Catálogo Truper.
            Columnas: <strong>codigo</strong>, <strong>nombre</strong>, <strong>marca</strong>, <strong>descripcion</strong>,
            <strong>clave_sat</strong>, <strong>clave_unidad_sat</strong>, <strong>unidad</strong>,
            <strong>objeto_impuesto</strong>, <strong>tipo_impuesto</strong>, <strong>tipo_factor</strong>, <strong>tasa_iva</strong>,
            <strong>costo</strong>, <strong>precio_venta</strong>, <strong>precio_mayoreo</strong>, <strong>precio_minimo</strong>,
            <strong>stock_minimo</strong>, <strong>controla_inventario</strong>, <strong>aplica_iva</strong>, <strong>activo</strong>
            (1/0 en booleanos). Si el código ya existe se <strong>actualiza</strong> (no borra el catálogo ni modifica el stock).
            Se sube en bloques de 500.
        </p>
        <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="file" id="productosExcelInput" accept=".xlsx,.xls,.csv" class="form-control" style="max-width:360px;">
            <button type="button" id="productosImportBtn" class="btn btn-primary" onclick="iniciarImportProductos()">Subir e importar</button>
            <a href="{{ route('productos.plantilla') }}" class="btn btn-light">📄 Descargar plantilla</a>
        </div>
        <div id="productosImportError" class="alert alert-danger" style="display:none;margin-top:12px;"></div>
    </div>
</div>

<div id="productosProgressOverlay" style="display:none; position:fixed; inset:0; background:rgba(15,23,42,0.55); z-index:9999; align-items:center; justify-content:center;">
    <div style="background:#fff; border-radius:12px; padding:28px 32px; width:min(440px, 92vw); box-shadow:0 20px 50px rgba(0,0,0,.25);">
        <div style="font-size:17px; font-weight:700; margin-bottom:6px;">Importando productos</div>
        <div id="productosProgressLabel" class="text-muted" style="font-size:13px; margin-bottom:14px;">Preparando archivo…</div>
        <div style="height:12px; background:#e5e7eb; border-radius:999px; overflow:hidden;">
            <div id="productosProgressBar" style="height:100%; width:0%; background:var(--color-primary, #1e3a5f); transition:width .2s ease;"></div>
        </div>
        <div style="display:flex; justify-content:space-between; margin-top:10px; font-size:13px; font-variant-numeric:tabular-nums;">
            <span id="productosProgressCount">0 / 0</span>
            <span id="productosProgressPct">0%</span>
        </div>
        <div id="productosProgressStats" class="text-muted" style="font-size:12px; margin-top:10px;">No cierres ni cambies de página.</div>
    </div>
</div>
@endcan

<form method="GET" action="{{ route('productos.index') }}" id="form-productos-filtros">
    <input type="hidden" name="sort" value="{{ $sort ?? 'nombre' }}">
    <input type="hidden" name="dir" value="{{ $dir ?? 'asc' }}">

{{-- Búsqueda + Acción --}}
<div class="card">
    <div class="card-body">
        <div class="filtros-bar">
            <div class="filtros-bar-left">
                <input type="text" name="search" value="{{ $search ?? '' }}"
                       placeholder="Buscar producto..." class="form-control"
                       style="flex: 1; min-width: 180px;">
                <select name="categoria_id" class="form-control" style="min-width: 160px;">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ ($categoria_id ?? '') == $cat->id ? 'selected' : '' }}>
                            @if($cat->parent)
                                {{ $cat->parent->nombre }} › {{ $cat->nombre }}
                            @else
                                {{ $cat->nombre }}
                            @endif
                        </option>
                    @endforeach
                </select>
                <button type="submit"
                        style="padding: 9px 20px; background: var(--color-primary); color: #fff; border: none; border-radius: var(--radius-md); font-weight: 600; cursor: pointer;">
                    🔍 Buscar
                </button>
                @if($hayFiltros ?? false)
                <a href="{{ route('productos.index') }}"
                   style="padding: 9px 16px; border: 1.5px solid var(--color-gray-300); border-radius: var(--radius-md); color: var(--color-gray-600); font-weight: 600;">
                    ✕ Limpiar todo
                </a>
                @endif
            </div>
        </div>
    </div>
</div>

@if($mostrarTablaFiltros ?? true)
{{-- Tabla con orden y filtros por columna --}}
<div class="table-container">
    <table class="table-productos-filtros">
        <thead>
            <tr>
                <th>
                    <div class="th-sort-title">Código</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('codigo', 'asc') }}" class="{{ $isSorted('codigo') && $dirAsc ? 'active' : '' }}" title="A → Z">A→Z</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('codigo', 'desc') }}" class="{{ $isSorted('codigo') && !$dirAsc ? 'active' : '' }}" title="Z → A">Z→A</a>
                    </div>
                </th>
                <th>
                    <div class="th-sort-title">Producto</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('nombre', 'asc') }}" class="{{ $isSorted('nombre') && $dirAsc ? 'active' : '' }}">A→Z</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('nombre', 'desc') }}" class="{{ $isSorted('nombre') && !$dirAsc ? 'active' : '' }}">Z→A</a>
                    </div>
                </th>
                <th>
                    <div class="th-sort-title">Código SAT</div>
                </th>
                <th>
                    <div class="th-sort-title">Categoría</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('categoria', 'asc') }}" class="{{ $isSorted('categoria') && $dirAsc ? 'active' : '' }}">A→Z</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('categoria', 'desc') }}" class="{{ $isSorted('categoria') && !$dirAsc ? 'active' : '' }}">Z→A</a>
                    </div>
                </th>
                <th class="td-right">
                    <div class="th-sort-title">Precio</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('precio_venta', 'asc') }}" class="{{ $isSorted('precio_venta') && $dirAsc ? 'active' : '' }}">↑ Menor</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('precio_venta', 'desc') }}" class="{{ $isSorted('precio_venta') && !$dirAsc ? 'active' : '' }}">↓ Mayor</a>
                    </div>
                </th>
                <th class="td-center">
                    <div class="th-sort-title">Stock</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('stock', 'asc') }}" class="{{ $isSorted('stock') && $dirAsc ? 'active' : '' }}">↑ Menor</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('stock', 'desc') }}" class="{{ $isSorted('stock') && !$dirAsc ? 'active' : '' }}">↓ Mayor</a>
                    </div>
                </th>
                <th class="td-center">
                    <div class="th-sort-title">Estado</div>
                    <div class="th-sort-links">
                        <a href="{{ $sortLink('activo', 'desc') }}" class="{{ $isSorted('activo') && !$dirAsc ? 'active' : '' }}">Activos primero</a>
                        <span class="th-sort-sep">|</span>
                        <a href="{{ $sortLink('activo', 'asc') }}" class="{{ $isSorted('activo') && $dirAsc ? 'active' : '' }}">Inactivos primero</a>
                    </div>
                </th>
                <th class="td-actions">
                    <div class="th-sort-title">Acciones</div>
                    <button type="submit" class="btn-col-filter" title="Aplicar filtros de columnas">✓ Filtros</button>
                </th>
            </tr>
            <tr class="tr-filtros-columna">
                <th>
                    <input type="text" name="f_codigo" value="{{ $fCodigo ?? '' }}" class="form-control input-col-filter" placeholder="Contiene…">
                </th>
                <th>
                    <input type="text" name="f_nombre" value="{{ $fNombre ?? '' }}" class="form-control input-col-filter" placeholder="Nombre o desc.">
                </th>
                <th></th>
                <th>
                    <select name="f_categoria_col" class="form-control input-col-filter">
                        <option value="">Todas</option>
                        <option value="sin" {{ ($fCategoriaCol ?? '') === 'sin' ? 'selected' : '' }}>Sin categoría</option>
                        @foreach($categorias as $cat)
                            <option value="{{ $cat->id }}" {{ (string)($fCategoriaCol ?? '') === (string)$cat->id ? 'selected' : '' }}>
                                @if($cat->parent)
                                    {{ $cat->parent->nombre }} › {{ $cat->nombre }}
                                @else
                                    {{ $cat->nombre }}
                                @endif
                            </option>
                        @endforeach
                    </select>
                </th>
                <th class="td-right">
                    <input type="number" name="f_precio_min" value="{{ $fPrecioMin ?? '' }}" class="form-control input-col-filter" placeholder="Mín" step="0.01" min="0" style="margin-bottom: 4px;">
                    <input type="number" name="f_precio_max" value="{{ $fPrecioMax ?? '' }}" class="form-control input-col-filter" placeholder="Máx" step="0.01" min="0">
                </th>
                <th class="td-center">
                    <select name="f_stock" class="form-control input-col-filter">
                        <option value="">Todos</option>
                        <option value="na" {{ ($fStock ?? '') === 'na' ? 'selected' : '' }}>N/A (sin inv.)</option>
                        <option value="inventario" {{ ($fStock ?? '') === 'inventario' ? 'selected' : '' }}>Con inventario</option>
                        <option value="bajo" {{ ($fStock ?? '') === 'bajo' ? 'selected' : '' }}>Bajo mínimo ⚠</option>
                    </select>
                </th>
                <th class="td-center">
                    <select name="f_activo" class="form-control input-col-filter">
                        <option value="">Todos</option>
                        <option value="1" {{ ($fActivo ?? '') === '1' ? 'selected' : '' }}>Solo activos</option>
                        <option value="0" {{ ($fActivo ?? '') === '0' ? 'selected' : '' }}>Solo inactivos</option>
                    </select>
                </th>
                <th class="td-actions"></th>
            </tr>
        </thead>
        <tbody>
            @forelse($productos as $producto)
            <tr>
                <td>
                    <span class="producto-row-code">{{ $producto->codigo }}</span>
                </td>
                <td>
                    <div class="fw-600" style="color: var(--color-primary);">{{ $producto->nombre }}</div>
                    @if($producto->descripcion)
                        <div class="text-muted" style="font-size: 12px;">
                            {{ \Str::limit($producto->descripcion, 55) }}
                        </div>
                    @endif
                </td>
                <td>
                    <div class="text-mono fw-600">{{ $producto->clave_sat }}</div>
                    @if($producto->claveProdServicio)
                        <div class="text-muted" style="font-size: 12px;">
                            {{ \Str::limit($producto->claveProdServicio->descripcion, 55) }}
                        </div>
                    @endif
                </td>
                <td>
                    @if($producto->categoria)
                        <div class="producto-cat-cell" style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px;">
                            @if($producto->categoria->parent)
                                <span class="badge" style="background: {{ $producto->categoria->parent->color }}20; color: {{ $producto->categoria->parent->color }}; font-size: 11px;">
                                    {{ $producto->categoria->parent->icono }} {{ $producto->categoria->parent->nombre }}
                                </span>
                                <span class="text-muted" style="font-size: 12px;" aria-hidden="true">›</span>
                            @endif
                            <span class="badge" style="background: {{ $producto->categoria->color }}20; color: {{ $producto->categoria->color }};">
                                {{ $producto->categoria->icono }} {{ $producto->categoria->nombre }}
                            </span>
                        </div>
                    @else
                        <span class="text-muted">Sin categoría</span>
                    @endif
                </td>
                <td class="td-right text-mono fw-600">
                    ${{ number_format($producto->precio_venta, 2, '.', ',') }}
                </td>
                <td class="td-center">
                    @if($producto->controla_inventario)
                        <span class="fw-600"
                              style="color: {{ $producto->bajoEnStock() ? 'var(--color-danger)' : 'var(--color-success)' }};">
                            {{ number_format($producto->stock, 0) }}
                            @if($producto->bajoEnStock()) ⚠ @endif
                        </span>
                    @else
                        <span class="text-muted">N/A</span>
                    @endif
                </td>
                <td class="td-center">
                    @if($producto->activo)
                        <span class="badge badge-success">✓ Activo</span>
                    @else
                        <span class="badge badge-danger">✗ Inactivo</span>
                    @endif
                </td>
                <td class="td-actions">
                    <div style="display: flex; gap: 8px; justify-content: center;">
                        <a href="{{ route('productos.show', $producto->id) }}"
                           class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                        <a href="{{ route('productos.edit', $producto->id) }}"
                           class="btn btn-warning btn-sm btn-icon" title="Editar">✏️</a>
                    </div>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="8" style="padding: 48px 24px; text-align: center;">
                    <div class="empty-state-icon" style="font-size: 2.5rem;">📦</div>
                    <div style="font-weight: 600; margin-top: 12px;">No hay productos que coincidan</div>
                    <div class="text-muted" style="margin-top: 8px;">Ajusta la búsqueda o los filtros por columna</div>
                    <div style="margin-top: 20px;">
                        @if($hayFiltros ?? false)
                        <a href="{{ route('productos.index') }}" class="btn btn-secondary">Limpiar filtros</a>
                        @endif
                        <a href="{{ route('productos.create') }}" class="btn btn-primary" style="margin-left: 8px;">➕ Nuevo producto</a>
                    </div>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
    @if($productos->isNotEmpty())
    <div style="padding: 16px 20px; border-top: 1px solid var(--color-gray-100);">
        {{ $productos->withQueryString()->links() }}
    </div>
    @endif
</div>
@else
{{-- Catálogo vacío (sin productos en el sistema) --}}
<div class="table-container">
    <div class="empty-state">
        <div class="empty-state-icon">📦</div>
        <div class="empty-state-title">No hay productos registrados</div>
        <div class="empty-state-text">Crea un producto o importa el Excel exportado desde Catálogo Truper (plantilla Productos).</div>
        <div style="margin-top: 20px; display:flex; gap:8px; flex-wrap:wrap; justify-content:center;">
            @can('productos.importar')
            <button type="button" class="btn btn-light" onclick="toggleImportarProductos(true)">📥 Importar Excel</button>
            @endcan
            <a href="{{ route('productos.create') }}" class="btn btn-primary">➕ Crear Primer Producto</a>
        </div>
    </div>
</div>
@endif
</form>

@push('styles')
<style>
.table-container .table-productos-filtros { min-width: 640px; }
.table-productos-filtros thead th {
    vertical-align: top;
    padding: 10px 8px;
}
.th-sort-title {
    font-weight: 600;
    margin-bottom: 6px;
}
.th-sort-links {
    font-size: 11px;
    font-weight: 500;
}
.th-sort-links a {
    color: var(--color-primary);
    text-decoration: none;
}
.th-sort-links a:hover { text-decoration: underline; }
.th-sort-links a.active {
    font-weight: 700;
    text-decoration: underline;
}
.th-sort-sep { color: var(--color-gray-400); margin: 0 2px; }
.tr-filtros-columna th {
    background: var(--color-gray-50, #f8f9fa);
    padding-top: 8px;
    padding-bottom: 10px;
}
.input-col-filter {
    font-size: 12px !important;
    padding: 6px 8px !important;
    min-width: 0;
    width: 100%;
    max-width: 140px;
}
.tr-filtros-columna .td-right .input-col-filter { max-width: 88px; margin-left: auto; display: block; }
.tr-filtros-columna .td-center .input-col-filter { max-width: 120px; margin: 0 auto; }
.btn-col-filter {
    font-size: 11px;
    padding: 6px 10px;
    margin-top: 4px;
    background: var(--color-primary);
    color: #fff;
    border: none;
    border-radius: var(--radius-md);
    cursor: pointer;
    font-weight: 600;
}
.btn-col-filter:hover { opacity: 0.92; }
</style>
@endpush

@can('productos.importar')
@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
<script>
(function () {
    const LOTE = 500;
    const URL_LOTE = @json(route('productos.importar-lote'));
    const CSRF = document.querySelector('meta[name="csrf-token"]')?.content || '';

    window.toggleImportarProductos = function (forceShow) {
        const el = document.getElementById('formImportarProductos');
        if (!el) return;
        if (forceShow === true) {
            el.style.display = 'block';
            return;
        }
        el.style.display = el.style.display === 'none' ? 'block' : 'none';
    };

    function normKey(k) {
        return String(k || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]/g, '');
    }

    function pick(row, keys) {
        const map = {};
        Object.keys(row || {}).forEach(function (k) { map[normKey(k)] = row[k]; });
        for (let i = 0; i < keys.length; i++) {
            const t = normKey(keys[i]);
            if (map[t] !== undefined && map[t] !== null && map[t] !== '') return map[t];
        }
        return '';
    }

    function toNum(v) {
        if (v === null || v === undefined || v === '') return 0;
        if (typeof v === 'number') return v;
        const s = String(v).replace(/[$,\s]/g, '');
        const n = parseFloat(s);
        return Number.isFinite(n) ? n : 0;
    }

    function toCodigo(v) {
        if (v === null || v === undefined || v === '') return '';
        if (typeof v === 'number') return String(Math.trunc(v));
        return String(v).trim();
    }

    function toBool(v, def) {
        if (v === null || v === undefined || v === '') return def ? 1 : 0;
        if (typeof v === 'boolean') return v ? 1 : 0;
        if (typeof v === 'number') return v === 1 ? 1 : 0;
        const s = String(v).trim().toLowerCase();
        if (['1', 'true', 'si', 'sí', 'yes', 'activo'].indexOf(s) >= 0) return 1;
        if (['0', 'false', 'no', 'inactivo'].indexOf(s) >= 0) return 0;
        return def ? 1 : 0;
    }

    function mapRow(row) {
        return {
            codigo: toCodigo(pick(row, ['codigo', 'code'])),
            nombre: String(pick(row, ['nombre', 'name', 'descripcion']) || '').trim(),
            marca: String(pick(row, ['marca', 'brand']) || '').trim(),
            descripcion: String(pick(row, ['descripcion', 'description']) || '').trim(),
            clave_sat: toCodigo(pick(row, ['clave_sat', 'clavesat', 'codigo_sat'])),
            clave_unidad_sat: String(pick(row, ['clave_unidad_sat', 'claveunidadsat']) || '').trim(),
            unidad: String(pick(row, ['unidad']) || '').trim(),
            objeto_impuesto: String(pick(row, ['objeto_impuesto', 'objetoimpuesto']) || '').trim(),
            tipo_impuesto: String(pick(row, ['tipo_impuesto', 'tipoimpuesto']) || '').trim(),
            tipo_factor: String(pick(row, ['tipo_factor', 'tipofactor']) || '').trim(),
            tasa_iva: toNum(pick(row, ['tasa_iva', 'tasaiva', 'iva'])),
            costo: toNum(pick(row, ['costo', 'cost'])),
            precio_venta: toNum(pick(row, ['precio_venta', 'precioventa', 'venta'])),
            precio_mayoreo: toNum(pick(row, ['precio_mayoreo', 'preciomayoreo'])),
            precio_minimo: toNum(pick(row, ['precio_minimo', 'preciominimo'])),
            stock_minimo: toNum(pick(row, ['stock_minimo', 'stockminimo'])),
            controla_inventario: toBool(pick(row, ['controla_inventario', 'controlainventario']), true),
            aplica_iva: toBool(pick(row, ['aplica_iva', 'aplicaiva']), true),
            activo: toBool(pick(row, ['activo']), true),
        };
    }

    function showError(msg) {
        const el = document.getElementById('productosImportError');
        if (!el) return;
        el.textContent = msg;
        el.style.display = 'block';
    }

    function setProgress(done, total, stats) {
        const pct = total > 0 ? Math.min(100, Math.round((done / total) * 100)) : 0;
        const overlay = document.getElementById('productosProgressOverlay');
        const bar = document.getElementById('productosProgressBar');
        if (overlay) overlay.style.display = 'flex';
        if (bar) bar.style.width = pct + '%';
        document.getElementById('productosProgressCount').textContent = done.toLocaleString() + ' / ' + total.toLocaleString();
        document.getElementById('productosProgressPct').textContent = pct + '%';
        document.getElementById('productosProgressLabel').textContent = done < total
            ? 'Procesando bloque… no cierres ni cambies de página.'
            : 'Importación terminada.';
        if (stats) {
            document.getElementById('productosProgressStats').textContent =
                'Creados: ' + stats.creados.toLocaleString()
                + ' · Actualizados: ' + stats.actualizados.toLocaleString()
                + ' · Omitidos: ' + stats.omitidos.toLocaleString();
        }
    }

    async function enviarLote(items) {
        const res = await fetch(URL_LOTE, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': CSRF,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ items: items }),
        });
        const data = await res.json().catch(function () { return {}; });
        if (!res.ok) {
            const msg = data.message
                || (data.errors ? Object.values(data.errors).flat().join(' ') : null)
                || ('Error HTTP ' + res.status);
            throw new Error(msg);
        }
        return data;
    }

    window.iniciarImportProductos = async function () {
        const input = document.getElementById('productosExcelInput');
        const btn = document.getElementById('productosImportBtn');
        const err = document.getElementById('productosImportError');
        if (err) err.style.display = 'none';

        if (!input || !input.files || !input.files.length) {
            showError('Selecciona un archivo Excel.');
            return;
        }
        if (typeof XLSX === 'undefined') {
            showError('No se pudo cargar la librería Excel. Recarga la página.');
            return;
        }

        btn.disabled = true;
        window.__productosImporting = true;
        window.onbeforeunload = function () {
            return window.__productosImporting ? 'La importación está en curso. Si sales se cancelará.' : undefined;
        };
        setProgress(0, 0, { creados: 0, actualizados: 0, omitidos: 0 });
        document.getElementById('productosProgressLabel').textContent = 'Leyendo Excel…';

        try {
            const buf = await input.files[0].arrayBuffer();
            const wb = XLSX.read(buf, { type: 'array' });
            const sheet = wb.Sheets[wb.SheetNames[0]];
            const rawRows = XLSX.utils.sheet_to_json(sheet, { defval: '' });
            if (!rawRows.length) throw new Error('El Excel no tiene filas de datos.');

            const items = rawRows.map(mapRow).filter(function (r) { return r.codigo || r.nombre; });
            const total = items.length;
            let done = 0;
            const stats = { creados: 0, actualizados: 0, omitidos: 0 };
            setProgress(0, total, stats);

            for (let i = 0; i < total; i += LOTE) {
                const lote = items.slice(i, i + LOTE);
                const result = await enviarLote(lote);
                done += lote.length;
                stats.creados += result.creados || 0;
                stats.actualizados += result.actualizados || 0;
                stats.omitidos += result.omitidos || 0;
                setProgress(done, total, stats);
            }

            window.__productosImporting = false;
            window.onbeforeunload = null;
            window.location.href = @json(route('productos.index'))
                + '?imported=1&c=' + stats.creados + '&a=' + stats.actualizados + '&o=' + stats.omitidos;
        } catch (e) {
            console.error(e);
            window.__productosImporting = false;
            window.onbeforeunload = null;
            document.getElementById('productosProgressOverlay').style.display = 'none';
            showError(e.message || 'Error al importar.');
            btn.disabled = false;
        }
    };
})();
</script>
@endpush
@endcan

@endsection
