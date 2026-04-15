@extends('layouts.app')
@section('title', 'Editar Proveedor')
@section('page-title', '✏️ Editar Proveedor')
@section('page-subtitle', $proveedor->nombre)

@php $breadcrumbs = [['title' => 'Proveedores', 'url' => route('proveedores.index')], ['title' => $proveedor->nombre], ['title' => 'Editar']]; @endphp

@section('content')
<form method="POST" action="{{ route('proveedores.update', $proveedor->id) }}">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Datos del Proveedor</div></div>
        <div class="card-body">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Nombre / Razón Social <span class="req">*</span></label>
                    <input type="text" name="nombre" class="form-control" value="{{ old('nombre', $proveedor->nombre) }}" required>
                    @error('nombre')<span class="form-hint" style="color:var(--color-danger);">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Código</label>
                    <input type="text" name="codigo" class="form-control text-mono" value="{{ old('codigo', $proveedor->codigo) }}">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">RFC</label>
                    <input type="text" name="rfc" class="form-control text-mono" value="{{ old('rfc', $proveedor->rfc) }}" maxlength="13" style="text-transform:uppercase;">
                </div>
                <div class="form-group">
                    <label class="form-label">Días de crédito</label>
                    <input type="number" name="dias_credito" class="form-control" value="{{ old('dias_credito', $proveedor->dias_credito) }}" min="0">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Régimen Fiscal</label>
                    <select name="regimen_fiscal" class="form-control">
                        <option value="">Seleccionar...</option>
                        @foreach($regimenes ?? [] as $r)
                            <option value="{{ $r->clave }}" {{ old('regimen_fiscal', $proveedor->regimen_fiscal) == $r->clave ? 'selected' : '' }}>{{ $r->etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Uso de CFDI</label>
                    <select name="uso_cfdi" class="form-control">
                        <option value="">Seleccionar...</option>
                        @foreach($usosCfdi ?? [] as $u)
                            <option value="{{ $u->clave }}" {{ old('uso_cfdi', $proveedor->uso_cfdi) == $u->clave ? 'selected' : '' }}>{{ $u->etiqueta }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ old('email', $proveedor->email) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control" value="{{ old('telefono', $proveedor->telefono) }}">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                    <input type="checkbox" name="activo" value="1" {{ old('activo', $proveedor->activo) ? 'checked' : '' }}> Activo
                </label>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="{{ route('proveedores.show', $proveedor->id) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Actualizar</button>
        </div>
    </div>
</form>
@endsection
