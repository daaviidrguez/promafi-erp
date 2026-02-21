<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/EmpresaController.php

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class EmpresaController extends Controller
{
    /**
     * Mostrar formulario de configuración
     */
    public function edit()
    {
        $empresa = Empresa::principal();
        
        // Si no existe, crear una nueva
        if (!$empresa) {
            $empresa = new Empresa([
                'serie_factura' => 'A',
                'folio_factura' => 1,
                'pac_modo_prueba' => true,
            ]);
        }

        return view('empresa.edit', compact('empresa'));
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
        'regimen_fiscal' => 'required|string|in:' . implode(',', array_keys(Config::get('regimenes_fiscales', []))),

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

        // ===============================
        // IDENTIDAD / BANCO
        // ===============================
        'logo' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        'qr_sat' => 'nullable|image|mimes:png,jpg,jpeg|max:2048',
        'banco' => 'nullable|string|max:255',
        'numero_cuenta' => 'nullable|string|max:50',
        'clabe' => 'nullable|string|max:18',

        // ===============================
        // PAC
        // ===============================
        'pac_modo_prueba' => 'boolean',
        'pac_nombre' => 'nullable|string|max:50',
        'pac_usuario' => 'nullable|string|max:255',
        'pac_password' => 'nullable|string|max:255',

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

    // Etiqueta completa del régimen fiscal (para PDF) desde config único
    $regimenes = Config::get('regimenes_fiscales', []);
    $validated['regimen_fiscal_etiqueta'] = $regimenes[$validated['regimen_fiscal']] ?? $validated['regimen_fiscal'];

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

        if ($empresa->pac_modo_prueba) {
            return back()->with('success', '✅ Modo prueba activo. El timbrado generará UUIDs fake para desarrollo.');
        }

        if (!$empresa->tienePACConfigurado()) {
            return back()->with('error', 'Configura las credenciales del PAC primero');
        }

        // TODO: Aquí iría la prueba real de conexión con el PAC
        return back()->with('success', '✅ Conexión con PAC configurada correctamente (implementar prueba real)');
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