<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="promafi-route" content="{{ Route::currentRouteName() }}">
    <meta name="promafi-rendimiento-pagina" content="{{ route('rendimiento.pagina') }}">
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
                <div class="page-header-main">
                    <div class="page-header-title-row">
                        <h1 class="page-title">@yield('page-title')</h1>
                        @hasSection('page-title-aside')
                            @php
                                $pageTitleAside = trim($__env->yieldContent('page-title-aside'));
                            @endphp
                            @if($pageTitleAside !== '')
                                <div class="page-title-aside">{!! $pageTitleAside !!}</div>
                            @endif
                        @endif
                    </div>
                    @hasSection('page-meta')
                        <div class="page-meta">@yield('page-meta')</div>
                    @endif
                    @hasSection('page-subtitle')
                        <p class="page-subtitle">@yield('page-subtitle')</p>
                    @endif
                </div>
                @hasSection('page-actions')
                    @php
                        $pageActions = trim($__env->yieldContent('page-actions'));
                    @endphp
                    @if($pageActions !== '')
                        <div class="page-header-actions">{!! $pageActions !!}</div>
                    @endif
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

        document.querySelectorAll('form.form-timbrar-protegido').forEach(function (form) {
            form.addEventListener('submit', function () {
                const btn = form.querySelector('button[type="submit"]');
                if (!btn || btn.disabled) {
                    return;
                }
                btn.disabled = true;
                const loadingText = form.getAttribute('data-loading-text') || 'Emitiendo…';
                if (!btn.dataset.originalText) {
                    btn.dataset.originalText = btn.textContent.trim();
                }
                btn.textContent = loadingText;
                btn.style.opacity = '0.65';
                btn.style.cursor = 'wait';
            });
        });
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

    @include('partials.conexion_status', ['conexionPart' => 'body'])

    {{-- Overlay al navegar (tablas/filtros/menú). No sustituye “Timbrando…” del PAC. --}}
    <div id="promafiNavLoading" class="nav-loading" hidden role="status" aria-live="polite" aria-busy="true">
        <div class="nav-loading__panel">
            <div class="nav-loading__spinner" aria-hidden="true"></div>
            <p class="nav-loading__text">Cargando, espere…</p>
        </div>
    </div>
    <script>
    (function () {
        if (window.PromafiNavLoader && window.PromafiNavLoader._initDone) return;

        const DELAY_MS = 200;
        let showTimer = null;
        let visible = false;

        function overlay() {
            return document.getElementById('promafiNavLoading');
        }

        function showNow() {
            const el = overlay();
            if (!el) return;
            visible = true;
            el.hidden = false;
            document.documentElement.style.cursor = 'wait';
        }

        function hide() {
            clearTimeout(showTimer);
            showTimer = null;
            visible = false;
            const el = overlay();
            if (el) el.hidden = true;
            document.documentElement.style.cursor = '';
        }

        function scheduleShow() {
            clearTimeout(showTimer);
            showTimer = setTimeout(showNow, DELAY_MS);
        }

        function isModifiedClick(e) {
            return e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button === 1;
        }

        function shouldSkipLink(a) {
            if (!a) return true;
            if (a.hasAttribute('download')) return true;
            if (a.getAttribute('data-no-nav-loader') !== null) return true;
            const target = (a.getAttribute('target') || '').toLowerCase();
            if (target === '_blank') return true;
            const href = a.getAttribute('href');
            if (!href || href === '#' || href.startsWith('#')) return true;
            const lower = href.toLowerCase();
            if (lower.startsWith('javascript:') || lower.startsWith('mailto:') || lower.startsWith('tel:')) return true;
            try {
                const url = new URL(href, window.location.href);
                if (url.origin !== window.location.origin) return true;
                if (url.pathname === window.location.pathname
                    && url.search === window.location.search
                    && url.hash) {
                    return true;
                }
            } catch (err) {
                return true;
            }
            return false;
        }

        function shouldSkipForm(form) {
            if (!form) return true;
            if (form.getAttribute('data-no-nav-loader') !== null) return true;
            if (form.classList.contains('form-timbrar-protegido')) return true;
            const target = (form.getAttribute('target') || '').toLowerCase();
            if (target === '_blank') return true;
            return false;
        }

        document.addEventListener('click', function (e) {
            if (isModifiedClick(e)) return;
            const a = e.target.closest && e.target.closest('a[href]');
            if (!a || shouldSkipLink(a)) return;
            if (e.defaultPrevented) return;
            scheduleShow();
        }, true);

        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (shouldSkipForm(form)) return;
            if (e.defaultPrevented) return;
            scheduleShow();
        }, true);

        window.addEventListener('pageshow', function () {
            hide();
        });
        window.addEventListener('pagehide', function () {
            clearTimeout(showTimer);
        });

        window.PromafiNavLoader = {
            _initDone: true,
            show: scheduleShow,
            showNow: showNow,
            hide: hide,
            get visible() { return visible; },
        };
    })();
    </script>

    {{-- Beacon UX: DOMContentLoaded (siempre ≥2 s; verdes muestreados en servidor). --}}
    <script>
    (function () {
        if (window.PromafiPageBeacon && window.PromafiPageBeacon._done) return;
        window.PromafiPageBeacon = { _done: true };

        function meta(name) {
            var el = document.querySelector('meta[name="' + name + '"]');
            return el ? el.getAttribute('content') : '';
        }

        function enviar() {
            var url = meta('promafi-rendimiento-pagina');
            var csrf = meta('csrf-token');
            if (!url || !csrf || !window.performance || !performance.getEntriesByType) return;

            var nav = performance.getEntriesByType('navigation')[0];
            if (!nav) return;

            var dcl = Math.max(0, (nav.domContentLoadedEventEnd || 0) / 1000);
            var load = Math.max(0, (nav.loadEventEnd || 0) / 1000);
            var ttfb = Math.max(0, ((nav.responseStart || 0) - (nav.requestStart || 0)) / 1000);
            if (dcl <= 0 && load <= 0) return;

            // Espejo del servidor: verdes (~10%) se filtran en el endpoint.
            var body = JSON.stringify({
                path: window.location.pathname + window.location.search,
                route: meta('promafi-route') || null,
                dcl_s: Math.round(dcl * 100) / 100,
                load_s: Math.round(load * 100) / 100,
                ttfb_s: Math.round(ttfb * 100) / 100,
            });

            try {
                if (navigator.sendBeacon) {
                    var blob = new Blob([body], { type: 'application/json' });
                    // sendBeacon no manda CSRF fácil: usamos fetch keepalive
                }
                fetch(url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: body,
                    credentials: 'same-origin',
                    keepalive: true,
                }).catch(function () {});
            } catch (e) {}
        }

        if (document.readyState === 'complete') {
            setTimeout(enviar, 0);
        } else {
            window.addEventListener('load', function () { setTimeout(enviar, 0); });
        }
    })();
    </script>

    @stack('scripts')
</body>
</html>