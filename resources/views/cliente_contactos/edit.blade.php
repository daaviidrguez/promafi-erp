@extends('layouts.app')

@section('title', 'Editar Contacto')
@section('page-title', '✏️ Editar Contacto')
@section('page-subtitle', $contacto->nombre . ' — Cliente: ' . $cliente->nombre)

@php
$breadcrumbs = [
    ['title' => 'Clientes', 'url' => route('clientes.index')],
    ['title' => $cliente->nombre, 'url' => route('clientes.show', $cliente)],
    ['title' => 'Editar Contacto']
];
@endphp

@section('content')

<form method="POST" action="{{ route('clientes.contactos.update', [$cliente, $contacto]) }}">
    @csrf
    @method('PUT')

    <div class="card">
        <div class="card-header">
            <div class="card-title">📇 Información del Contacto</div>
        </div>

        <div class="card-body">

            {{-- Nombre --}}
            <div class="form-group">
                <label class="form-label">Nombre Completo <span class="req">*</span></label>
                <input type="text" name="nombre" class="form-control"
                       value="{{ old('nombre', $contacto->nombre) }}" required>
                @error('nombre')
                    <span class="form-hint" style="color: var(--color-danger);">
                        {{ $message }}
                    </span>
                @enderror
            </div>

            {{-- Puesto y Departamento --}}
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Puesto</label>
                    <input type="text" name="puesto" class="form-control"
                           value="{{ old('puesto', $contacto->puesto) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Departamento</label>
                    <input type="text" name="departamento" class="form-control"
                           value="{{ old('departamento', $contacto->departamento) }}">
                </div>
            </div>

            {{-- Email --}}
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control"
                       value="{{ old('email', $contacto->email) }}">
            </div>

            {{-- Teléfono y Celular --}}
            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Teléfono</label>
                    <input type="text" name="telefono" class="form-control"
                           value="{{ old('telefono', $contacto->telefono) }}">
                </div>

                <div class="form-group">
                    <label class="form-label">Celular</label>
                    <input type="text" name="celular" class="form-control"
                           value="{{ old('celular', $contacto->celular) }}">
                </div>
            </div>

            {{-- Principal --}}
            <div class="form-group">
                <label class="form-label" style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox"
                           name="principal"
                           value="1"
                           {{ old('principal', $contacto->principal) ? 'checked' : '' }}>
                    Contacto Principal
                </label>
            </div>

            {{-- Activo --}}
            <div class="form-group">
                <label class="form-label" style="display:flex; gap:8px; align-items:center;">
                    <input type="checkbox"
                           name="activo"
                           value="1"
                           {{ old('activo', $contacto->activo) ? 'checked' : '' }}>
                    Activo
                </label>
            </div>

            {{-- Notas --}}
            <div class="form-group">
                <label class="form-label">Notas</label>
                <textarea name="notas"
                          class="form-control"
                          rows="3">{{ old('notas', $contacto->notas) }}</textarea>
            </div>

        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display:flex; gap:12px; justify-content:flex-end;">
            <a href="{{ route('clientes.show', $cliente) }}"
               class="btn btn-light">Cancelar</a>

            <button type="submit"
                    class="btn btn-primary">
                ✓ Actualizar Contacto
            </button>
        </div>
    </div>

</form>

@endsection