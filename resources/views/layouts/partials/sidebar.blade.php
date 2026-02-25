{{-- resources/views/layouts/partials/sidebar.blade.php --}}
@php
    $empresaSidebar = \App\Models\Empresa::principal();
    $logoUrlSidebar = $empresaSidebar && $empresaSidebar->logo_path ? asset('storage/' . $empresaSidebar->logo_path) : asset('assets/imagenes/logo.svg');
    $nombreSidebar = $empresaSidebar ? ($empresaSidebar->nombre_comercial ?? $empresaSidebar->razon_social ?? 'PROMAFI ERP') : 'PROMAFI ERP';
@endphp
<aside class="sidebar">

    {{-- Logo --}}
    <div class="sidebar-logo">
        <div class="sidebar-logo-initials">{{ strtoupper(mb_substr($nombreSidebar, 0, 2)) }}</div>
        <img src="{{ $logoUrlSidebar }}"
             alt="{{ $nombreSidebar }}"
             class="sidebar-logo-img"
             onerror="this.style.display='none'">
    </div>

    {{-- Navegación con Dropdowns --}}
    <nav class="sidebar-nav">

        {{-- Principal (sin dropdown, enlaces directos) --}}
        @can('dashboard.ver')
        <div class="sidebar-section">
            <div class="sidebar-section-title">Principal</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('dashboard') }}"
                       class="sidebar-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                       title="Dashboard">
                        <span class="sidebar-menu-icon">📊</span>
                        <span class="sidebar-menu-text">Dashboard</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('tablero.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('tablero.*') ? 'active' : '' }}"
                       title="Tablero">
                        <span class="sidebar-menu-icon">📈</span>
                        <span class="sidebar-menu-text">Tablero</span>
                    </a>
                </li>
            </ul>
        </div>
        @endcan

        {{-- Facturación (dropdown) --}}
        @php
            $factHasActive = request()->routeIs('catalogos-sat.*') || request()->routeIs('facturas.*') || request()->routeIs('cotizaciones.*') || request()->routeIs('listas-precios.*') || request()->routeIs('complementos.*') || request()->routeIs('remisiones.*') || request()->routeIs('devoluciones.*') || request()->routeIs('notas-credito.*');
        @endphp
        @if(auth()->user()->can('catalogos_sat.ver') || auth()->user()->can('facturas.ver') || auth()->user()->can('cotizaciones.ver') || auth()->user()->can('listas_precios.ver') || auth()->user()->can('complementos.ver') || auth()->user()->can('remisiones.ver') || auth()->user()->can('devoluciones.ver') || auth()->user()->can('notas_credito.ver'))
        <div class="sidebar-dropdown {{ $factHasActive ? 'open' : '' }}">
            <button type="button" class="sidebar-dropdown-trigger {{ $factHasActive ? 'active' : '' }}" data-dropdown="facturacion" title="Facturación">
                <span class="sidebar-menu-icon">🧾</span>
                <span class="sidebar-menu-text">Facturación</span>
                <span class="sidebar-dropdown-chevron">▼</span>
            </button>
            <ul class="sidebar-dropdown-menu">
                @can('catalogos_sat.ver')<li><a href="{{ route('catalogos-sat.index') }}" class="sidebar-menu-link {{ request()->routeIs('catalogos-sat.*') ? 'active' : '' }}" title="Catálogos SAT"><span class="sidebar-menu-icon">📑</span><span class="sidebar-menu-text">Catálogos SAT</span></a></li>@endcan
                @can('facturas.ver')<li><a href="{{ route('facturas.index') }}" class="sidebar-menu-link {{ request()->routeIs('facturas.*') ? 'active' : '' }}" title="Facturas CFDI"><span class="sidebar-menu-icon">📄</span><span class="sidebar-menu-text">Facturas CFDI</span></a></li>@endcan
                @can('cotizaciones.ver')<li><a href="{{ route('cotizaciones.index') }}" class="sidebar-menu-link {{ request()->routeIs('cotizaciones.*') ? 'active' : '' }}" title="Cotizaciones"><span class="sidebar-menu-icon">📋</span><span class="sidebar-menu-text">Cotizaciones</span></a></li>@endcan
                @can('listas_precios.ver')<li><a href="{{ route('listas-precios.index') }}" class="sidebar-menu-link {{ request()->routeIs('listas-precios.*') ? 'active' : '' }}" title="Listas de Precios"><span class="sidebar-menu-icon">💰</span><span class="sidebar-menu-text">Listas de Precios</span></a></li>@endcan
                @can('complementos.ver')<li><a href="{{ route('complementos.index') }}" class="sidebar-menu-link {{ request()->routeIs('complementos.*') ? 'active' : '' }}" title="Complementos Pago"><span class="sidebar-menu-icon">💳</span><span class="sidebar-menu-text">Complementos Pago</span></a></li>@endcan
                @can('remisiones.ver')<li><a href="{{ route('remisiones.index') }}" class="sidebar-menu-link {{ request()->routeIs('remisiones.*') ? 'active' : '' }}" title="Remisiones"><span class="sidebar-menu-icon">🚚</span><span class="sidebar-menu-text">Remisiones</span></a></li>@endcan
                @can('devoluciones.ver')<li><a href="{{ route('devoluciones.index') }}" class="sidebar-menu-link {{ request()->routeIs('devoluciones.*') ? 'active' : '' }}" title="Devoluciones"><span class="sidebar-menu-icon">↩️</span><span class="sidebar-menu-text">Devoluciones</span></a></li>@endcan
                @can('notas_credito.ver')<li><a href="{{ route('notas-credito.index') }}" class="sidebar-menu-link {{ request()->routeIs('notas-credito.*') ? 'active' : '' }}" title="Notas de Crédito"><span class="sidebar-menu-icon">📑</span><span class="sidebar-menu-text">Notas de Crédito</span></a></li>@endcan
            </ul>
        </div>
        @endif

        {{-- Administración (dropdown) --}}
        @can('clientes.ver')
        @php $admHasActive = request()->routeIs('clientes.*'); @endphp
        <div class="sidebar-dropdown {{ $admHasActive ? 'open' : '' }}">
            <button type="button" class="sidebar-dropdown-trigger {{ $admHasActive ? 'active' : '' }}" data-dropdown="admin" title="Administración">
                <span class="sidebar-menu-icon">👥</span>
                <span class="sidebar-menu-text">Administración</span>
                <span class="sidebar-dropdown-chevron">▼</span>
            </button>
            <ul class="sidebar-dropdown-menu">
                <li><a href="{{ route('clientes.index') }}" class="sidebar-menu-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}" title="Clientes"><span class="sidebar-menu-icon">👥</span><span class="sidebar-menu-text">Clientes</span></a></li>
            </ul>
        </div>
        @endcan

        {{-- Catálogos (dropdown) --}}
        @php
            $catHasActive = request()->routeIs('productos.*') || request()->routeIs('inventario.*') || request()->routeIs('categorias.*') || request()->routeIs('sugerencias.*');
        @endphp
        @if(auth()->user()->can('productos.ver') || auth()->user()->can('inventario.ver') || auth()->user()->can('categorias.ver') || auth()->user()->can('sugerencias.ver'))
        <div class="sidebar-dropdown {{ $catHasActive ? 'open' : '' }}">
            <button type="button" class="sidebar-dropdown-trigger {{ $catHasActive ? 'active' : '' }}" data-dropdown="catalogos" title="Catálogos">
                <span class="sidebar-menu-icon">📦</span>
                <span class="sidebar-menu-text">Catálogos</span>
                <span class="sidebar-dropdown-chevron">▼</span>
            </button>
            <ul class="sidebar-dropdown-menu">
                @can('productos.ver')<li><a href="{{ route('productos.index') }}" class="sidebar-menu-link {{ request()->routeIs('productos.*') ? 'active' : '' }}" title="Productos"><span class="sidebar-menu-icon">📦</span><span class="sidebar-menu-text">Productos</span></a></li>@endcan
                @can('inventario.ver')<li><a href="{{ route('inventario.index') }}" class="sidebar-menu-link {{ request()->routeIs('inventario.*') ? 'active' : '' }}" title="Inventario"><span class="sidebar-menu-icon">📊</span><span class="sidebar-menu-text">Inventario</span></a></li>@endcan
                @can('categorias.ver')<li><a href="{{ route('categorias.index') }}" class="sidebar-menu-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}" title="Categorías"><span class="sidebar-menu-icon">🗂️</span><span class="sidebar-menu-text">Categorías</span></a></li>@endcan
                @can('sugerencias.ver')<li><a href="{{ route('sugerencias.index') }}" class="sidebar-menu-link {{ request()->routeIs('sugerencias.*') ? 'active' : '' }}" title="Sugerencias"><span class="sidebar-menu-icon">💡</span><span class="sidebar-menu-text">Sugerencias</span></a></li>@endcan
            </ul>
        </div>
        @endif

        {{-- Compras (dropdown) --}}
        @php
            $compHasActive = request()->routeIs('ordenes-compra.*') || request()->routeIs('cotizaciones-compra.*') || request()->routeIs('proveedores.*') || request()->routeIs('cuentas-por-pagar.*');
        @endphp
        @if(auth()->user()->can('ordenes_compra.ver') || auth()->user()->can('cotizaciones_compra.ver') || auth()->user()->can('proveedores.ver') || auth()->user()->can('cuentas_por_pagar.ver'))
        <div class="sidebar-dropdown {{ $compHasActive ? 'open' : '' }}">
            <button type="button" class="sidebar-dropdown-trigger {{ $compHasActive ? 'active' : '' }}" data-dropdown="compras" title="Compras">
                <span class="sidebar-menu-icon">🛒</span>
                <span class="sidebar-menu-text">Compras</span>
                <span class="sidebar-dropdown-chevron">▼</span>
            </button>
            <ul class="sidebar-dropdown-menu">
                @can('ordenes_compra.ver')<li><a href="{{ route('ordenes-compra.index') }}" class="sidebar-menu-link {{ request()->routeIs('ordenes-compra.*') ? 'active' : '' }}" title="Órdenes de Compra"><span class="sidebar-menu-icon">📦</span><span class="sidebar-menu-text">Órdenes de Compra</span></a></li>@endcan
                @can('cotizaciones_compra.ver')<li><a href="{{ route('cotizaciones-compra.index') }}" class="sidebar-menu-link {{ request()->routeIs('cotizaciones-compra.*') ? 'active' : '' }}" title="Cotizaciones de Compra"><span class="sidebar-menu-icon">📋</span><span class="sidebar-menu-text">Cotizaciones de Compra</span></a></li>@endcan
                @can('proveedores.ver')<li><a href="{{ route('proveedores.index') }}" class="sidebar-menu-link {{ request()->routeIs('proveedores.*') ? 'active' : '' }}" title="Proveedores"><span class="sidebar-menu-icon">🏭</span><span class="sidebar-menu-text">Proveedores</span></a></li>@endcan
                @can('cuentas_por_pagar.ver')<li><a href="{{ route('cuentas-por-pagar.index') }}" class="sidebar-menu-link {{ request()->routeIs('cuentas-por-pagar.*') ? 'active' : '' }}" title="Cuentas por Pagar"><span class="sidebar-menu-icon">💳</span><span class="sidebar-menu-text">Cuentas por Pagar</span></a></li>@endcan
            </ul>
        </div>
        @endif

        {{-- Finanzas (dropdown) --}}
        @php
            $finHasActive = request()->routeIs('estado-cuenta.*') || request()->routeIs('cuentas-cobrar.*');
        @endphp
        @if(auth()->user()->can('estado_cuenta.ver') || auth()->user()->can('cuentas_cobrar.ver'))
        <div class="sidebar-dropdown {{ $finHasActive ? 'open' : '' }}">
            <button type="button" class="sidebar-dropdown-trigger {{ $finHasActive ? 'active' : '' }}" data-dropdown="finanzas" title="Finanzas">
                <span class="sidebar-menu-icon">💵</span>
                <span class="sidebar-menu-text">Finanzas</span>
                <span class="sidebar-dropdown-chevron">▼</span>
            </button>
            <ul class="sidebar-dropdown-menu">
                @can('estado_cuenta.ver')<li><a href="{{ route('estado-cuenta.index') }}" class="sidebar-menu-link {{ request()->routeIs('estado-cuenta.*') ? 'active' : '' }}" title="Estado de Cuenta"><span class="sidebar-menu-icon">📋</span><span class="sidebar-menu-text">Estado de Cuenta</span></a></li>@endcan
                @can('cuentas_cobrar.ver')<li><a href="{{ route('cuentas-cobrar.index') }}" class="sidebar-menu-link {{ request()->routeIs('cuentas-cobrar.*') ? 'active' : '' }}" title="Cuentas por Cobrar"><span class="sidebar-menu-icon">💵</span><span class="sidebar-menu-text">Cuentas por Cobrar</span></a></li>@endcan
            </ul>
        </div>
        @endif

        {{-- Sistema (dropdown) --}}
        @php
            $sisHasActive = request()->routeIs('usuarios.*') || request()->routeIs('roles.*') || request()->routeIs('empresa.*');
        @endphp
        @if(auth()->user()->can('usuarios.ver') || auth()->user()->can('roles.ver') || auth()->user()->can('configuracion.editar'))
        <div class="sidebar-dropdown {{ $sisHasActive ? 'open' : '' }}">
            <button type="button" class="sidebar-dropdown-trigger {{ $sisHasActive ? 'active' : '' }}" data-dropdown="sistema" title="Sistema">
                <span class="sidebar-menu-icon">⚙️</span>
                <span class="sidebar-menu-text">Sistema</span>
                <span class="sidebar-dropdown-chevron">▼</span>
            </button>
            <ul class="sidebar-dropdown-menu">
                @can('usuarios.ver')<li><a href="{{ route('usuarios.index') }}" class="sidebar-menu-link {{ request()->routeIs('usuarios.*') ? 'active' : '' }}" title="Usuarios"><span class="sidebar-menu-icon">👤</span><span class="sidebar-menu-text">Usuarios</span></a></li>@endcan
                @can('roles.ver')<li><a href="{{ route('roles.index') }}" class="sidebar-menu-link {{ request()->routeIs('roles.*') ? 'active' : '' }}" title="Roles y permisos"><span class="sidebar-menu-icon">🔐</span><span class="sidebar-menu-text">Roles y permisos</span></a></li>@endcan
                @can('configuracion.editar')<li><a href="{{ route('empresa.edit') }}" class="sidebar-menu-link {{ request()->routeIs('empresa.*') ? 'active' : '' }}" title="Configuración"><span class="sidebar-menu-icon">⚙️</span><span class="sidebar-menu-text">Configuración</span></a></li>@endcan
            </ul>
        </div>
        @endif

    </nav>

    <div class="sidebar-footer">
        <button class="sidebar-toggle" onclick="toggleSidebar()" title="Contraer / Expandir menú">
            <span id="toggleIcon">◀</span>
            <span class="sidebar-menu-text">Contraer</span>
        </button>
    </div>

</aside>

<div id="sidebar-collapsed-tooltip" class="sidebar-collapsed-tooltip" aria-hidden="true"></div>

@push('scripts')
<script>
(function() {
    var tooltip = document.getElementById('sidebar-collapsed-tooltip');
    var links = document.querySelectorAll('.sidebar-menu-link[title]');
    var triggers = document.querySelectorAll('.sidebar-dropdown-trigger[title]');
    function showTooltip(e) {
        if (!document.body.classList.contains('sidebar-collapsed')) return;
        var t = e.currentTarget;
        var text = t.getAttribute('title');
        if (!text) return;
        tooltip.textContent = text;
        tooltip.classList.add('visible');
        var rect = t.getBoundingClientRect();
        tooltip.style.left = (rect.right + 8) + 'px';
        tooltip.style.top = (rect.top + rect.height / 2 - (tooltip.offsetHeight || 20) / 2) + 'px';
    }
    function hideTooltip() {
        tooltip.classList.remove('visible');
    }
    links.forEach(function(link) {
        link.addEventListener('mouseenter', showTooltip);
        link.addEventListener('mouseleave', hideTooltip);
    });
    triggers.forEach(function(t) {
        t.addEventListener('mouseenter', showTooltip);
        t.addEventListener('mouseleave', hideTooltip);
    });
    function posicionarFlyout(dd) {
        if (!document.body.classList.contains('sidebar-collapsed')) return;
        var trigger = dd.querySelector('.sidebar-dropdown-trigger');
        var menu = dd.querySelector('.sidebar-dropdown-menu');
        if (trigger && menu && dd.classList.contains('open')) {
            var rect = trigger.getBoundingClientRect();
            menu.style.top = rect.top + 'px';
        }
    }
    document.querySelectorAll('.sidebar-dropdown-trigger').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var dd = this.closest('.sidebar-dropdown');
            if (document.body.classList.contains('sidebar-collapsed')) {
                var open = dd.classList.contains('open');
                document.querySelectorAll('.sidebar-dropdown').forEach(function(d) { d.classList.remove('open'); });
                if (!open) {
                    dd.classList.add('open');
                    posicionarFlyout(dd);
                }
            } else {
                dd.classList.toggle('open');
            }
        });
    });
    document.body.classList.contains('sidebar-collapsed') && document.querySelectorAll('.sidebar-dropdown.open').forEach(posicionarFlyout);
    document.addEventListener('click', function(e) {
        if (document.body.classList.contains('sidebar-collapsed') && !e.target.closest('.sidebar')) {
            document.querySelectorAll('.sidebar-dropdown').forEach(function(d) { d.classList.remove('open'); });
        }
    });
})();
</script>
@endpush
