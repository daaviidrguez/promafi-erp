@extends('layouts.app')

@section('title', 'Editar Cliente')
@section('page-title', '✏️ Editar Cliente')
@section('page-subtitle', $cliente->nombre)

@php
$breadcrumbs = [
    ['title' => 'Clientes', 'url' => route('clientes.index')],
    ['title' => $cliente->nombre, 'url' => route('clientes.show', $cliente->id)],
    ['title' => 'Editar']
];
@endphp

@section('content')

<form method="POST" action="{{ route('clientes.update', $cliente->id) }}">
    @csrf
    @method('PUT')

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

        {{-- Columna izquierda --}}
        <div>
            {{-- Datos Generales --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📋 Datos Generales</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Nombre / Razón Social <span class="req">*</span></label>
                        <input type="text" name="nombre" class="form-control"
                               value="{{ old('nombre', $cliente->nombre) }}" required>
                        @error('nombre')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control"
                               value="{{ old('nombre_comercial', $cliente->nombre_comercial) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Persona <span class="req">*</span></label>
                        <select name="tipo_persona" class="form-control" required>
                            <option value="fisica" {{ old('tipo_persona', $cliente->tipo_persona ?? 'fisica') === 'fisica' ? 'selected' : '' }}>Persona Física</option>
                            <option value="moral" {{ old('tipo_persona', $cliente->tipo_persona ?? 'fisica') === 'moral' ? 'selected' : '' }}>Persona Moral</option>
                        </select>
                        @error('tipo_persona')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>
                </div>
            </div>

            {{-- Contacto --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📞 Contacto</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="{{ old('email', $cliente->email) }}">
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control"
                                   value="{{ old('telefono', $cliente->telefono) }}" maxlength="15">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-control"
                                   value="{{ old('celular', $cliente->celular) }}" maxlength="15">
                        </div>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Nombre del contacto</label>
                            <input type="text" name="contacto_nombre" class="form-control"
                                   value="{{ old('contacto_nombre', $cliente->contacto_nombre) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Puesto</label>
                            <input type="text" name="contacto_puesto" class="form-control"
                                   value="{{ old('contacto_puesto', $cliente->contacto_puesto) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Domicilio Fiscal --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📍 Domicilio Fiscal</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Calle</label>
                        <input type="text" name="calle" class="form-control"
                               value="{{ old('calle', $cliente->calle) }}">
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Núm. Exterior</label>
                            <input type="text" name="numero_exterior" class="form-control"
                                   value="{{ old('numero_exterior', $cliente->numero_exterior) }}" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Núm. Interior</label>
                            <input type="text" name="numero_interior" class="form-control"
                                   value="{{ old('numero_interior', $cliente->numero_interior) }}" maxlength="10">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Colonia</label>
                        <input type="text" name="colonia" class="form-control"
                               value="{{ old('colonia', $cliente->colonia) }}">
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="ciudad" class="form-control"
                                   value="{{ old('ciudad', $cliente->ciudad) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <input type="text" name="estado" class="form-control"
                                   value="{{ old('estado', $cliente->estado) }}">
                        </div>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 120px 100px; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Código Postal</label>
                            <input type="text" name="codigo_postal" class="form-control"
                                   value="{{ old('codigo_postal', $cliente->codigo_postal) }}" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label class="form-label">País</label>
                            <input type="text" name="pais" class="form-control"
                                   value="{{ old('pais', $cliente->pais ?? 'MEX') }}" maxlength="3">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Columna derecha --}}
        <div>
            {{-- Información Fiscal --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📑 Información Fiscal</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">RFC <span class="req">*</span></label>
                        <input type="text" name="rfc" id="rfc" class="form-control text-mono"
                               value="{{ old('rfc', $cliente->rfc) }}" maxlength="13" required
                               style="text-transform: uppercase;">
                        @error('rfc')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Régimen Fiscal</label>
                        <input type="text" name="regimen_fiscal" class="form-control"
                               value="{{ old('regimen_fiscal', $cliente->regimen_fiscal) }}" maxlength="3" placeholder="Ej: 601">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Uso de CFDI <span class="req">*</span></label>
                        <select name="uso_cfdi_default" class="form-control" required>
                            @foreach(['G03'=>'G03 - Gastos en general','P01'=>'P01 - Por definir','S01'=>'S01 - Sin efectos fiscales','D01'=>'D01 - Honorarios médicos','D02'=>'D02 - Gastos médicos','D03'=>'D03 - Gastos funerales','D04'=>'D04 - Donativos','I01'=>'I01 - Construcciones'] as $val => $label)
                            <option value="{{ $val }}" {{ old('uso_cfdi_default', $cliente->uso_cfdi_default) == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Config. Comercial --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">💼 Config. Comercial</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Días de Crédito</label>
                        <input type="number" name="dias_credito" class="form-control"
                               value="{{ old('dias_credito', $cliente->dias_credito) }}" min="0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Límite de Crédito</label>
                        <input type="number" name="limite_credito" class="form-control"
                               value="{{ old('limite_credito', $cliente->limite_credito) }}" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descuento (%)</label>
                        <input type="number" name="descuento_porcentaje" class="form-control"
                               value="{{ old('descuento_porcentaje', $cliente->descuento_porcentaje) }}" min="0" max="100" step="0.01">
                    </div>
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="activo" value="1"
                                   {{ old('activo', $cliente->activo) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;">
                            Cliente Activo
                        </label>
                    </div>
                </div>
            </div>

            {{-- Notas --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📝 Notas</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Información adicional</label>
                        <textarea name="notas" class="form-control"
                                  rows="4">{{ old('notas', $cliente->notas) }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('clientes.show', $cliente->id) }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Actualizar Cliente</button>
        </div>
    </div>
</form>

@endsection

@push('scripts')
<script>
    document.getElementById('rfc').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
</script>
@endpush
