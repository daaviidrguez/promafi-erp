<nav style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px;" aria-label="Secciones de auditoría">
    <a href="{{ route('auditoria.rendimiento') }}"
       class="btn btn-sm {{ request()->routeIs('auditoria.rendimiento') ? 'btn-primary' : 'btn-light' }}">⏱️ Rendimiento</a>
    <a href="{{ route('auditoria.errores') }}"
       class="btn btn-sm {{ request()->routeIs('auditoria.errores') ? 'btn-primary' : 'btn-light' }}">🧯 Errores del sistema</a>
</nav>
