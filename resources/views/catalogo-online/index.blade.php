@extends('layouts.app')

@section('title', 'Catálogo Online')
@section('page-title', '🌐 Catálogo Online')
@section('page-subtitle', 'Visibilidad y precio para el sitio web — filtro rápido por producto')

@php
$breadcrumbs = [
    ['title' => 'Catálogo Online'],
];
@endphp

@section('content')

<form method="GET" action="{{ route('catalogo-online.index') }}" class="card" style="margin-bottom: 16px;">
    <div class="card-body">
        <div class="filtros-bar">
            <div class="filtros-bar-left">
                <input type="text" name="q" value="{{ request('q') }}"
                       placeholder="Código o descripción..."
                       class="form-control" style="flex: 1; min-width: 160px;">
                <select name="categoria_id" class="form-control" style="min-width: 180px;">
                    <option value="">Todas las categorías</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}" {{ (string) request('categoria_id') === (string) $cat->id ? 'selected' : '' }}>
                            {{ $cat->icono }} {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
                <input type="number" name="precio_min" value="{{ request('precio_min') }}" step="0.01" min="0"
                       placeholder="Precio mín." class="form-control" style="max-width: 120px;">
                <input type="number" name="precio_max" value="{{ request('precio_max') }}" step="0.01" min="0"
                       placeholder="Precio máx." class="form-control" style="max-width: 120px;">
                <button type="submit" class="btn btn-primary">🔍 Filtrar</button>
                @if($hayFiltros ?? false)
                    <a href="{{ route('catalogo-online.index') }}" class="btn btn-light">✕ Limpiar</a>
                @endif
            </div>
        </div>
        <p class="form-hint" style="margin: 12px 0 0;">Los cambios de visibilidad se hacen por producto con <strong>Configurar</strong>. Las URLs de la API están en <strong>API</strong>. La API pública lista solo productos <strong>activos</strong> y marcados como visibles en catálogo.</p>
    </div>
</form>

<div class="catalogo-online-grid">
    @forelse($productos as $p)
        @php
            $thumb = $p->imagenes_urls[0] ?? null;
            $urlProductoApi = $p->urlApiCatalogoOnline();
        @endphp
        <div class="card catalogo-online-card">
            <div class="catalogo-online-thumb-wrap">
                @if($thumb)
                    <img src="{{ $thumb }}" alt="" class="catalogo-online-thumb" loading="lazy">
                @else
                    <div class="catalogo-online-thumb catalogo-online-thumb--empty">📷</div>
                @endif
            </div>
            <div class="card-body" style="padding-top: 12px;">
                <div class="text-mono fw-600" style="font-size: 13px;">{{ $p->codigo }}</div>
                <div class="fw-600" style="font-size: 14px; margin-top: 4px; line-height: 1.35;">{{ $p->nombre }}</div>
                @if($p->categoria)
                    <div style="margin-top: 8px;">
                        <span class="badge" style="background: {{ $p->categoria->color }}20; color: {{ $p->categoria->color }};">
                            {{ $p->categoria->icono }} {{ $p->categoria->nombre }}
                        </span>
                    </div>
                @else
                    <div class="text-muted" style="font-size: 12px; margin-top: 6px;">Sin categoría</div>
                @endif
                <div class="catalogo-online-precio text-mono" style="margin-top: 10px;">
                    ${{ number_format($p->precio_venta, 2, '.', ',') }}
                </div>
                <div style="margin-top: 10px; display: flex; flex-wrap: wrap; gap: 6px;">
                    @if($p->catalogo_online_visible)
                        <span class="badge badge-success">Visible en catálogo</span>
                    @else
                        <span class="badge" style="background: var(--color-gray-200); color: var(--color-gray-700);">No publicado</span>
                    @endif
                    @if($p->catalogo_online_visible)
                        @if($p->catalogo_online_mostrar_precio)
                            <span class="badge badge-info">Con precio</span>
                        @else
                            <span class="badge badge-warning">Sin precio</span>
                        @endif
                    @endif
                </div>
                <div class="catalogo-online-actions" style="margin-top: 14px; display: flex; gap: 8px; flex-wrap: wrap;">
                    <button type="button"
                            class="btn btn-primary btn-sm js-catalogo-config"
                            data-producto-id="{{ $p->id }}"
                            data-producto-codigo="{{ e($p->codigo) }}"
                            data-visible="{{ $p->catalogo_online_visible ? '1' : '0' }}"
                            data-mostrar-precio="{{ $p->catalogo_online_mostrar_precio ? '1' : '0' }}">
                        ⚙️ Configurar
                    </button>
                    <a href="{{ route('productos.show', $p->id) }}" class="btn btn-light btn-sm">Ver producto</a>
                    <button type="button"
                            class="btn btn-outline btn-sm js-catalogo-api"
                            data-url-listado="{{ e($urlApiListado) }}"
                            data-url-producto="{{ e($urlProductoApi) }}"
                            data-codigo-ref="{{ e($p->codigo) }}">
                        🔗 API
                    </button>
                </div>
            </div>
        </div>
    @empty
        <div class="card" style="grid-column: 1 / -1;">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-state-icon">📦</div>
                    <div class="empty-state-title">Sin productos con estos filtros</div>
                </div>
            </div>
        </div>
    @endforelse
</div>

@if($productos->hasPages())
    <div style="margin-top: 20px;">
        {{ $productos->links() }}
    </div>
@endif

{{-- Modal configuración --}}
<div id="modalCatalogoOnline" class="modal" style="z-index: 3000;">
    <div class="modal-box" style="max-width: 480px;">
        <div class="modal-header">
            <div class="modal-title" id="modalCatalogoTitulo">Catálogo online</div>
            <button type="button" class="modal-close" onclick="window.cerrarModalCatalogoOnline && window.cerrarModalCatalogoOnline()">✕</button>
        </div>
        <form id="formCatalogoOnline" method="POST" action="">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <p class="text-muted" style="font-size: 13px; margin-top: 0;" id="modalCatalogoSub"></p>
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="catalogo_online_visible" id="co_visible" value="1">
                        Mostrar este producto en el catálogo online
                    </label>
                    <span class="form-hint">Si está desactivado, la API no lo expondrá (aunque el producto siga activo en el ERP).</span>
                </div>
                <div class="form-group">
                    <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="catalogo_online_mostrar_precio" id="co_precio" value="1">
                        Mostrar precio de venta en el catálogo
                    </label>
                    <span class="form-hint">Si lo desactivas, la API devolverá <code>precio_venta: null</code> aunque el producto tenga precio.</span>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end;">
                <button type="button" class="btn btn-light" onclick="window.cerrarModalCatalogoOnline && window.cerrarModalCatalogoOnline()">Cancelar</button>
                <button type="submit" class="btn btn-primary">✓ Guardar</button>
            </div>
        </form>
    </div>
</div>

{{-- Modal URLs API (por producto) --}}
<div id="modalCatalogoApi" class="modal" style="z-index: 3000;">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title" id="modalCatalogoApiTitulo">🌐 API — Catálogo</div>
            <button type="button" class="modal-close" onclick="window.cerrarModalCatalogoApi && window.cerrarModalCatalogoApi()">✕</button>
        </div>
        <div class="modal-body">
            <p class="form-hint" style="margin-top: 0;">
                Consumo desde <strong>promafi.mx/catalogo</strong> (u otro sitio). Requiere
                <code>CATALOGO_API_TOKEN</code> en cabecera <code>Authorization: Bearer …</code> o <code>X-Catalog-Token</code>.
            </p>
            <div class="form-group">
                <label class="form-label">Listado JSON</label>
                <input type="text" id="apiUrlListadoInput" class="form-control text-mono" readonly onclick="this.select()" style="font-size: 12px;">
            </div>
            <div class="form-group" style="margin-bottom: 0;">
                <label class="form-label">Este producto (JSON)</label>
                <input type="text" id="apiUrlProductoInput" class="form-control text-mono" readonly onclick="this.select()" style="font-size: 12px;">
            </div>
        </div>
        <div class="modal-footer" style="display: flex; gap: 12px; justify-content: flex-end;">
            <button type="button" class="btn btn-light" onclick="window.cerrarModalCatalogoApi && window.cerrarModalCatalogoApi()">Cerrar</button>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function() {
    const baseCatalogoUpdate = @json(url('/catalogo-online/productos'));

    function abrirModalCatalogoOnline(id, codigo, visible, mostrarPrecio) {
        var titulo = document.getElementById('modalCatalogoTitulo');
        var sub = document.getElementById('modalCatalogoSub');
        var form = document.getElementById('formCatalogoOnline');
        var modal = document.getElementById('modalCatalogoOnline');
        var coVisible = document.getElementById('co_visible');
        var coPrecio = document.getElementById('co_precio');
        if (!titulo || !form || !modal || !coVisible || !coPrecio) return;

        titulo.textContent = 'Catálogo online — ' + codigo;
        if (sub) sub.textContent = 'Producto ' + codigo;
        form.action = baseCatalogoUpdate + '/' + id;
        coVisible.checked = !!visible;
        coPrecio.checked = mostrarPrecio !== false;
        modal.classList.add('show');
    }

    function cerrarModalCatalogoOnline() {
        var modal = document.getElementById('modalCatalogoOnline');
        if (modal) modal.classList.remove('show');
    }

    function abrirModalCatalogoApi(urlListado, urlProducto, codigoRef) {
        var modal = document.getElementById('modalCatalogoApi');
        var titulo = document.getElementById('modalCatalogoApiTitulo');
        var inList = document.getElementById('apiUrlListadoInput');
        var inProd = document.getElementById('apiUrlProductoInput');
        if (!modal || !inList || !inProd) return;

        if (titulo) titulo.textContent = '🌐 API — ' + (codigoRef || '');
        inList.value = urlListado || '';
        inProd.value = urlProducto || '';
        modal.classList.add('show');
    }

    function cerrarModalCatalogoApi() {
        var modal = document.getElementById('modalCatalogoApi');
        if (modal) modal.classList.remove('show');
    }

    window.cerrarModalCatalogoOnline = cerrarModalCatalogoOnline;
    window.cerrarModalCatalogoApi = cerrarModalCatalogoApi;

    document.addEventListener('click', function(e) {
        var btnCfg = e.target.closest('.js-catalogo-config');
        if (btnCfg) {
            e.preventDefault();
            var id = parseInt(btnCfg.getAttribute('data-producto-id'), 10);
            var codigo = btnCfg.getAttribute('data-producto-codigo') || '';
            var visible = btnCfg.getAttribute('data-visible') === '1';
            var mostrarPrecio = btnCfg.getAttribute('data-mostrar-precio') === '1';
            abrirModalCatalogoOnline(id, codigo, visible, mostrarPrecio);
            return;
        }

        var btnApi = e.target.closest('.js-catalogo-api');
        if (btnApi) {
            e.preventDefault();
            var uList = btnApi.getAttribute('data-url-listado') || '';
            var uProd = btnApi.getAttribute('data-url-producto') || '';
            var cref = btnApi.getAttribute('data-codigo-ref') || '';
            abrirModalCatalogoApi(uList, uProd, cref);
            return;
        }

        var modalCfg = document.getElementById('modalCatalogoOnline');
        var modalApi = document.getElementById('modalCatalogoApi');
        if (modalCfg && modalCfg.classList.contains('show') && e.target === modalCfg) {
            cerrarModalCatalogoOnline();
        }
        if (modalApi && modalApi.classList.contains('show') && e.target === modalApi) {
            cerrarModalCatalogoApi();
        }
    });
})();
</script>
@endpush
