@extends('layouts.app')

@section('title', 'Configuración de Empresa')
@section('page-title', '⚙️ Configuración de Empresa')
@section('page-subtitle', 'Datos fiscales y configuración del sistema')

@php
$breadcrumbs = [
    ['title' => 'Configuración de Empresa']
];
@endphp

@section('content')

<form method="POST" action="{{ route('empresa.update') }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')

    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">

        {{-- Columna izquierda --}}
        <div>

            {{-- Datos Fiscales --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🏛️ Datos Fiscales</div>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">RFC <span class="req">*</span></label>
                            <input type="text" name="rfc" id="rfc" class="form-control text-mono"
                                   value="{{ old('rfc', $empresa->rfc) }}"
                                   maxlength="13" required style="text-transform: uppercase;"
                                   placeholder="12 (moral) o 13 (física) caracteres">
                            <span class="form-hint">Persona moral: 12 caracteres (ej. XA1901231ABC). Persona física: 13 caracteres (ej. GODE901231ABC).</span>
                            @error('rfc')
                                <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Régimen Fiscal <span class="req">*</span></label>
                            <select name="regimen_fiscal" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                @foreach($regimenes ?? [] as $r)
                                    <option value="{{ $r->clave }}"
                                        {{ old('regimen_fiscal', $empresa->regimen_fiscal) == $r->clave ? 'selected' : '' }}>
                                        {{ $r->etiqueta }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Razón Social <span class="req">*</span></label>
                        <input type="text" name="razon_social" class="form-control"
                               value="{{ old('razon_social', $empresa->razon_social) }}" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nombre Comercial</label>
                        <input type="text" name="nombre_comercial" class="form-control"
                               value="{{ old('nombre_comercial', $empresa->nombre_comercial) }}">
                    </div>
                </div>
            </div>

            {{-- Domicilio Fiscal --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📍 Domicilio Fiscal</div>
                </div>
                <div class="card-body">
                    <div style="display: grid; grid-template-columns: 2fr 1fr 1fr; gap: 12px;">
                        <div class="form-group">
                            <label class="form-label">Calle</label>
                            <input type="text" name="calle" class="form-control"
                                   value="{{ old('calle', $empresa->calle) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. Ext.</label>
                            <input type="text" name="numero_exterior" class="form-control"
                                   value="{{ old('numero_exterior', $empresa->numero_exterior) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">No. Int.</label>
                            <input type="text" name="numero_interior" class="form-control"
                                   value="{{ old('numero_interior', $empresa->numero_interior) }}">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Colonia</label>
                            <input type="text" name="colonia" class="form-control"
                                   value="{{ old('colonia', $empresa->colonia) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Código Postal <span class="req">*</span></label>
                            <input type="text" name="codigo_postal" class="form-control"
                                   value="{{ old('codigo_postal', $empresa->codigo_postal) }}"
                                   maxlength="5" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Municipio</label>
                            <input type="text" name="municipio" class="form-control"
                                   value="{{ old('municipio', $empresa->municipio) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Estado</label>
                            <input type="text" name="estado" class="form-control"
                                   value="{{ old('estado', $empresa->estado) }}">
                        </div>
                    </div>
                </div>
            </div>

            {{-- Datos bancarios --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🏦 Datos Bancarios</div>
                </div>
                <div class="card-body">

                    <div class="form-group">
                        <label class="form-label">Banco</label>
                        <input type="text" name="banco" class="form-control"
                            value="{{ old('banco', $empresa->banco) }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Número de Cuenta</label>
                        <input type="text" name="numero_cuenta" class="form-control"
                            value="{{ old('numero_cuenta', $empresa->numero_cuenta) }}">
                    </div>

                    <div class="form-group">
                        <label class="form-label">CLABE</label>
                        <input type="text" name="clabe" class="form-control"
                            value="{{ old('clabe', $empresa->clabe) }}">
                    </div>

                </div>
            </div>

            {{-- Contacto --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📞 Contacto</div>
                </div>
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control"
                                   value="{{ old('email', $empresa->email) }}">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="telefono" class="form-control"
                                   value="{{ old('telefono', $empresa->telefono) }}">
                        </div>
                    </div>
                </div>
            </div>

        </div>

        {{-- Columna derecha --}}
        <div>
            
        {{-- Identidad Visual --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🖼 Identidad Visual</div>
                </div>
                <div class="card-body">

                    @if($empresa->logo_path)
                        <div style="margin-bottom:12px;">
                            <img src="{{ asset('storage/'.$empresa->logo_path) }}"
                                style="max-height:80px;">
                        </div>
                    @endif

                    <div class="form-group">
                        <label class="form-label">Logo</label>
                        <input type="file" name="logo" class="form-control"
                            accept="image/png,image/jpeg">
                    </div>

                </div>
            </div>

            {{-- QR identificación SAT --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📱 QR identificación SAT</div>
                </div>
                <div class="card-body">

                    @if($empresa->qr_sat_path ?? null)
                        <div style="margin-bottom:12px;">
                            <img src="{{ asset('storage/'.$empresa->qr_sat_path) }}"
                                style="max-height:80px;">
                        </div>
                    @endif

                    <div class="form-group">
                        <label class="form-label">Imagen QR SAT</label>
                        <input type="file" name="qr_sat" class="form-control"
                            accept="image/png,image/jpeg">
                        <span class="form-hint">Se mostrará en el encabezado del PDF (cotizaciones, facturas).</span>
                    </div>

                </div>
            </div>

            {{-- Configuración de Facturación --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🧾 Facturación</div>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-4">Serie y folio inicial para cada tipo de documento. El folio es el siguiente número a asignar.</p>

                    {{-- Facturas --}}
                    <div class="form-section-title" style="margin-bottom: 10px;">📄 Facturas</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Serie <span class="req">*</span></label>
                            <input type="text" name="serie_factura" id="serie_factura" class="form-control"
                                   value="{{ old('serie_factura', $empresa->serie_factura ?? 'A') }}"
                                   maxlength="5" required style="text-transform: uppercase;">
                            <span class="form-hint">Ej: A, B, F</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Folio inicial <span class="req">*</span></label>
                            <input type="number" name="folio_factura" class="form-control"
                                   value="{{ old('folio_factura', $empresa->folio_factura ?? 1) }}" min="1" required>
                        </div>
                    </div>

                    {{-- Notas de Crédito --}}
                    <div class="form-section-title" style="margin-bottom: 10px;">📝 Notas de Crédito</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Serie <span class="req">*</span></label>
                            <input type="text" name="serie_nota_credito" class="form-control"
                                   value="{{ old('serie_nota_credito', $empresa->serie_nota_credito ?? 'NC') }}"
                                   maxlength="5" required style="text-transform: uppercase;">
                            <span class="form-hint">Ej: NC, NCR</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Folio inicial <span class="req">*</span></label>
                            <input type="number" name="folio_nota_credito" class="form-control"
                                   value="{{ old('folio_nota_credito', $empresa->folio_nota_credito ?? 1) }}" min="1" required>
                        </div>
                    </div>

                    {{-- Notas de Débito --}}
                    <div class="form-section-title" style="margin-bottom: 10px;">📝 Notas de Débito</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Serie <span class="req">*</span></label>
                            <input type="text" name="serie_nota_debito" class="form-control"
                                   value="{{ old('serie_nota_debito', $empresa->serie_nota_debito ?? 'ND') }}"
                                   maxlength="5" required style="text-transform: uppercase;">
                            <span class="form-hint">Ej: ND, NDB</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Folio inicial <span class="req">*</span></label>
                            <input type="number" name="folio_nota_debito" class="form-control"
                                   value="{{ old('folio_nota_debito', $empresa->folio_nota_debito ?? 1) }}" min="1" required>
                        </div>
                    </div>

                    {{-- Complementos de Pago --}}
                    <div class="form-section-title" style="margin-bottom: 10px;">💳 Complementos de Pago</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Serie <span class="req">*</span></label>
                            <input type="text" name="serie_complemento" class="form-control"
                                   value="{{ old('serie_complemento', $empresa->serie_complemento ?? 'CP') }}"
                                   maxlength="5" required style="text-transform: uppercase;">
                            <span class="form-hint">Ej: CP, P</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Folio inicial <span class="req">*</span></label>
                            <input type="number" name="folio_complemento" class="form-control"
                                   value="{{ old('folio_complemento', $empresa->folio_complemento ?? 1) }}" min="1" required>
                        </div>
                    </div>

                    {{-- Cotizaciones --}}
                    <div class="form-section-title" style="margin-bottom: 10px;">📝 Cotizaciones</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                        <div class="form-group">
                            <label class="form-label">Serie / Prefijo <span class="req">*</span></label>
                            <input type="text" name="serie_cotizacion" class="form-control"
                                   value="{{ old('serie_cotizacion', $empresa->serie_cotizacion ?? 'COT') }}"
                                   maxlength="10" required style="text-transform: uppercase;">
                            <span class="form-hint">Ej: COT, COT-2026</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Folio inicial <span class="req">*</span></label>
                            <input type="number" name="folio_cotizacion" class="form-control"
                                   value="{{ old('folio_cotizacion', $empresa->folio_cotizacion ?? 1) }}" min="1" required>
                        </div>
                    </div>

                    {{-- Remisiones --}}
                    <div class="form-section-title" style="margin-bottom: 10px;">📦 Remisiones</div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                        <div class="form-group">
                            <label class="form-label">Serie / Prefijo <span class="req">*</span></label>
                            <input type="text" name="serie_remision" class="form-control"
                                   value="{{ old('serie_remision', $empresa->serie_remision ?? 'REM') }}"
                                   maxlength="10" required style="text-transform: uppercase;">
                            <span class="form-hint">Ej: REM, REM-2026</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Folio inicial <span class="req">*</span></label>
                            <input type="number" name="folio_remision" class="form-control"
                                   value="{{ old('folio_remision', $empresa->folio_remision ?? 1) }}" min="1" required>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Configuración PAC --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🔐 Timbrado (PAC)</div>
                </div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                            <input type="checkbox" name="pac_modo_prueba" value="1"
                                   {{ old('pac_modo_prueba', $empresa->pac_modo_prueba) ? 'checked' : '' }}
                                   style="width: 16px; height: 16px;">
                            Modo Prueba (UUID fake)
                        </label>
                        <span class="form-hint">Activa para desarrollo sin consumir timbres</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Proveedor PAC</label>
                        <select name="pac_nombre" class="form-control">
                            <option value="">Seleccionar...</option>
                            @foreach(['factel' => 'Factel', 'finkok' => 'Finkok', 'sw' => 'SW (SmartWeb)'] as $val => $label)
                            <option value="{{ $val }}"
                                {{ old('pac_nombre', $empresa->pac_nombre) == $val ? 'selected' : '' }}>
                                {{ $label }}
                            </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Usuario / API Key</label>
                        <input type="text" name="pac_usuario" class="form-control"
                               value="{{ old('pac_usuario', $empresa->pac_usuario) }}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Password / Token</label>
                        <input type="password" name="pac_password" class="form-control"
                               placeholder="••••••••">
                        <span class="form-hint">Dejar en blanco para mantener el actual</span>
                    </div>

                    @if(!$empresa->pac_modo_prueba && $empresa->tienePACConfigurado())
                    <form method="POST" action="{{ route('empresa.probar-pac') }}" style="margin-top: 12px;">
                        @csrf
                        <button type="submit" class="btn btn-success w-full">🔍 Probar Conexión PAC</button>
                    </form>
                    @endif
                </div>
            </div>

            {{-- Certificados SAT --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📜 Certificados SAT</div>
                </div>
                <div class="card-body">
                    @if($empresa->tieneCertificados())
                    <div class="alert alert-success" style="margin-bottom: 16px;">
                        <span>✅</span>
                        <div>
                            <div class="fw-600">Certificados cargados</div>
                            @if($empresa->certificado_vigencia)
                                <div style="font-size: 12px;">
                                    Vigencia: {{ $empresa->certificado_vigencia->format('d/m/Y') }}
                                </div>
                            @endif
                        </div>
                    </div>
                    <form method="POST" action="{{ route('empresa.verificar-certificados') }}" style="margin-bottom: 16px;">
                        @csrf
                        <button type="submit" class="btn btn-info w-full">🔍 Verificar Certificados</button>
                    </form>
                    @endif

                    <div class="form-group">
                        <label class="form-label">Certificado (.cer)</label>
                        <input type="file" name="certificado_cer" class="form-control" accept=".cer">
                        <span class="form-hint">Archivo .cer del SAT</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Llave Privada (.key)</label>
                        <input type="file" name="certificado_key" class="form-control" accept=".key">
                        <span class="form-hint">Archivo .key del SAT</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contraseña del Certificado</label>
                        <input type="password" name="certificado_password" class="form-control"
                               placeholder="••••••••">
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- Botones --}}
    <div class="card">
        <div class="card-body" style="display: flex; gap: 12px; justify-content: flex-end;">
            <a href="{{ route('dashboard') }}" class="btn btn-light">Cancelar</a>
            <button type="submit" class="btn btn-primary">✓ Guardar Configuración</button>
        </div>
    </div>

</form>

@endsection

@push('scripts')
<script>
    document.getElementById('rfc').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    document.getElementById('serie_factura').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
</script>
@endpush