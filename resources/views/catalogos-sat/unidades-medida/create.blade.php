@extends('layouts.app')

@section('title', 'Nueva unidad de medida')
@section('page-title', 'Nueva unidad de medida')

@php
$breadcrumbs = [
    ['title' => 'Catálogos SAT', 'url' => route('catalogos-sat.index')],
    ['title' => 'Unidades de medida', 'url' => route('catalogos-sat.unidades-medida.index')],
    ['title' => 'Nuevo']
];
@endphp

@section('content')

<form method="POST" action="{{ route('catalogos-sat.unidades-medida.store') }}">
    @csrf
    <div class="card">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Clave *</label>
                <input type="text" name="clave" class="form-control text-mono" value="{{ old('clave') }}" maxlength="10" required>
                @error('clave')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Descripción *</label>
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
            <a href="{{ route('catalogos-sat.unidades-medida.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
    </div>
</form>

@endsection
