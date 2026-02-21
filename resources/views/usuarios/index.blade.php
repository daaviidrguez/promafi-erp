@extends('layouts.app')
@section('title', 'Usuarios')
@section('page-title', '👤 Usuarios')
@section('page-subtitle', 'Gestión de usuarios del sistema')
@section('page-actions')
    <a href="{{ route('usuarios.create') }}" class="btn btn-primary">➕ Nuevo Usuario</a>
@endsection

@php
$breadcrumbs = [['title' => 'Usuarios']];
@endphp

@section('content')

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('usuarios.index') }}" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
            <input type="text" name="search" value="{{ $search ?? '' }}" placeholder="Buscar por nombre o email..." class="form-control" style="min-width:240px;">
            <button type="submit" class="btn btn-primary">🔍 Buscar</button>
            @if($search ?? false)
            <a href="{{ route('usuarios.index') }}" class="btn btn-light">✕ Limpiar</a>
            @endif
        </form>
    </div>
</div>

<div class="table-container">
    @if($usuarios->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th class="td-center">Rol</th>
                <th class="td-center">Estado</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($usuarios as $u)
            <tr>
                <td><div class="fw-600 text-primary">{{ $u->name }}</div></td>
                <td class="text-mono">{{ $u->email }}</td>
                <td class="td-center">{{ $u->role ? $u->role->display_name : '—' }}</td>
                <td class="td-center">
                    @if($u->activo)<span class="badge badge-success">Activo</span>@else<span class="badge badge-danger">Inactivo</span>@endif
                </td>
                <td class="td-actions">
                    <a href="{{ route('usuarios.show', $u->id) }}" class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                    <a href="{{ route('usuarios.edit', $u->id) }}" class="btn btn-warning btn-sm btn-icon" title="Editar">✏️</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    <div style="padding:16px 20px; border-top:1px solid var(--color-gray-100);">{{ $usuarios->withQueryString()->links() }}</div>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">👤</div>
        <div class="empty-state-title">No hay usuarios</div>
        <div class="empty-state-text">Crea usuarios para que accedan al sistema</div>
        <a href="{{ route('usuarios.create') }}" class="btn btn-primary" style="margin-top:16px;">➕ Nuevo Usuario</a>
    </div>
    @endif
</div>

@endsection
