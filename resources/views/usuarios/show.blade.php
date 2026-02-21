@extends('layouts.app')
@section('title', $usuario->name)
@section('page-title', $usuario->name)
@section('page-subtitle', 'Usuario')

@php $breadcrumbs = [['title' => 'Usuarios', 'url' => route('usuarios.index')], ['title' => $usuario->name]]; @endphp

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
@endsection
