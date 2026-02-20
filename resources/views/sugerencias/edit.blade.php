@extends('layouts.app')
@section('title', 'Editar Sugerencia')
@section('page-title', '✏️ Editar Sugerencia')
@section('page-subtitle', Str::limit($sugerencia->descripcion, 50))

@php
$breadcrumbs = [['title' => 'Sugerencias', 'url' => route('sugerencias.index')], ['title' => 'Ver', 'url' => route('sugerencias.show', $sugerencia->id)], ['title' => 'Editar']];
@endphp

@section('content')
<form method="POST" action="{{ route('sugerencias.update', $sugerencia->id) }}">
    @csrf
    @method('PUT')
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Datos de la partida</div></div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Código / Clave (opcional)</label>
                <input type="text" name="codigo" class="form-control text-mono" value="{{ old('codigo', $sugerencia->codigo) }}" placeholder="Ej. 46557, CPS26" maxlength="50">
            </div>
            <div class="form-group">
                <label class="form-label">Descripción <span class="req">*</span></label>
                <textarea name="descripcion" class="form-control" rows="3" required>{{ old('descripcion', $sugerencia->descripcion) }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Unidad</label>
                    <input type="text" name="unidad" class="form-control" value="{{ old('unidad', $sugerencia->unidad) }}" maxlength="10">
                </div>
                <div class="form-group">
                    <label class="form-label">Precio unitario <span class="req">*</span></label>
                    <input type="number" name="precio_unitario" class="form-control" value="{{ old('precio_unitario', $sugerencia->precio_unitario) }}" min="0" step="0.01" required>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="{{ route('sugerencias.show', $sugerencia->id) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Actualizar</button>
        </div>
    </div>
</form>
@endsection
