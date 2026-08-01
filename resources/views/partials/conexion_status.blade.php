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
        <svg class="header-conexion-svg header-conexion-svg--online" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18">
            <path d="M12 18.5a1.75 1.75 0 1 1 0 3.5 1.75 1.75 0 0 1 0-3.5Zm-4.95-3.05a.75.75 0 0 1 0-1.06 6.75 6.75 0 0 1 9.9 0 .75.75 0 1 1-1.06 1.06 5.25 5.25 0 0 0-7.78 0 .75.75 0 0 1-1.06 0Zm-2.83-2.83a.75.75 0 0 1 0-1.06 10.75 10.75 0 0 1 15.56 0 .75.75 0 1 1-1.06 1.06 9.25 9.25 0 0 0-13.44 0 .75.75 0 0 1-1.06 0Zm-2.82-2.83a.75.75 0 0 1 0-1.06C5.02 5.11 8.35 3.75 12 3.75s6.98 1.36 10.6 4.98a.75.75 0 0 1-1.06 1.06C18.3 6.67 15.3 5.25 12 5.25S5.7 6.67 2.46 9.91a.75.75 0 0 1-1.06 0Z"/>
        </svg>
        <svg class="header-conexion-svg header-conexion-svg--offline" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="18" height="18" hidden>
            <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l18.5 18.5a.75.75 0 1 0 1.06-1.06l-3.1-3.1a10.6 10.6 0 0 0 3.92-2.63.75.75 0 0 0-1.06-1.06 9.17 9.17 0 0 1-3.4 2.25l-2.2-2.2a6.7 6.7 0 0 0 2.56-1.7.75.75 0 1 0-1.06-1.06 5.2 5.2 0 0 1-1.98 1.3L12.7 10.3a.8.8 0 0 0-.2-.03 1.75 1.75 0 0 0-1.55 2.56L3.28 2.22ZM12 18.5a1.75 1.75 0 1 1 0 3.5 1.75 1.75 0 0 1 0-3.5Z" clip-rule="evenodd"/>
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
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="48" height="48">
                <path fill-rule="evenodd" d="M3.28 2.22a.75.75 0 0 0-1.06 1.06l18.5 18.5a.75.75 0 1 0 1.06-1.06l-3.1-3.1a10.6 10.6 0 0 0 3.92-2.63.75.75 0 0 0-1.06-1.06 9.17 9.17 0 0 1-3.4 2.25l-2.2-2.2a6.7 6.7 0 0 0 2.56-1.7.75.75 0 1 0-1.06-1.06 5.2 5.2 0 0 1-1.98 1.3L12.7 10.3a.8.8 0 0 0-.2-.03 1.75 1.75 0 0 0-1.55 2.56L3.28 2.22ZM12 18.5a1.75 1.75 0 1 1 0 3.5 1.75 1.75 0 0 1 0-3.5Z" clip-rule="evenodd"/>
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

        iconWrap.querySelectorAll('.header-conexion-svg').forEach(function (svg) {
            const isOnlineSvg = svg.classList.contains('header-conexion-svg--online');
            svg.hidden = online ? !isOnlineSvg : isOnlineSvg;
        });
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
