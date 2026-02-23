<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/EmpresaController.php

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\RegimenFiscal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    /**
     * Mostrar formulario de configuración
     */
    public function edit()
    {
        $empresa = Empresa::principal();
        $regimenes = RegimenFiscal::activos()->get();

        // Si no existe, crear una nueva
        if (!$empresa) {
            $empresa = new Empresa([
                'serie_factura' => 'FA',
                'folio_factura' => 1,
                'serie_factura_credito' => 'FB',
                'folio_factura_credito' => 1,
                'pac_modo_prueba' => true,
            ]);
        }

        return view('empresa.edit', compact('empresa', 'regimenes'));
    }

    /**
     * Guardar o actualizar configuración
     */
    public function update(Request $request)
{
    $validated = $request->validate([
        // ===============================
        // DATOS GENERALES
        // ===============================
        'rfc' => [
            'required',
            'string',
            'between:12,13',
            function ($attribute, $value, $fail) {
                $rfc = strtoupper(preg_replace('/\s/', '', $value));
                $len = strlen($rfc);
                $moral = $len === 12 && preg_match('/^[A-ZÑ&]{3}\d{6}[A-Z0-9]{3}$/', $rfc);
                $fisica = $len === 13 && preg_match('/^[A-ZÑ&]{4}\d{6}[A-Z0-9]{3}$/', $rfc);
                if (!$moral && !$fisica) {
                    $fail('El RFC debe ser 12 caracteres (persona moral) o 13 (persona física). Ej: XA1901231ABC o GODE901231ABC.');
                }
            },
        ],
        'razon_social' => 'required|string|max:255',
        'nombre_comercial' => 'nullable|string|max:255',
        'regimen_fiscal' => 'required|string|exists:regimenes_fiscales,clave',

        // ===============================
        // DOMICILIO
        // ===============================
        'calle' => 'nullable|string|max:255',
        'numero_exterior' => 'nullable|string|max:20',
        'numero_interior' => 'nullable|string|max:20',
        'colonia' => 'nullable|string|max:255',
        'municipio' => 'nullable|string|max:255',
        'estado' => 'nullable|string|max:100',
        'codigo_postal' => 'required|string|size:5',
        'pais' => 'nullable|string|max:100',

        // ===============================
        // CONTACTO
        // ===============================
        'email' => 'nullable|email',
        'telefono' => 'nullable|string|max:15',

        // ===============================
        // FACTURACIÓN
        // ===============================
        'serie_factura' => 'required|string|max:5',
        'folio_factura' => 'required|integer|min:1',
        'serie_factura_credito' => 'required|string|max:5',
        'folio_factura_credito' => 'required|integer|min:1',
        'serie_nota_credito' => 'required|string|max:5',
        'folio_nota_credito' => 'required|integer|min:1',
        'serie_nota_debito' => 'required|string|max:5',
        'folio_nota_debito' => 'required|integer|min:1',
        'serie_complemento' => 'required|string|max:5',
        'folio_complemento' => 'required|integer|min:1',
        'serie_cotizacion' => 'required|string|max:10',
        'folio_cotizacion' => 'required|integer|min:1',
        'serie_remision' => 'required|string|max:10',
        'folio_remision' => 'required|integer|min:1',

        // ===============================
        // IDENTIDAD / BANCO
        // ===============================
        'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        'qr_sat' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        'banco' => 'nullable|string|max:255',
        'numero_cuenta' => 'nullable|string|max:50',
        'clabe' => 'nullable|string|max:18',

        // ===============================
        // PAC / Facturama
        // ===============================
        'pac_modo_prueba' => 'boolean',
        'pac_nombre' => 'nullable|string|max:50',
        'pac_usuario' => 'nullable|string|max:255',
        'pac_password' => 'nullable|string|max:255',
        'pac_provider' => 'nullable|string|in:fake,facturama_sandbox,facturama_production',
        'pac_facturama_user' => 'nullable|string|max:255',
        'pac_facturama_password' => 'nullable|string|max:255',

        // ===============================
        // CERTIFICADOS
        // ===============================
        'certificado_cer' => 'nullable|file|mimes:cer',
        'certificado_key' => 'nullable|file|mimes:key',
        'certificado_password' => 'nullable|string|max:255',
    ]);

    // ===============================
    // OBTENER EMPRESA PRINCIPAL
    // ===============================
    $empresa = Empresa::principal();

    if (!$empresa) {
        $empresa = new Empresa();
        $empresa->activo = true; // 🔥 fundamental para principal()
    }

    // ===============================
    // CONVERTIR CHECKBOX BOOLEAN
    // ===============================
    $validated['pac_modo_prueba'] = $request->has('pac_modo_prueba');
    $validated['pac_provider'] = $validated['pac_provider'] ?? 'fake';
    // No sobrescribir contraseña Facturama si viene vacía
    if (empty($validated['pac_facturama_password'])) {
        unset($validated['pac_facturama_password']);
    }

    // Etiqueta del régimen fiscal (para PDF)
    $reg = RegimenFiscal::where('clave', $validated['regimen_fiscal'])->first();
    $validated['regimen_fiscal_etiqueta'] = $reg ? $reg->etiqueta : $validated['regimen_fiscal'];

    // ===============================
    // CERTIFICADO .CER (privado)
    // ===============================
    if ($request->hasFile('certificado_cer')) {

        if ($empresa->certificado_cer) {
            Storage::disk('local')->delete($empresa->certificado_cer);
        }

        $validated['certificado_cer'] =
            $request->file('certificado_cer')
                ->store('certificados', 'local');
    }

    // ===============================
    // CERTIFICADO .KEY (privado)
    // ===============================
    if ($request->hasFile('certificado_key')) {

        if ($empresa->certificado_key) {
            Storage::disk('local')->delete($empresa->certificado_key);
        }

        $validated['certificado_key'] =
            $request->file('certificado_key')
                ->store('certificados', 'local');
    }

    // ===============================
    // LOGO (público)
    // ===============================
    if ($request->hasFile('logo')) {

        if ($empresa->logo_path) {
            Storage::disk('public')->delete($empresa->logo_path);
        }

        $validated['logo_path'] =
            $request->file('logo')
                ->store('empresa', 'public');
    }

    // ===============================
    // QR IDENTIFICACIÓN SAT (público)
    // ===============================
    if ($request->hasFile('qr_sat')) {
        if ($empresa->qr_sat_path) {
            Storage::disk('public')->delete($empresa->qr_sat_path);
        }
        $validated['qr_sat_path'] =
            $request->file('qr_sat')
                ->store('empresa', 'public');
    }

    // ===============================
    // GUARDAR
    // ===============================
    $empresa->fill($validated);
    $empresa->save();

    return redirect()
        ->route('empresa.edit')
        ->with('success', 'Configuración de la empresa actualizada exitosamente');
}

    /**
     * Probar conexión con PAC
     */
    public function probarPAC()
    {
        $empresa = Empresa::principal();

        if (!$empresa) {
            return back()->with('error', 'Configura los datos de la empresa primero');
        }

        $provider = $empresa->pac_provider ?? 'fake';
        if ($provider === 'fake') {
            return back()->with('success', '✅ Modo prueba activo. El timbrado generará UUIDs fake para desarrollo.');
        }

        if (in_array($provider, ['facturama_sandbox', 'facturama_production'])) {
            if (empty($empresa->pac_facturama_user) || empty($empresa->pac_facturama_password)) {
                return back()->with('error', 'Configura Usuario y Contraseña de Facturama (guarda los cambios antes de probar).');
            }
            try {
                $baseUrl = rtrim($empresa->facturama_base_url, '/');
                // API Web: GET /TaxEntity obtiene el perfil fiscal (doc: https://apisandbox.facturama.mx/docs/api/GET-TaxEntity)
                $response = \Illuminate\Support\Facades\Http::withBasicAuth($empresa->pac_facturama_user, $empresa->pac_facturama_password)
                    ->acceptJson()
                    ->timeout(15)
                    ->get($baseUrl . '/TaxEntity');
                if ($response->successful()) {
                    return back()->with('success', '✅ Conexión con Facturama correcta (' . ($provider === 'facturama_sandbox' ? 'sandbox' : 'producción') . '). Puedes timbrar facturas.');
                }
                $body = $response->json();
                $msg = $body['Message'] ?? $body['message'] ?? $response->body();
                if (is_array($msg)) {
                    $msg = json_encode($msg);
                }
                if (strlen($msg) > 300) {
                    $msg = substr($msg, 0, 300) . '…';
                }
                $status = $response->status();
                if ($status === 401) {
                    return back()->with('error', 'Facturama: Credenciales incorrectas (usuario o contraseña). Revisa que sea la cuenta de ' . ($provider === 'facturama_sandbox' ? 'sandbox' : 'producción') . '.');
                }
                return back()->with('error', 'Facturama respondió con error ' . $status . ': ' . ($msg ?: 'Sin mensaje'));
            } catch (\Throwable $e) {
                return back()->with('error', 'Error al conectar con Facturama: ' . $e->getMessage());
            }
        }

        if (!$empresa->tienePACConfigurado()) {
            return back()->with('error', 'Configura las credenciales del PAC primero');
        }

        return back()->with('success', '✅ Conexión con PAC configurada correctamente');
    }

    /**
     * Verificar certificados
     */
    public function verificarCertificados()
    {
        $empresa = Empresa::principal();

        if (!$empresa || !$empresa->tieneCertificados()) {
            return back()->with('error', 'No hay certificados cargados');
        }

        // Verificar que los archivos existan
        $cerExists = Storage::disk('local')->exists($empresa->certificado_cer_path);
        $keyExists = Storage::disk('local')->exists($empresa->certificado_key_path);

        if (!$cerExists || !$keyExists) {
            return back()->with('error', 'Los archivos de certificados no se encontraron');
        }

        // Verificar vigencia
        if ($empresa->certificadoVigente()) {
            return back()->with('success', '✅ Certificados cargados y vigentes hasta: ' . $empresa->certificado_vigencia->format('d/m/Y'));
        } else {
            return back()->with('error', '⚠️ Los certificados han expirado. Fecha de vencimiento: ' . $empresa->certificado_vigencia->format('d/m/Y'));
        }
    }
}