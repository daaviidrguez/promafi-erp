@extends('layouts.app')
@section('title', 'Editar Rol')
@section('page-title', '✏️ Editar rol')
@section('page-subtitle', $role->display_name)

@php $breadcrumbs = [['title' => 'Roles y permisos', 'url' => route('roles.index')], ['title' => $role->display_name, 'url' => route('roles.show', $role->id)], ['title' => 'Editar']]; @endphp

@section('content')
<form method="POST" action="{{ route('roles.update', $role->id) }}">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Datos del rol</div></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Nombre a mostrar <span class="req">*</span></label>
                    <input type="text" name="display_name" class="form-control" value="{{ old('display_name', $role->display_name) }}" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Descripción</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description', $role->description) }}">
                </div>
            </div>
            <p class="text-muted" style="font-size:13px; margin:0 0 12px 0;">Nombre interno: <strong>{{ $role->name }}</strong> (no editable)</p>
        </div>
    </div>
    <div class="card">
        <div class="card-header"><div class="card-title">🔑 Permisos</div></div>
        <div class="card-body">
            @foreach($permissions as $module => $items)
            <div style="margin-bottom:20px;">
                <div class="fw-600 text-primary" style="margin-bottom:10px; font-size:13px;">{{ $module }}</div>
                <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap:8px;">
                    @foreach($items as $p)
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:13px;">
                        <input type="checkbox" name="permissions[]" value="{{ $p->id }}" {{ $role->permissions->contains('id', $p->id) ? 'checked' : '' }}>
                        {{ $p->name }}
                    </label>
                    @endforeach
                </div>
            </div>
            @endforeach
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="{{ route('roles.show', $role->id) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Actualizar rol</button>
        </div>
    </div>
</form>
@endsection
