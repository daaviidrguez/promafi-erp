@extends('layouts.app')

@section('title', 'Nueva Categoría')
@section('page-title', '➕ Nueva Categoría')
@section('page-subtitle', 'Crear categoría de productos')

@php
$breadcrumbs = [
    ['title' => 'Categorías', 'url' => route('categorias.index')],
    ['title' => 'Nueva Categoría']
];
@endphp

@section('content')

<form method="POST" action="{{ route('categorias.store') }}">
    @csrf

    <div class="card">
        <div class="card-body">

            <div class="form-group">
                <label class="form-label">Nombre *</label>
                <input type="text" name="nombre" class="form-control" required>
            </div>

            <div class="form-group">
                <label class="form-label">Código</label>
                <input type="text" name="codigo" class="form-control text-mono">
            </div>

            <div class="form-group">
                <label class="form-label">Categoría Padre</label>
                <select name="parent_id" class="form-control">
                    <option value="">Sin padre (Raíz)</option>
                    @foreach($categorias as $cat)
                        <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                    @endforeach
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">Icono</label>
                <input type="text" name="icono" class="form-control" placeholder="Ej: 📦">
            </div>

            <div class="form-group">
                <label class="form-label">Orden</label>
                <input type="number" name="orden" class="form-control" value="0">
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-body" style="display:flex; justify-content:flex-end; gap:12px;">
            <a href="{{ route('categorias.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">Guardar Categoría</button>
        </div>
    </div>

</form>

@endsection