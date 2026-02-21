@extends('layouts.app')
@section('title', 'Nuevo Usuario')
@section('page-title', '➕ Nuevo Usuario')
@section('page-subtitle', 'Registrar usuario del sistema')

@php $breadcrumbs = [['title' => 'Usuarios', 'url' => route('usuarios.index')], ['title' => 'Nuevo']]; @endphp

@section('content')
<form method="POST" action="{{ route('usuarios.store') }}">
    @csrf
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Datos del usuario</div></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Nombre <span class="req">*</span></label>
                    <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
                    @error('name')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Email <span class="req">*</span></label>
                    <input type="email" name="email" class="form-control" value="{{ old('email') }}" required>
                    @error('email')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Contraseña <span class="req">*</span></label>
                    <input type="password" name="password" class="form-control" required autocomplete="new-password">
                    @error('password')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Confirmar contraseña <span class="req">*</span></label>
                    <input type="password" name="password_confirmation" class="form-control" required autocomplete="new-password">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Rol <span class="req">*</span></label>
                    <select name="role_id" class="form-control" required>
                        <option value="">Seleccionar...</option>
                        @foreach($roles as $r)
                            <option value="{{ $r->id }}" {{ old('role_id') == $r->id ? 'selected' : '' }}>{{ $r->display_name }}</option>
                        @endforeach
                    </select>
                    @error('role_id')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;">
                    <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}> Activo
                    </label>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="{{ route('usuarios.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Usuario</button>
        </div>
    </div>
</form>
@endsection
