@extends('layouts.app')

@section('title', 'Nuevo uso de CFDI')
@section('page-title', 'Nuevo uso de CFDI')

@php
$breadcrumbs = [
    ['title' => 'Catálogos SAT', 'url' => route('catalogos-sat.index')],
    ['title' => 'Usos de CFDI', 'url' => route('catalogos-sat.usos-cfdi.index')],
    ['title' => 'Nuevo']
];
@endphp

@section('content')

<form method="POST" action="{{ route('catalogos-sat.usos-cfdi.store') }}">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Clave *</label>
                <input type="text" name="clave" class="form-control text-mono" value="{{ old('clave') }}" maxlength="10" required>
                @error('clave')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Descripcion *</label>
                <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion') }}" required>
                @error('descripcion')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Orden</label>
                <input type="number" name="orden" class="form-control" value="{{ old('orden', 0) }}" min="0">
            </div>
            <div class="form-group">
                <label class="form-label"><input type="checkbox" name="activo" value="1" {{ old('activo', true) ? 'checked' : '' }}> Activo</label>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex; justify-content:flex-end; gap:12px;">
            <a href="{{ route('catalogos-sat.usos-cfdi.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</form>

@endsection
