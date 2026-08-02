@php
    $conexionPart = $conexionPart ?? 'all';
    $conexionBloqueanteLead = $conexionBloqueanteLead
        ?? 'Verifica tu conexión a la red. El avance ya quedó guardado en este equipo; la captura se reactivará automáticamente cuando se restablezca la comunicación.';
@endphp

@if($conexionPart === 'navbar' || $conexionPart === 'all')
<div
    id="promafiConexionNav"
    class="header-conexion"
    role="status"
    aria-live="polite"
    title="Con conexión"
    aria-label="Con conexión"
>
    <span id="promafiConexionNavIcon" class="header-conexion-icon header-conexion-icon--online" aria-hidden="true">
        <svg class="header-conexion-svg header-conexion-svg--online" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
            <path d="M5 12.55a11 11 0 0 1 14.08 0"/>
            <path d="M1.42 9a16 16 0 0 1 21.16 0"/>
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
            <circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/>
        </svg>
        <svg class="header-conexion-svg header-conexion-svg--offline" xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 26 26" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true">
            <path d="M1 1l22 22"/>
            <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
            <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
            <path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
            <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
            <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
            <circle cx="12" cy="20" r="1" fill="currentColor" stroke="none"/>
        </svg>
    </span>
    <span id="promafiConexionNavHora" class="header-conexion-hora"></span>
</div>
@endif

@if($conexionPart === 'body' || $conexionPart === 'all')
<div
    id="promafiConexionBloqueante"
    class="conexion-bloqueante"
    hidden
    role="alertdialog"
    aria-modal="true"
    aria-labelledby="promafiConexionBloqueanteTitle"
>
    <div class="conexion-bloqueante__panel">
        <div class="conexion-bloqueante__icon" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="-1 -1 26 26" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" width="48" height="48">
                <path d="M1 1l22 22"/>
                <path d="M16.72 11.06A10.94 10.94 0 0 1 19 12.55"/>
                <path d="M5 12.55a10.94 10.94 0 0 1 5.17-2.39"/>
                <path d="M10.71 5.05A16 16 0 0 1 22.58 9"/>
                <path d="M1.42 9a15.91 15.91 0 0 1 4.7-2.88"/>
                <path d="M8.53 16.11a6 6 0 0 1 6.95 0"/>
                <circle cx="12" cy="20" r="1.15" fill="currentColor" stroke="none"/>
            </svg>
        </div>
        <h2 id="promafiConexionBloqueanteTitle" class="conexion-bloqueante__title">Sin conexión con el servidor</h2>
        <p class="conexion-bloqueante__lead">{{ $conexionBloqueanteLead }}</p>
        <div class="conexion-bloqueante__spinner" aria-hidden="true"></div>
        <p class="conexion-bloqueante__status">Reintentando conexión...</p>
    </div>
</div>

<div
    id="promafiConexionToast"
    class="conexion-toast"
    hidden
    role="status"
    aria-live="polite"
>
    <span class="conexion-toast__text">Conexión restablecida</span>
    <button type="button" class="conexion-toast__close" id="promafiConexionToastClose" aria-label="Cerrar">×</button>
</div>

<script>
(function () {
    if (window.PromafiConexion && window.PromafiConexion._initDone) return;

    const state = {
        online: typeof navigator !== 'undefined' ? navigator.onLine : true,
        hora: '',
        toastVisible: false,
        _toastTimer: null,
        _horaTimer: null,
        _initDone: false,
    };

    function $(id) { return document.getElementById(id); }

    function bloqueoActivo() {
        return document.body && document.body.dataset.conexionBloqueo === '1';
    }

    function actualizarHora() {
        const now = new Date();
        const h = String(now.getHours()).padStart(2, '0');
        const m = String(now.getMinutes()).padStart(2, '0');
        state.hora = h + ':' + m;
        const horaEl = $('promafiConexionNavHora');
        if (horaEl) horaEl.textContent = state.hora;
    }

    function actualizarNav() {
        const nav = $('promafiConexionNav');
        const iconWrap = $('promafiConexionNavIcon');
        if (!nav || !iconWrap) return;

        const online = state.online;
        const label = online ? 'Con conexión' : 'Sin conexión';
        nav.title = label;
        nav.setAttribute('aria-label', label);
        iconWrap.classList.toggle('header-conexion-icon--online', online);
        iconWrap.classList.toggle('header-conexion-icon--offline', !online);
    }

    function actualizarOverlay() {
        const overlay = $('promafiConexionBloqueante');
        if (!overlay) return;
        const mostrar = bloqueoActivo() && !state.online;
        overlay.hidden = !mostrar;
    }

    function mostrarToast() {
        const toast = $('promafiConexionToast');
        if (!toast) return;
        state.toastVisible = true;
        toast.hidden = false;
        clearTimeout(state._toastTimer);
        state._toastTimer = setTimeout(function () {
            cerrarToast();
        }, 4200);
    }

    function cerrarToast() {
        const toast = $('promafiConexionToast');
        state.toastVisible = false;
        if (toast) toast.hidden = true;
        clearTimeout(state._toastTimer);
    }

    function setOnline(online) {
        const estabaOffline = !state.online;
        state.online = online;
        actualizarNav();
        actualizarOverlay();
        if (online && estabaOffline) {
            mostrarToast();
        }
    }

    function init() {
        if (state._initDone) return;
        state._initDone = true;
        actualizarHora();
        state._horaTimer = setInterval(actualizarHora, 15000);
        state.online = typeof navigator !== 'undefined' ? navigator.onLine : true;
        actualizarNav();
        actualizarOverlay();

        window.addEventListener('online', function () { setOnline(true); });
        window.addEventListener('offline', function () { setOnline(false); });

        const closeBtn = $('promafiConexionToastClose');
        if (closeBtn) {
            closeBtn.addEventListener('click', cerrarToast);
        }
    }

    window.PromafiConexion = {
        get online() { return state.online; },
        get hora() { return state.hora; },
        _initDone: false,
        init: init,
        enableBloqueo: function () {
            if (document.body) {
                document.body.dataset.conexionBloqueo = '1';
            }
            actualizarOverlay();
        },
        disableBloqueo: function () {
            if (document.body) {
                delete document.body.dataset.conexionBloqueo;
            }
            actualizarOverlay();
        },
        mostrarToast: mostrarToast,
        cerrarToast: cerrarToast,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
    window.PromafiConexion._initDone = true;
})();
</script>
@endif
