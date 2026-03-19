<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') — PROMAFI ERP</title>

    @PwaHead

    @vite(['resources/css/app.css'])
    @stack('styles')
</head>
<body class="page-wrapper">

    @include('layouts.partials.sidebar')

    <div class="sidebar-overlay" onclick="toggleSidebarMobile()"></div>

    <div class="main-content">

        {{-- ===== HEADER ===== --}}
    @include('layouts.partials.header')

        {{-- ===== CONTENIDO ===== --}}
        <div class="content-wrapper">

            {{-- Flash messages --}}
            @foreach(['success','error','warning','info'] as $type)
                @if(session($type))
                <div class="alert alert-{{ $type }}" id="flash-alert">
                    <span>{{ $type === 'success' ? '✓' : ($type === 'error' ? '✗' : ($type === 'warning' ? '⚠' : 'ℹ')) }}</span>
                    {{ session($type) }}
                </div>
                @endif
            @endforeach

            {{-- Page Header (título + subtítulo) --}}
            @hasSection('page-title')
            <div class="page-header">
                <div>
                    <h1 class="page-title">@yield('page-title')</h1>
                    @hasSection('page-subtitle')
                        <p class="page-subtitle">@yield('page-subtitle')</p>
                    @endif
                </div>
                @hasSection('page-actions')
                    <div>@yield('page-actions')</div>
                @endif
            </div>
            @endif

            @yield('content')

        </div>

        @include('layouts.partials.footer')

    </div>

    <script>
    // Auto-cerrar flash
    const flash = document.getElementById('flash-alert');
    if (flash) {
        setTimeout(() => {
            flash.style.transition = 'opacity .4s';
            flash.style.opacity = '0';
            setTimeout(() => flash.remove(), 400);
        }, 4500);
    }

    function toggleSidebar() {
        document.body.classList.toggle('sidebar-collapsed');
        const icon = document.getElementById('toggleIcon');
        const isCollapsed = document.body.classList.contains('sidebar-collapsed');
        if (icon) icon.textContent = isCollapsed ? '▶' : '◀';
        localStorage.setItem('sidebarCollapsed', isCollapsed);
    }

    function toggleSidebarMobile() {
        document.body.classList.toggle('sidebar-open');
    }

    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024) {
            const sidebar = document.querySelector('.sidebar');
            const btn = document.querySelector('.mobile-menu-btn');
            if (document.body.classList.contains('sidebar-open')
                && sidebar && !sidebar.contains(e.target)
                && btn && !btn.contains(e.target)) {
                document.body.classList.remove('sidebar-open');
            }
        }
        const dd = document.getElementById('userDropdown');
        const user = document.querySelector('.header-user');
        if (dd && user && !user.contains(e.target) && !dd.contains(e.target)) {
            dd.style.display = 'none';
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        if (localStorage.getItem('sidebarCollapsed') === 'true') {
            document.body.classList.add('sidebar-collapsed');
            const icon = document.getElementById('toggleIcon');
            if (icon) icon.textContent = '▶';
        }

        // Tablas responsivas: envolver en contenedor con scroll horizontal
        const content = document.querySelector('.content-wrapper');
        if (content) {
            const tables = content.querySelectorAll('table');
            tables.forEach((table) => {
                if (!table || table.closest('.table-container') || table.classList.contains('table-no-scroll')) return;

                const wrapper = document.createElement('div');
                wrapper.className = 'table-container';

                const parent = table.parentNode;
                if (!parent) return;

                parent.insertBefore(wrapper, table);
                wrapper.appendChild(table);
            });
        }

        // Coherencia móvil/PWA: los enlaces "Ver PDF" no abren en nueva pestaña.
        // Evita que el botón atrás cierre la app al salir de un contexto externo.
        const isMobile = window.matchMedia('(max-width: 1024px)').matches;
        const isStandalonePwa = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
        if (isMobile || isStandalonePwa) {
            const verPdfLinks = Array.from(document.querySelectorAll('a[target="_blank"]'))
                .filter((a) => {
                    const href = (a.getAttribute('href') || '').toLowerCase();
                    const text = (a.textContent || '').toLowerCase();
                    return href.includes('/ver-pdf') || text.includes('ver pdf') || text.includes('ver factura');
                });
            verPdfLinks.forEach((a) => a.removeAttribute('target'));
        }
    });

    function toggleUserMenu() {
        const dd = document.getElementById('userDropdown');
        if (dd) dd.style.display = dd.style.display === 'none' ? 'block' : 'none';
    }

    function logout() {
        if (confirm('¿Cerrar sesión?')) {
            document.getElementById('logout-form').submit();
        }
    }
    </script>

    @RegisterServiceWorkerScript

    @stack('scripts')
</body>
</html>