{{-- resources/views/layouts/partials/sidebar.blade.php --}}
<aside class="sidebar">

    {{-- Logo --}}
    <div class="sidebar-logo">
        <div class="sidebar-logo-initials">PE</div>
        <img src="{{ asset('assets/imagenes/logo.svg') }}"
             alt="Promafi ERP"
             class="sidebar-logo-img"
             onerror="this.style.display='none'">
        <div>
            <div class="sidebar-logo-text">PROMAFI ERP</div>
            <div class="sidebar-logo-sub">Sistema Comercial</div>
        </div>
    </div>

    {{-- Navegación --}}
    <nav class="sidebar-nav">

        <div class="sidebar-section">
            <div class="sidebar-section-title">Principal</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('dashboard') }}"
                       class="sidebar-menu-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">📊</span>
                        <span class="sidebar-menu-text">Dashboard</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Facturación</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('facturas.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('facturas.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">📄</span>
                        <span class="sidebar-menu-text">Facturas CFDI</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('cotizaciones.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('cotizaciones.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">📋</span>
                        <span class="sidebar-menu-text">Cotizaciones</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('complementos.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('complementos.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">💳</span>
                        <span class="sidebar-menu-text">Complementos Pago</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('remisiones.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('remisiones.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">🚚</span>
                        <span class="sidebar-menu-text">Remisiones</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Administración</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('clientes.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('clientes.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">👥</span>
                        <span class="sidebar-menu-text">Clientes</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Catálogos</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('productos.index') }}"
                    class="sidebar-menu-link {{ request()->routeIs('productos.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">📦</span>
                        <span class="sidebar-menu-text">Productos</span>
                    </a>
                </li>

                <li class="sidebar-menu-item">
                    <a href="{{ route('categorias.index') }}"
                    class="sidebar-menu-link {{ request()->routeIs('categorias.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">🗂️</span>
                        <span class="sidebar-menu-text">Categorías</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('sugerencias.index') }}"
                    class="sidebar-menu-link {{ request()->routeIs('sugerencias.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">💡</span>
                        <span class="sidebar-menu-text">Sugerencias</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Compras</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('ordenes-compra.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('ordenes-compra.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">📦</span>
                        <span class="sidebar-menu-text">Órdenes de Compra</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('cotizaciones-compra.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('cotizaciones-compra.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">📋</span>
                        <span class="sidebar-menu-text">Cotizaciones de Compra</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('proveedores.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('proveedores.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">🏭</span>
                        <span class="sidebar-menu-text">Proveedores</span>
                    </a>
                </li>
                <li class="sidebar-menu-item">
                    <a href="{{ route('cuentas-por-pagar.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('cuentas-por-pagar.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">💳</span>
                        <span class="sidebar-menu-text">Cuentas por Pagar</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Finanzas</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('cuentas-cobrar.index') }}"
                       class="sidebar-menu-link {{ request()->routeIs('cuentas-cobrar.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">💵</span>
                        <span class="sidebar-menu-text">Cuentas por Cobrar</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidebar-section">
            <div class="sidebar-section-title">Sistema</div>
            <ul class="sidebar-menu">
                <li class="sidebar-menu-item">
                    <a href="{{ route('empresa.edit') }}"
                       class="sidebar-menu-link {{ request()->routeIs('empresa.*') ? 'active' : '' }}">
                        <span class="sidebar-menu-icon">⚙️</span>
                        <span class="sidebar-menu-text">Configuración</span>
                    </a>
                </li>
            </ul>
        </div>

    </nav>

    {{-- Toggle --}}
    <div class="sidebar-footer">
        <button class="sidebar-toggle" onclick="toggleSidebar()">
            <span id="toggleIcon">◀</span>
            <span class="sidebar-menu-text">Contraer</span>
        </button>
    </div>

</aside>