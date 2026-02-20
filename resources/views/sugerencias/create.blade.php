@extends('layouts.app')
@section('title', 'Nueva Sugerencia')
@section('page-title', '➕ Nueva Sugerencia')
@section('page-subtitle', 'Partida para autocompletar en cotizaciones manuales')

@php $breadcrumbs = [['title' => 'Sugerencias', 'url' => route('sugerencias.index')], ['title' => 'Nueva']]; @endphp

@section('content')
<form method="POST" action="{{ route('sugerencias.store') }}">
    @csrf
    <div class="card">
        <div class="card-header"><div class="card-title">📋 Datos de la partida</div></div>
        <div class="card-body">
            <div class="form-group">
                <label class="form-label">Código / Clave (opcional)</label>
                <input type="text" name="codigo" class="form-control text-mono" value="{{ old('codigo') }}" placeholder="Ej. 46557, CPS26, 115085" maxlength="50">
                <span class="form-hint">Permite buscar por este código al cotizar (ej. modelo o referencia)</span>
            </div>
            <div class="form-group">
                <label class="form-label">Descripción <span class="req">*</span></label>
                <textarea name="descripcion" class="form-control" rows="3" required placeholder="Descripción del producto o servicio">{{ old('descripcion') }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <div class="form-group">
                    <label class="form-label">Unidad</label>
                    <input type="text" name="unidad" class="form-control" value="{{ old('unidad', 'PZA') }}" maxlength="10" placeholder="PZA">
                </div>
                <div class="form-group">
                    <label class="form-label">Precio unitario <span class="req">*</span></label>
                    <input type="number" name="precio_unitario" class="form-control" value="{{ old('precio_unitario', '0') }}" min="0" step="0.01" required>
                </div>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-body" style="display:flex;gap:12px;justify-content:flex-end;">
            <a href="{{ route('sugerencias.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Sugerencia</button>
        </div>
    </div>
</form>
@endsection
