@extends('layouts.app')

@section('title', 'Nuevo Contacto')
@section('page-title', '➕ Nuevo Contacto')
@section('page-subtitle', 'Cliente: ' . $cliente->nombre)

@php
$breadcrumbs = [
    ['title' => 'Clientes', 'url' => route('clientes.index')],
    ['title' => $cliente->nombre, 'url' => route('clientes.show', $cliente)],
    ['title' => 'Nuevo Contacto']
];
@endphp

@section('content')

<form method="POST" action="{{ route('clientes.contactos.store', $cliente) }}">
    @csrf

    <div class="card">
        <div class="card-header">
            <div class="card-title">📇 Información del Contacto</div>
        </div>

        <div class="card-body">

            <div class="form-group">
                <label class="form-label">Nombre Completo <span class="req">*</span></label>
                <input type="text" name="nombre" class="form-control"
                       value="{{ old('nombre') }}" required>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Puesto</label>
                    <input type="text" name="puesto" class="form-control"
                           value="{{ old('puesto') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Departamento</label>
                    <input type="text" name="departamento" class="form-control"
                           value="{{ old('departamento') }}">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email') }}">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control"
                           value="{{ old('telefono') }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Celular</label>
                    <input type="text" name="celular" class="form-control"
                           value="{{ old('celular') }}">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox" name="principal" value="1">
                    Contacto Principal
                </label>
            </div>

            <div class="form-group">
                <label class="form-label" style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox" name="activo" value="1" checked>
                    Activo
                </label>
            </div>

            <div class="form-group">
                <label class="form-label">Notas</label>
                <textarea name="notas" class="form-control" rows="3">{{ old('notas') }}</textarea>
            </div>

        </div>
    </div>

    <div class="card">
        <div class="card-body" style="display:flex; gap:12px; justify-content:flex-end;">
            <a href="{{ route('clientes.show', $cliente) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Contacto</button>
        </div>
    </div>

</form>

@endsection