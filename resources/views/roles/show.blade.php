@extends('layouts.app')
@section('title', $role->display_name)
@section('page-title', $role->display_name)
@section('page-subtitle', 'Rol')

@php $breadcrumbs = [['title' => 'Roles y permisos', 'url' => route('roles.index')], ['title' => $role->display_name]]; @endphp

@section('content')
<div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;">
    <div>
        <div class="card">
            <div class="card-header">
                <div class="card-title">📋 Datos del rol</div>
                <a href="{{ route('roles.edit', $role->id) }}" class="btn btn-primary btn-sm">✏️ Editar permisos</a>
            </div>
            <div class="card-body">
                <div class="info-grid-2">
                    <div class="info-row"><div class="info-label">Nombre interno</div><div class="info-value text-mono">{{ $role->name }}</div></div>
                    <div class="info-row"><div class="info-label">Nombre a mostrar</div><div class="info-value">{{ $role->display_name }}</div></div>
                    <div class="info-row"><div class="info-label">Descripción</div><div class="info-value">{{ $role->description ?? '—' }}</div></div>
                </div>
            </div>
        </div>
        <div class="card" style="margin-top:20px;">
            <div class="card-header"><div class="card-title">🔑 Permisos asignados</div></div>
            <div class="card-body">
                @if($role->permissions->count() > 0)
                <ul style="margin:0; padding-left:20px; display:grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:6px;">
                    @foreach($role->permissions->groupBy('module') as $module => $perms)
                    <li style="list-style:none; margin:0;">
                        <div class="fw-600 text-primary" style="font-size:12px; margin-bottom:4px;">{{ $module }}</div>
                        <ul style="margin:0; padding-left:16px; font-size:13px;">
                            @foreach($perms as $p)
                            <li>{{ $p->name }}</li>
                            @endforeach
                        </ul>
                    </li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted" style="margin:0;">Sin permisos asignados.</p>
                @endif
            </div>
        </div>
    </div>
    <div>
        <div class="card">
            <div class="card-header"><div class="card-title">👤 Usuarios con este rol</div></div>
            <div class="card-body">
                @if($role->users->count() > 0)
                <ul style="margin:0; padding-left:20px;">
                    @foreach($role->users as $u)
                    <li><a href="{{ route('usuarios.show', $u->id) }}">{{ $u->name }}</a> <span class="text-muted">({{ $u->email }})</span></li>
                    @endforeach
                </ul>
                @else
                <p class="text-muted" style="margin:0;">Ningún usuario con este rol.</p>
                @endif
                <a href="{{ route('usuarios.create') }}" class="btn btn-outline btn-sm" style="margin-top:12px;">➕ Nuevo usuario</a>
            </div>
        </div>
    </div>
</div>
@endsection
