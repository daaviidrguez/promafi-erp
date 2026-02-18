{{-- resources/views/layouts/partials/header.blade.php --}}
<header class="header">

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

        {{-- BÚSQUEDA GLOBAL --}}
        <div class="header-search">
            <span class="header-search-icon">🔍</span>
            <input type="text"
                   class="header-search-input"
                   placeholder="Buscar en el sistema..."
                   id="globalSearch"
                   autocomplete="off">

            {{-- Resultados --}}
            <div id="globalSearchResults" class="global-search-results"></div>
        </div>

        {{-- Notificaciones --}}
        <button class="header-icon-btn" title="Notificaciones">
            🔔
            <span class="badge-dot"></span>
        </button>

        {{-- Usuario --}}
        <div class="header-user" onclick="toggleUserMenu()">
            <div class="header-user-avatar">
                {{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}
            </div>
            <div>
                <div class="header-user-name">{{ Auth::user()->name ?? 'Usuario' }}</div>
                <div class="header-user-role">Administrador</div>
            </div>
        </div>
    </div>

</header>

{{-- Dropdown usuario --}}
<div id="userDropdown" class="user-dropdown" style="display:none;">
    <div class="user-dropdown-inner">
        <a href="{{ route('empresa.edit') }}" class="user-dropdown-item">
            <span class="icon">⚙️</span> Configuración
        </a>
        <div class="user-dropdown-divider"></div>
        <a href="#" onclick="logout(); return false;" class="user-dropdown-item danger">
            <span class="icon">🚪</span> Cerrar Sesión
        </a>
    </div>
</div>

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

    function renderResults(data) {

        resultsContainer.innerHTML = '';

        let hasResults = false;

        Object.keys(data).forEach(section => {

            if (data[section] && data[section].length > 0) {

                hasResults = true;

                const title = document.createElement('div');
                title.className = 'search-group-title';
                title.innerText = section.toUpperCase();
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
@endpush