@extends('layouts.app')
@section('title', 'Sugerencia')
@section('page-title', '💡 Sugerencia')
@section('page-subtitle', Str::limit($sugerencia->descripcion, 50))

@php $breadcrumbs = [['title' => 'Sugerencias', 'url' => route('sugerencias.index')], ['title' => 'Ver']]; @endphp

@section('content')
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Datos de la partida</div>
                <a href="{{ route('sugerencias.edit', $sugerencia->id) }}" class="btn btn-primary btn-sm">✏️ Editar</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Código / Clave</div><div class="info-value text-mono">{{ $sugerencia->codigo ?? '—' }}</div></div>
                    <div class="info-row"><div class="info-label">Unidad</div><div class="info-value">{{ $sugerencia->unidad }}</div></div>
                    <div class="info-row"><div class="info-label">Precio unitario</div><div class="info-value text-mono">${{ number_format($sugerencia->precio_unitario, 2) }}</div></div>
                </div>
                <div class="form-group" style="margin-top:16px;">
                    <div class="info-label" style="margin-bottom:6px;">Descripción</div>
                    <div style="background:var(--color-gray-50); border:1px solid var(--color-gray-200); border-radius:var(--radius-md); padding:12px 16px; font-size:14px;">{{ $sugerencia->descripcion }}</div>
                </div>
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">⚡ Acciones</div></div>
            <div class="card-body" style="display:flex;flex-direction:column;gap:10px;">
                <a href="{{ route('sugerencias.edit', $sugerencia->id) }}" class="btn btn-primary w-full">✏️ Editar</a>
                <a href="{{ route('cotizaciones.create') }}" class="btn btn-outline w-full">📋 Ir a Nueva Cotización</a>
                <form method="POST" action="{{ route('sugerencias.destroy', $sugerencia->id) }}" onsubmit="return confirm('¿Eliminar esta sugerencia?');" style="margin:0;">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-danger w-full">🗑️ Eliminar</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
