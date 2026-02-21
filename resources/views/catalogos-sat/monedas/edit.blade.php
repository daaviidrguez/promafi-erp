@extends('layouts.app')

@section('title', 'Editar moneda')
@section('page-title', 'Editar moneda')

@php
$breadcrumbs = [
    ['title' => 'Catálogos SAT', 'url' => route('catalogos-sat.index')],
    ['title' => 'Monedas', 'url' => route('catalogos-sat.monedas.index')],
    ['title' => 'Editar']
];
@endphp

@section('content')

<form method="POST" action="{{ route('catalogos-sat.monedas.update', $item) }}">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Clave *</label>
                <input type="text" name="clave" class="form-control text-mono" value="{{ old('clave', $item->clave) }}" maxlength="10" required>
                @error('clave')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Descripción *</label>
                <input type="text" name="descripcion" class="form-control" value="{{ old('descripcion', $item->descripcion) }}" required>
                @error('descripcion')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Orden</label>
                <input type="number" name="orden" class="form-control" value="{{ old('orden', $item->orden) }}" min="0">
            </div>
            <div class="form-group">
                <label class="form-label">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" {{ old('activo', $item->activo) ? 'checked' : '' }}> Activo
                </label>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex; justify-content:flex-end; gap:12px;">
            <a href="{{ route('catalogos-sat.monedas.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">Actualizar</button>
        </div>
    </div>
</form>

@endsection
