@extends('layouts.app')
@section('title', $usuario->name)
@section('page-title', $usuario->name)
@section('page-subtitle', 'Usuario')

@php
$breadcrumbs = [['title' => 'Usuarios', 'url' => route('usuarios.index')], ['title' => $usuario->name]];
$puedeEditarMeta = auth()->user()?->isAdmin() || auth()->user()?->hasPermission('usuarios.editar');
@endphp

@section('content')
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Datos del usuario</div>
                <a href="{{ route('usuarios.edit', $usuario->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Nombre</div><div class="info-value">{{ $usuario->name }}</div></div>
                    <div class="info-row"><div class="info-label">Email</div><div class="info-value text-mono">{{ $usuario->email }}</div></div>
                    <div class="info-row"><div class="info-label">Rol</div><div class="info-value">{{ $usuario->role ? $usuario->role->display_name : '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Estado</div><div>@if($usuario->activo)<span class="badge badge-success">Activo</span>@else<span class="badge badge-danger">Inactivo</span>@endif</div></div>
                </div>
            </div>
        </div>

        @if($usuario->puedeTenerMetaComercial())
        <div class="card" style="margin-top:20px;">
            <div class="card-header">
                <div class="card-title">🎯 Gestión comercial</div>
                @if($puedeEditarMeta)
                <button type="button" class="btn btn-primary btn-sm" onclick="abrirModalMetaUsuario()">✏️ Editar meta</button>
                @endif
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row">
                        <div class="info-label">Meta mensual sin IVA</div>
                        <div class="info-value text-mono">
                            @if($usuario->metaVentasMensual() > 0)
                                ${{ number_format($usuario->metaVentasMensual(), 2, '.', ',') }}
                            @else
                                <span style="color:var(--color-warning);">Sin meta definida</span>
                            @endif
                        </div>
                    </div>
                    <div class="info-row">
                        <div class="info-label">Periodo</div>
                        <div class="info-value">Mensual (fijo)</div>
                    </div>
                </div>
                <p style="margin:12px 0 0; font-size:13px; color:var(--color-gray-500);">
                    Disponible para roles admin y vendedor. Alimenta el avance total del Dashboard de Ventas.
                    Las metas por cliente se definen en la ficha del cliente.
                </p>
            </div>
        </div>
        @endif
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <a href="{{ route('usuarios.edit', $usuario->id) }}" class="btn btn-primary w-full">✏️ Editar</a>
                @if($usuario->id !== auth()->id())
                <form method="POST" action="{{ route('usuarios.destroy', $usuario->id) }}" onsubmit="return confirm('¿Eliminar este usuario?');" style="margin:0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar</button>
                </form>
                @endif
            </div>
        </div>
    </div>
</div>

@if($usuario->puedeTenerMetaComercial() && $puedeEditarMeta)
<div id="modalMetaUsuario" class="modal" style="z-index: 3000;">
    <div class="modal-box" style="max-width: 520px;">
        <div class="modal-header">
            <div class="modal-title">Editar meta comercial</div>
            <button type="button" class="modal-close" onclick="cerrarModalMetaUsuario()">✕</button>
        </div>
        <form method="POST" action="{{ route('usuarios.meta-comercial.update', $usuario) }}">
            @csrf
            @method('PUT')
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Meta mensual sin IVA (MXN) <span class="req">*</span></label>
                    <input type="number" name="meta_ventas_mensual" class="form-control"
                           step="0.01" min="0.01" required
                           value="{{ old('meta_ventas_mensual', $usuario->meta_ventas_mensual) }}"
                           placeholder="0.00">
                    <div style="margin-top:6px; font-size:12px; color:var(--color-gray-500);">
                        Monto fijo por mes. Se compara contra todas las facturas del vendedor (subtotal sin IVA).
                    </div>
                </div>
            </div>
            <div class="modal-footer" style="display:flex; gap:12px; justify-content:flex-end;">
                <button type="button" class="btn btn-light" onclick="cerrarModalMetaUsuario()">Cancelar</button>
                <button type="submit" class="btn btn-primary">✓ Guardar</button>
            </div>
        </form>
    </div>
</div>
@endif
@endsection

@if($usuario->puedeTenerMetaComercial() && $puedeEditarMeta)
@push('scripts')
<script>
    function abrirModalMetaUsuario() {
        document.getElementById('modalMetaUsuario')?.classList.add('show');
    }
    function cerrarModalMetaUsuario() {
        document.getElementById('modalMetaUsuario')?.classList.remove('show');
    }
</script>
@endpush
@endif
