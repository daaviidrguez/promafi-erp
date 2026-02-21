@extends('layouts.app')
@section('title', 'Roles y permisos')
@section('page-title', '🔐 Roles y permisos')
@section('page-subtitle', 'Asignar permisos por rol')

@php
$breadcrumbs = [['title' => 'Roles y permisos']];
@endphp

@section('content')

<div class="table-container">
    @if($roles->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Rol</th>
                <th>Descripción</th>
                <th class="td-center">Permisos</th>
                <th class="td-center">Usuarios</th>
                <th class="td-actions">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($roles as $r)
            <tr>
                <td><div class="fw-600 text-primary">{{ $r->display_name }}</div><span class="text-muted" style="font-size:12px;">{{ $r->name }}</span></td>
                <td>{{ $r->description ?? '—' }}</td>
                <td class="td-center">{{ $r->permissions->count() }}</td>
                <td class="td-center">{{ $r->users_count ?? 0 }}</td>
                <td class="td-actions">
                    <a href="{{ route('roles.show', $r->id) }}" class="btn btn-info btn-sm btn-icon" title="Ver">👁️</a>
                    <a href="{{ route('roles.edit', $r->id) }}" class="btn btn-warning btn-sm btn-icon" title="Editar permisos">✏️</a>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <div class="empty-state">
        <div class="empty-state-icon">🔐</div>
        <div class="empty-state-title">No hay roles</div>
        <div class="empty-state-text">Ejecuta las migraciones para crear los roles por defecto.</div>
    </div>
    @endif
</div>

@endsection
