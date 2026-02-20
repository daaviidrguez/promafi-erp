@extends('layouts.app')

@section('title', 'Nuevo Cliente')
@section('page-title', '➕ Nuevo Cliente')
@section('page-subtitle', 'Registrar nuevo cliente en el sistema')

@php
$breadcrumbs = [
    ['title' => 'Clientes', 'url' => route('clientes.index')],
    ['title' => 'Nuevo Cliente']
];
@endphp

@section('content')

<form method="POST" action="{{ route('clientes.store') }}">
    @csrf

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
                               value="{{ old('nombre') }}" required>
                        @error('nombre')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control"
                               value="{{ old('nombre_comercial') }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tipo de Persona <span class="req">*</span></label>
                        <select name="tipo_persona" class="form-control" required>
                            <option value="fisica" {{ old('tipo_persona', 'fisica') === 'fisica' ? 'selected' : '' }}>Persona Física</option>
                            <option value="moral" {{ old('tipo_persona') === 'moral' ? 'selected' : '' }}>Persona Moral</option>
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
                               value="{{ old('email') }}">
                        @error('email')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control"
                                   value="{{ old('telefono') }}" maxlength="15">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Celular</label>
                            <input type="text" name="celular" class="form-control"
                                   value="{{ old('celular') }}" maxlength="15">
                        </div>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Nombre del contacto</label>
                            <input type="text" name="contacto_nombre" class="form-control"
                                   value="{{ old('contacto_nombre') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Puesto</label>
                            <input type="text" name="contacto_puesto" class="form-control"
                                   value="{{ old('contacto_puesto') }}">
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
                               value="{{ old('calle') }}">
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Núm. Exterior</label>
                            <input type="text" name="numero_exterior" class="form-control"
                                   value="{{ old('numero_exterior') }}" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Núm. Interior</label>
                            <input type="text" name="numero_interior" class="form-control"
                                   value="{{ old('numero_interior') }}" maxlength="10">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Colonia</label>
                        <input type="text" name="colonia" class="form-control"
                               value="{{ old('colonia') }}">
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Ciudad</label>
                            <input type="text" name="ciudad" class="form-control"
                                   value="{{ old('ciudad') }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <input type="text" name="estado" class="form-control"
                                   value="{{ old('estado') }}">
                        </div>
                    </div>
                    <div class="form-row" style="display: grid; grid-template-columns: 120px 100px; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Código Postal</label>
                            <input type="text" name="codigo_postal" class="form-control"
                                   value="{{ old('codigo_postal') }}" maxlength="5">
                        </div>
                        <div class="form-group">
                            <label class="form-label">País</label>
                            <input type="text" name="pais" class="form-control"
                                   value="{{ old('pais', 'MEX') }}" maxlength="3">
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
                               value="{{ old('rfc') }}" maxlength="13" required
                               style="text-transform: uppercase;">
                        @error('rfc')
                            <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                        @enderror
                    </div>
                    <div class="form-group">
                        <label class="form-label">Régimen Fiscal</label>
                        <input type="text" name="regimen_fiscal" class="form-control"
                               value="{{ old('regimen_fiscal') }}" maxlength="3" placeholder="Ej: 601">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Uso de CFDI <span class="req">*</span></label>
                        <select name="uso_cfdi_default" class="form-control" required>
                            @foreach(['G03'=>'G03 - Gastos en general','P01'=>'P01 - Por definir','S01'=>'S01 - Sin efectos fiscales','D01'=>'D01 - Honorarios médicos','D02'=>'D02 - Gastos médicos','D03'=>'D03 - Gastos funerales','D04'=>'D04 - Donativos','I01'=>'I01 - Construcciones'] as $val => $label)
                            <option value="{{ $val }}" {{ old('uso_cfdi_default', 'G03') == $val ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            {{-- Configuración Comercial --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">💼 Config. Comercial</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Días de Crédito</label>
                        <input type="number" name="dias_credito" class="form-control"
                               value="{{ old('dias_credito', 0) }}" min="0">
                        <span class="form-hint">0 = Contado | 30, 60, 90 = Crédito</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Límite de Crédito</label>
                        <input type="number" name="limite_credito" class="form-control"
                               value="{{ old('limite_credito', 0) }}" min="0" step="0.01">
                        <span class="form-hint">$0.00 = Sin límite</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Descuento (%)</label>
                        <input type="number" name="descuento_porcentaje" class="form-control"
                               value="{{ old('descuento_porcentaje', 0) }}" min="0" max="100" step="0.01">
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
                        <textarea name="notas" class="form-control" rows="4">{{ old('notas') }}</textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('clientes.index') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Cliente</button>
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
