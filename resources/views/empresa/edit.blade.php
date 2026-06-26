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

@if($errors->any())
<div class="alert alert-danger" style="margin-bottom: 16px;">
    <strong>Errores al guardar:</strong>
    <ul style="margin: 8px 0 0 0; padding-left: 20px;">
        @foreach($errors->all() as $err)
            <li>{{ $err }}</li>
        @endforeach
    </ul>
</div>
@endif

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
                            <label class="form-label">Tipo de persona <span class="req">*</span></label>
                            <select name="tipo_persona" id="tipo_persona" class="form-control" required>
                                <option value="moral" {{ old('tipo_persona', $empresa->tipo_persona ?? 'moral') == 'moral' ? 'selected' : '' }}>Persona moral</option>
                                <option value="fisica" {{ old('tipo_persona', $empresa->tipo_persona ?? 'moral') == 'fisica' ? 'selected' : '' }}>Persona física</option>
                            </select>
                            <span class="form-hint">Persona moral: RFC 12 caracteres. Persona física: RFC 13 caracteres.</span>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Régimen Fiscal <span class="req">*</span></label>
                            <select name="regimen_fiscal" id="regimen_fiscal" class="form-control" required>
                                <option value="">Seleccionar...</option>
                                @foreach($regimenes ?? [] as $r)
                                    <option value="{{ $r->clave }}"
                                        {{ old('regimen_fiscal', $empresa->regimen_fiscal) == $r->clave ? 'selected' : '' }}>
                                        {{ $r->etiqueta }}
                                    </option>
                                @endforeach
                            </select>
                            @php $mostrarResico = (old('tipo_persona', $empresa->tipo_persona ?? 'moral') === 'fisica') && (old('regimen_fiscal', $empresa->regimen_fiscal ?? '') == '626'); @endphp
                            <div id="resico-aviso" class="alert alert-info mt-2" style="padding: 8px 12px; font-size: 12px; {{ $mostrarResico ? '' : 'display:none;' }}">
                                <strong>RESICO:</strong> Con persona física y régimen 626 aplica la tabla ISR RESICO. Ver <a href="{{ route('catalogos-sat.index') }}">Catálogos SAT → Tabla ISR RESICO</a>.<br>
                                <strong>Retención ISR:</strong> Cuando factures a clientes <em>persona moral</em>, se aplicará retención del 1.25% sobre el subtotal (SAT 2026, LISR Art. 152). No aplica en complementos de pago.
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Razón Social <span class="req">*</span></label>
                            <input type="text" name="razon_social" class="form-control"
                                   value="{{ old('razon_social', $empresa->razon_social) }}" required>
                            @error('razon_social')
                                <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                            @enderror
                        </div>
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

            {{-- Configuración de Facturación (movido debajo de Contacto) --}}
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
                            <input type="text" name="serie_factura_credito" id="serie_factura_credito" class="form-control"
                                   value="{{ old('serie_factura_credito', $empresa->serie_factura_credito ?? 'FB') }}"
                                   maxlength="5" required style="text-transform: uppercase;">
                            <span class="form-hint">Sugerido: FB (contado y crédito)</span>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Folio inicial <span class="req">*</span></label>
                            <input type="number" name="folio_factura_credito" class="form-control"
                                   value="{{ old('folio_factura_credito', $empresa->folio_factura_credito ?? 1) }}" min="1" required>
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

            {{-- Tipografía PDF de factura --}}
            @php
                $pdfFontCuerpo = old('pdf_factura_font_cuerpo', $empresa->pdf_factura_font_cuerpo ?? \App\Models\Empresa::PDF_FACTURA_FONT_CUERPO_DEFAULT);
                $pdfFontTitulo = old('pdf_factura_font_titulo', $empresa->pdf_factura_font_titulo ?? \App\Models\Empresa::PDF_FACTURA_FONT_TITULO_DEFAULT);
            @endphp
            <div class="card">
                <div class="card-header">
                    <div class="card-title">📄 PDF de factura</div>
                </div>
                <div class="card-body">
                    <p class="text-muted" style="font-size: 12px; margin: 0 0 12px;">
                        Ajusta el tamaño de <strong>RECEPTOR</strong> y <strong>DATOS DEL COMPROBANTE</strong> en el PDF de factura.
                        Los rangos están limitados para evitar que el contenido se desborde de la página.
                    </p>

                    <div style="display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px;">
                        <button type="button" class="btn btn-secondary btn-sm pdf-font-preset" data-cuerpo="6.5" data-titulo="7">Compacto</button>
                        <button type="button" class="btn btn-secondary btn-sm pdf-font-preset" data-cuerpo="7.5" data-titulo="8">Normal</button>
                        <button type="button" class="btn btn-secondary btn-sm pdf-font-preset" data-cuerpo="8.5" data-titulo="9.5">Amplio</button>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Texto del cuerpo (pt)</label>
                            <input type="number" name="pdf_factura_font_cuerpo" id="pdf_factura_font_cuerpo" class="form-control"
                                   value="{{ $pdfFontCuerpo }}"
                                   min="{{ \App\Models\Empresa::PDF_FACTURA_FONT_CUERPO_MIN }}"
                                   max="{{ \App\Models\Empresa::PDF_FACTURA_FONT_CUERPO_MAX }}"
                                   step="0.5" required>
                            <span class="form-hint">Rango seguro: {{ \App\Models\Empresa::PDF_FACTURA_FONT_CUERPO_MIN }} – {{ \App\Models\Empresa::PDF_FACTURA_FONT_CUERPO_MAX }} pt</span>
                            @error('pdf_factura_font_cuerpo')
                                <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="form-group">
                            <label class="form-label">Títulos de sección (pt)</label>
                            <input type="number" name="pdf_factura_font_titulo" id="pdf_factura_font_titulo" class="form-control"
                                   value="{{ $pdfFontTitulo }}"
                                   min="{{ \App\Models\Empresa::PDF_FACTURA_FONT_TITULO_MIN }}"
                                   max="{{ \App\Models\Empresa::PDF_FACTURA_FONT_TITULO_MAX }}"
                                   step="0.5" required>
                            <span class="form-hint">Rango seguro: {{ \App\Models\Empresa::PDF_FACTURA_FONT_TITULO_MIN }} – {{ \App\Models\Empresa::PDF_FACTURA_FONT_TITULO_MAX }} pt (mín. +0.5 sobre el cuerpo)</span>
                            @error('pdf_factura_font_titulo')
                                <span class="form-hint" style="color: var(--color-danger);">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div id="pdf-font-preview" style="border: 1px solid var(--color-gray-200); border-radius: var(--radius-sm); padding: 10px 12px; background: var(--color-gray-50);">
                        <div id="pdf-font-preview-titulo" style="font-weight: bold; border-bottom: 2px solid #0B3C5D; margin-bottom: 4px; padding-bottom: 2px;">RECEPTOR</div>
                        <div id="pdf-font-preview-cuerpo" style="line-height: 1.25;">
                            <strong>RFC:</strong> XAXX010101000<br>
                            <strong>Nombre:</strong> Cliente de ejemplo S.A. de C.V.
                        </div>
                    </div>

                    <div class="alert alert-info" style="margin-top: 12px; margin-bottom: 0; padding: 10px 12px; font-size: 12px;">
                        Los PDF ya generados no cambian solos. El nuevo tamaño aplica al timbrar o regenerar facturas.
                    </div>
                </div>
            </div>
            <script>
            (function () {
                var cuerpoInput = document.getElementById('pdf_factura_font_cuerpo');
                var tituloInput = document.getElementById('pdf_factura_font_titulo');
                var previewCuerpo = document.getElementById('pdf-font-preview-cuerpo');
                var previewTitulo = document.getElementById('pdf-font-preview-titulo');
                var cuerpoMin = {{ \App\Models\Empresa::PDF_FACTURA_FONT_CUERPO_MIN }};
                var cuerpoMax = {{ \App\Models\Empresa::PDF_FACTURA_FONT_CUERPO_MAX }};
                var tituloMin = {{ \App\Models\Empresa::PDF_FACTURA_FONT_TITULO_MIN }};
                var tituloMax = {{ \App\Models\Empresa::PDF_FACTURA_FONT_TITULO_MAX }};

                function clamp(value, min, max) {
                    return Math.min(max, Math.max(min, value));
                }

                function updatePreview() {
                    var cuerpo = clamp(parseFloat(cuerpoInput.value) || 7.5, cuerpoMin, cuerpoMax);
                    var titulo = clamp(parseFloat(tituloInput.value) || 8, tituloMin, tituloMax);
                    if (titulo < cuerpo + 0.5) {
                        titulo = cuerpo + 0.5;
                        tituloInput.value = titulo.toFixed(1);
                    }
                    previewCuerpo.style.fontSize = cuerpo + 'pt';
                    previewTitulo.style.fontSize = titulo + 'pt';
                }

                cuerpoInput.addEventListener('input', function () {
                    var cuerpo = clamp(parseFloat(cuerpoInput.value) || cuerpoMin, cuerpoMin, cuerpoMax);
                    var titulo = parseFloat(tituloInput.value) || tituloMin;
                    if (titulo < cuerpo + 0.5) {
                        tituloInput.value = (cuerpo + 0.5).toFixed(1);
                    }
                    updatePreview();
                });
                tituloInput.addEventListener('input', updatePreview);

                document.querySelectorAll('.pdf-font-preset').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        cuerpoInput.value = btn.getAttribute('data-cuerpo');
                        tituloInput.value = btn.getAttribute('data-titulo');
                        updatePreview();
                    });
                });

                updatePreview();
            })();
            </script>

            {{-- Configuración PAC / Facturama --}}
            <div class="card">
                <div class="card-header">
                    <div class="card-title">🔐 Timbrado (PAC / Facturama)</div>
                </div>
                <div class="card-body">
                    @php
                        $pacProvider = old('pac_provider', $empresa->pac_provider ?? 'facturama_sandbox');
                    @endphp
                    <div class="form-group">
                        <label class="form-label">Ambiente de timbrado</label>
                        <select name="pac_provider" id="pac_provider" class="form-control" required>
                            <option value="facturama_sandbox" {{ $pacProvider === 'facturama_sandbox' ? 'selected' : '' }}>Pruebas (sandbox)</option>
                            <option value="facturama_production" {{ $pacProvider === 'facturama_production' ? 'selected' : '' }}>Producción</option>
                        </select>
                        <span class="form-hint">Sandbox: ambiente de pruebas. Producción: timbrado real ante el SAT.</span>
                    </div>
                    <div id="facturama_url_box" class="form-group" style="{{ in_array($pacProvider, ['facturama_sandbox', 'facturama_production']) ? '' : 'display:none;' }}">
                        <label class="form-label">URL de petición</label>
                        <input type="text" class="form-control" readonly
                               value="{{ $pacProvider === 'facturama_sandbox' ? 'https://apisandbox.facturama.mx/' : ($pacProvider === 'facturama_production' ? 'https://api.facturama.mx/' : '') }}"
                               id="facturama_url_display">
                        <span class="form-hint">Sandbox: <code>https://apisandbox.facturama.mx/</code> · Producción: <code>https://api.facturama.mx/</code></span>
                    </div>
                    {{-- Credenciales por entorno: sandbox y producción usan cuentas distintas en Facturama --}}
                    <div id="facturama_creds_box" style="{{ in_array($pacProvider, ['facturama_sandbox', 'facturama_production']) ? '' : 'display:none;' }}">
                        <div class="form-section-title" style="margin: 12px 0 8px 0; color: var(--color-primary);">🧪 Sandbox</div>
                        <div class="form-group">
                            <label class="form-label">Usuario (sandbox)</label>
                            <input type="text" name="pac_facturama_user_sandbox" class="form-control"
                                   value="{{ old('pac_facturama_user_sandbox', $empresa->pac_facturama_user_sandbox ?? $empresa->pac_facturama_user) }}"
                                   placeholder="Usuario de app.facturama.mx">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contraseña (sandbox)</label>
                            <input type="password" name="pac_facturama_password_sandbox" class="form-control"
                                   placeholder="••••••••">
                            <span class="form-hint">Dejar en blanco para no cambiar la actual</span>
                        </div>
                        <div class="form-section-title" style="margin: 16px 0 8px 0; color: var(--color-primary);">🚀 Producción</div>
                        <div class="form-group">
                            <label class="form-label">Usuario (producción)</label>
                            <input type="text" name="pac_facturama_user_production" class="form-control"
                                   value="{{ old('pac_facturama_user_production', $empresa->pac_facturama_user_production) }}"
                                   placeholder="Usuario de api.facturama.mx">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contraseña (producción)</label>
                            <input type="password" name="pac_facturama_password_production" class="form-control"
                                   placeholder="••••••••">
                            <span class="form-hint">Obligatoria la primera vez. Dejar en blanco solo si ya guardaste una antes y no quieres cambiarla. Debes hacer clic en "Guardar Configuración" para que se guarden las credenciales.</span>
                        </div>
                        <div class="alert alert-info" style="margin-top: 12px; padding: 10px 12px; font-size: 12px;">
                            <strong>Importante:</strong> Sandbox y producción son cuentas distintas en Facturama. Si usas producción, debes ingresar el usuario y contraseña de tu cuenta de producción (api.facturama.mx). Haz clic en <strong>Guardar Configuración</strong> antes de facturar.
                        </div>
                    </div>
                    @php
                        [$fUser, $fPass] = $empresa->getFacturamaCredentials();
                        $tieneCreds = !empty($fUser) && !empty($fPass);
                    @endphp
                    @if(in_array($pacProvider, ['facturama_sandbox', 'facturama_production']) && $tieneCreds)
                    <div style="margin-top: 12px;">
                        <button type="submit" form="probar-facturama-form" class="btn btn-success w-full">🔍 Probar conexión Facturama</button>
                    </div>
                    @endif
                </div>
            </div>
            <script>
            document.getElementById('pac_provider').addEventListener('change', function() {
                var v = this.value;
                var isFacturama = (v === 'facturama_sandbox' || v === 'facturama_production');
                document.getElementById('facturama_url_box').style.display = isFacturama ? '' : 'none';
                document.getElementById('facturama_creds_box').style.display = isFacturama ? '' : 'none';
                if (document.getElementById('facturama_pass_box')) document.getElementById('facturama_pass_box').style.display = isFacturama ? '' : 'none';
                document.getElementById('facturama_url_display').value = v === 'facturama_sandbox' ? 'https://apisandbox.facturama.mx/' : (v === 'facturama_production' ? 'https://api.facturama.mx/' : '');
            });
            </script>

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
                    <div style="margin-bottom: 16px;">
                        <button type="submit" form="verificar-certificados-form" class="btn btn-info w-full">🔍 Verificar Certificados</button>
                    </div>
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

{{-- Forms fuera del form principal: evitan anidar forms (HTML inválido) que rompe el botón Guardar --}}
<form id="probar-facturama-form" method="POST" action="{{ route('empresa.probar-pac') }}" style="display: none;">
    @csrf
</form>
<form id="verificar-certificados-form" method="POST" action="{{ route('empresa.verificar-certificados') }}" style="display: none;">
    @csrf
</form>

@endsection

@push('scripts')
<script>
    document.getElementById('rfc').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
    function toggleResicoAviso() {
        var tipo = document.getElementById('tipo_persona')?.value;
        var regimen = document.getElementById('regimen_fiscal')?.value;
        var aviso = document.getElementById('resico-aviso');
        if (aviso) aviso.style.display = (tipo === 'fisica' && regimen === '626') ? '' : 'none';
    }
    document.getElementById('tipo_persona')?.addEventListener('change', toggleResicoAviso);
    document.getElementById('regimen_fiscal')?.addEventListener('change', toggleResicoAviso);
    document.getElementById('serie_factura_credito').addEventListener('input', function() {
        this.value = this.value.toUpperCase();
    });
</script>
@endpush