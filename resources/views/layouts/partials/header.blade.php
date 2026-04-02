{{-- resources/views/layouts/partials/header.blade.php --}}

<header class="header">

{{-- FILA SUPERIOR --}}
    <div class="header-top-row">

    {{-- Mobile toggle --}}
    <button class="mobile-menu-btn" onclick="toggleSidebarMobile()">☰</button>

    {{-- Breadcrumbs --}}
    <div class="breadcrumb">
        <a href="{{ route('dashboard') }}" class="breadcrumb-link">🏠 Inicio</a>
        @if(isset($breadcrumbs) && count($breadcrumbs))
            @foreach($breadcrumbs as $i => $crumb)
                <span class="breadcrumb-separator">/</span>
                @if(isset($crumb['url']) && $i < count($breadcrumbs) - 1)
                    <a href="{{ $crumb['url'] }}" class="breadcrumb-link">{{ $crumb['title'] }}</a>
                @else
                    <span class="breadcrumb-current">{{ $crumb['title'] }}</span>
                @endif
            @endforeach
        @elseif(isset($pageTitle))
            <span class="breadcrumb-separator">/</span>
            <span class="breadcrumb-current">{{ $pageTitle }}</span>
        @endif
    </div>

    {{-- Acciones --}}
    <div class="header-actions">

        {{-- Notificaciones --}}
        <button class="header-icon-btn" title="Notificaciones" id="notificationsButton" onclick="toggleNotifications()">
            🔔
            <span id="notificationsBadgeDot" class="badge-dot" style="display:none;"></span>
        </button>

        {{-- Usuario --}}
        <div class="header-user" onclick="toggleUserMenu()">
            <div class="header-user-avatar">
                {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
            </div>
            <div>
                <div class="header-user-name">{{ Auth::user()->name ?? 'Usuario' }}</div>
                <div class="header-user-role">{{ Auth::user()->role?->display_name ?? 'Usuario' }}</div>
            </div>
        </div>
    </div>
</div>

{{-- FILA INFERIOR (Search) --}}
    <div class="header-search">
        <span class="header-search-icon">🔍</span>
        <input type="text"
               class="header-search-input"
               placeholder="Buscar en el sistema..."
               id="globalSearch"
               autocomplete="off">
        <div id="globalSearchResults" class="global-search-results"></div>
    </div>
</header>

{{-- Dropdown usuario --}}
<div id="userDropdown" class="user-dropdown" style="display:none;">
    <div class="user-dropdown-inner">
        @can('configuracion.editar')
        <a href="{{ route('empresa.edit') }}" class="user-dropdown-item">
            <span class="icon">⚙️</span> Configuración
        </a>
        @endcan
        <a href="{{ route('perfil.edit') }}" class="user-dropdown-item">
            <span class="icon">👤</span> Mi perfil
        </a>
        <div class="user-dropdown-divider"></div>
        <a href="#" onclick="logout(); return false;" class="user-dropdown-item danger">
            <span class="icon">🚪</span> Cerrar Sesión
        </a>
    </div>
</div>

{{-- Dropdown notificaciones (solo admins/permiso) --}}
@can('notificaciones.ver')
<div id="notificationsDropdown"
     class="user-dropdown"
     style="display:none; width: 420px; min-width: 0; padding: 0; overflow: hidden;">
    <div class="user-dropdown-inner" id="notificationsDropdownInner">
        <div style="padding: 10px 12px; font-size: 13.5px; color: var(--color-gray-500);">
            Cargando notificaciones...
        </div>
    </div>
</div>
@endcan

{{-- Logout form --}}
<form id="logout-form" action="{{ route('logout') }}" method="POST" style="display:none;">
    @csrf
</form>

{{-- ============================= --}}
{{-- GLOBAL SEARCH SCRIPT --}}
{{-- ============================= --}}
@push('scripts')
<script>
document.addEventListener("DOMContentLoaded", function () {

    const input = document.getElementById('globalSearch');
    const resultsContainer = document.getElementById('globalSearchResults');

    if (!input || !resultsContainer) return;

    let debounceTimer;

    input.addEventListener('keyup', function () {

        clearTimeout(debounceTimer);

        let query = this.value.trim();

        if (query.length < 2) {
            resultsContainer.style.display = 'none';
            resultsContainer.innerHTML = '';
            return;
        }

        debounceTimer = setTimeout(() => {

            fetch(`{{ route('global.search') }}?q=${encodeURIComponent(query)}`)
                .then(res => res.json())
                .then(data => renderResults(data))
                .catch(() => {
                    resultsContainer.style.display = 'none';
                });

        }, 300);

    });

    const SECTION_LABELS = {
        productos: 'Productos',
        clientes: 'Clientes',
        proveedores: 'Proveedores',
        facturas: 'Facturas',
        cotizaciones: 'Cotizaciones',
        remisiones: 'Remisiones',
        compras: 'Compras'
    };
    const SECTION_ORDER = ['productos', 'clientes', 'proveedores', 'facturas', 'cotizaciones', 'remisiones', 'compras'];

    function renderResults(data) {

        resultsContainer.innerHTML = '';

        let hasResults = false;

        SECTION_ORDER.forEach(section => {

            if (data[section] && data[section].length > 0) {

                hasResults = true;

                const title = document.createElement('div');
                title.className = 'search-group-title';
                title.innerText = SECTION_LABELS[section] || section;
                resultsContainer.appendChild(title);

                data[section].forEach(item => {

                    const div = document.createElement('div');
                    div.className = 'search-item';
                    div.innerText = item.label;

                    div.addEventListener('click', () => {
                        window.location.href = item.url;
                    });

                    resultsContainer.appendChild(div);

                });
            }

        });

        resultsContainer.style.display = hasResults ? 'block' : 'none';
    }

    document.addEventListener('click', function (e) {
        if (!e.target.closest('.header-search')) {
            resultsContainer.style.display = 'none';
        }
    });

});
</script>

@can('notificaciones.ver')
<script>
document.addEventListener("DOMContentLoaded", function () {
    const btn = document.getElementById('notificationsButton');
    const dd = document.getElementById('notificationsDropdown');
    const ddInner = document.getElementById('notificationsDropdownInner');
    const badge = document.getElementById('notificationsBadgeDot');

    if (!btn || !dd || !ddInner || !badge) return;

    function escapeHtml(str) {
        return String(str ?? '').replace(/[&<>"']/g, (m) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        }[m]));
    }

    function formatMXN(n) {
        const num = Number(n ?? 0);
        try {
            return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(num);
        } catch (e) {
            return '$' + num.toFixed(2);
        }
    }

    function buildMarkup(data) {
        const credito = data.credito_excedente;
        const vencidas = data.vencidas || {};

        const creditoDisponible = credito
            ? -Math.abs(Number(credito.saldo_excedente ?? 0))
            : 0;

        const creditoHtml = credito
            ? `
                <div style="font-family: var(--font-display); font-weight: 800; color: var(--color-primary); font-size: 13.5px; margin-bottom: 4px;">
                    ⚠️ Crédito excedente
                </div>
                <div style="font-size: 13.5px; color: var(--color-gray-600);">
                    <div><span style="font-weight: 700; color: var(--color-gray-700);">Cliente:</span> ${escapeHtml(credito.cliente_nombre)}</div>
                    <div><span style="font-weight: 700; color: var(--color-gray-700);">Saldo excedente / Crédito disponible:</span> <span style="color: var(--color-danger); font-weight: 800;">${formatMXN(creditoDisponible)}</span></div>
                </div>
            `
            : `
                <div style="font-size: 13.5px; color: var(--color-gray-500);">
                    Sin crédito excedente detectado.
                </div>
            `;

        const primeras = (vencidas.primeras_fechas || []).map(d => `<span style="white-space: nowrap;">${escapeHtml(d.fecha_vencimiento || '')}</span>`).join(', ');

        const vencidasHtml = vencidas.cuentas_vencidas > 0
            ? `
                <div style="font-family: var(--font-display); font-weight: 800; color: var(--color-primary); font-size: 13.5px; margin-bottom: 4px;">
                    ⚠️ Cuentas vencidas
                </div>
                <div style="font-size: 13.5px; color: var(--color-gray-600);">
                    <div><span style="font-weight: 700; color: var(--color-gray-700);">Cuentas vencidas:</span> ${escapeHtml(vencidas.cuentas_vencidas)}</div>
                    <div><span style="font-weight: 700; color: var(--color-gray-700);">Monto vencido:</span> ${formatMXN(vencidas.monto_vencido)}</div>
                    <div style="margin-top: 6px;"><span style="font-weight: 700; color: var(--color-gray-700);">Primeras 3 fechas:</span> ${primeras || '—'}</div>
                </div>
            `
            : `
                <div style="font-size: 13.5px; color: var(--color-gray-500);">
                    No hay cuentas vencidas por cobrar.
                </div>
            `;

        return `
            <div style="padding: 10px 12px;">
                ${creditoHtml}
                <div style="height:1px; background: var(--color-gray-100); margin: 10px 0;"></div>
                ${vencidasHtml}
            </div>
        `;
    }

    window.toggleNotifications = function () {
        // Si no hay permiso (o elementos), no hagas nada.
        if (!dd || !ddInner || !badge) return;
        dd.style.display = dd.style.display === 'block' ? 'none' : 'block';
    };

    document.addEventListener('click', function (e) {
        if (!dd || dd.style.display === 'none') return;
        if (btn.contains(e.target) || dd.contains(e.target)) return;
        dd.style.display = 'none';
    });

    fetch('{{ route('notificaciones.admin') }}', {
        method: 'GET',
        headers: { 'Accept': 'application/json' },
        credentials: 'same-origin'
    })
        .then(r => r.ok ? r.json() : Promise.reject(r))
        .then(data => {
            const html = buildMarkup(data || {});
            ddInner.innerHTML = html;

            const has = Boolean(data?.has_notifications);
            badge.style.display = has ? 'block' : 'none';

            // Toast (aparece al entrar al sistema).
            if (has) {
                const toastId = 'notificationsToast';
                let toast = document.getElementById(toastId);
                if (!toast) {
                    toast = document.createElement('div');
                    toast.id = toastId;
                    toast.style.position = 'fixed';
                    toast.style.top = 'calc(var(--header-height) + 10px)';
                    toast.style.right = '20px';
                    toast.style.zIndex = '2500';
                    toast.style.width = '420px';
                    toast.style.maxWidth = 'calc(100vw - 32px)';
                    toast.style.background = '#fff';
                    toast.style.border = '1px solid var(--color-gray-200)';
                    toast.style.borderRadius = 'var(--radius-lg)';
                    toast.style.boxShadow = 'var(--shadow-xl)';
                    toast.style.opacity = '0';
                    toast.style.transition = 'opacity .25s ease';
                    toast.style.pointerEvents = 'none';
                    document.body.appendChild(toast);
                }

                toast.innerHTML = html;
                toast.style.pointerEvents = 'auto';
                toast.style.opacity = '1';

                setTimeout(() => {
                    toast.style.opacity = '0';
                    setTimeout(() => { toast.remove(); }, 350);
                }, 8500);
            }
        })
        .catch(() => {
            ddInner.innerHTML = `
                <div style="padding: 10px 12px; font-size: 13.5px; color: var(--color-gray-500);">
                    No se pudieron cargar las notificaciones.
                </div>
            `;
        });
});
</script>
@endcan

{{-- Fallback: evita errores JS cuando el usuario NO tiene permiso --}}
<script>
    function toggleNotifications() {
        const dd = document.getElementById('notificationsDropdown');
        if (!dd) return;
        dd.style.display = dd.style.display === 'block' ? 'none' : 'block';
    }
</script>

@endpush