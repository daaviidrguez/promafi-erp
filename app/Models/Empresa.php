<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'rfc',
        'razon_social',
        'nombre_comercial',
        'regimen_fiscal',
        'tipo_persona',
        'regimen_fiscal_etiqueta',
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'ciudad',
        'municipio',
        'estado',
        'codigo_postal',
        'pais',
        'telefono',
        'email',
        'sitio_web',
        'logo_path',
        'qr_sat_path',
        'banco',
        'numero_cuenta',
        'clabe',
        'certificado_cer',
        'certificado_key',
        'certificado_password',
        'no_certificado',
        'certificado_vigencia',
        'serie_factura',
        'folio_factura',
        'serie_factura_credito',
        'folio_factura_credito',
        'serie_nota_credito',
        'folio_nota_credito',
        'serie_nota_debito',
        'folio_nota_debito',
        'serie_complemento',
        'folio_complemento',
        'serie_cotizacion',
        'folio_cotizacion',
        'serie_remision',
        'folio_remision',
        'pac_nombre',
        'pac_api_key',
        'pac_endpoint',
        'pac_provider',
        'pac_facturama_user',
        'pac_facturama_password',
        'pac_facturama_user_sandbox',
        'pac_facturama_password_sandbox',
        'pac_facturama_user_production',
        'pac_facturama_password_production',
        'activo',
    ];

    protected $casts = [
        'certificado_vigencia' => 'date',
        'activo' => 'boolean',
        'folio_factura' => 'integer',
        'folio_factura_credito' => 'integer',
        'folio_nota_credito' => 'integer',
        'folio_nota_debito' => 'integer',
        'folio_complemento' => 'integer',
        'folio_cotizacion' => 'integer',
        'folio_remision' => 'integer',
    ];

    protected $hidden = [
        'certificado_password',
        'pac_api_key',
        'pac_facturama_password',
        'pac_facturama_password_sandbox',
        'pac_facturama_password_production',
    ];

    /**
     * Obtener la empresa principal (singleton)
     */
    public static function principal()
    {
        return self::where('activo', true)->first();
    }

    /**
     * Obtener siguiente folio de factura (contado)
     */
    public function obtenerSiguienteFolioFactura(): string
    {
        $serie = $this->serie_factura ?? 'FA';
        $folio = (int) ($this->folio_factura ?? 1);
        return $serie . '-' . str_pad((string) $folio, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementar folio de factura (contado)
     */
    public function incrementarFolioFactura(): void
    {
        $this->increment('folio_factura');
    }

    /**
     * Obtener siguiente folio de factura crédito
     */
    public function obtenerSiguienteFolioFacturaCredito(): string
    {
        $serie = $this->serie_factura_credito ?? 'FB';
        $folio = (int) ($this->folio_factura_credito ?? 1);
        return $serie . '-' . str_pad((string) $folio, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementar folio de factura crédito
     */
    public function incrementarFolioFacturaCredito(): void
    {
        $this->increment('folio_factura_credito');
    }

    /**
     * Obtener siguiente folio de complemento
     */
    public function obtenerSiguienteFolioComplemento(): string
    {
        return $this->serie_complemento . '-' . str_pad($this->folio_complemento, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementar folio de complemento
     */
    public function incrementarFolioComplemento(): void
    {
        $this->increment('folio_complemento');
    }

    /**
     * Obtener siguiente folio de cotización (sin incrementar)
     */
    public function obtenerSiguienteFolioCotizacion(): string
    {
        return $this->serie_cotizacion . '-' . str_pad((string) $this->folio_cotizacion, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementar folio de cotización
     */
    public function incrementarFolioCotizacion(): void
    {
        $this->increment('folio_cotizacion');
    }

    /**
     * Obtener siguiente folio de remisión (sin incrementar)
     */
    public function obtenerSiguienteFolioRemision(): string
    {
        return $this->serie_remision . '-' . str_pad((string) $this->folio_remision, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementar folio de remisión
     */
    public function incrementarFolioRemision(): void
    {
        $this->increment('folio_remision');
    }

    /**
     * Verificar si tiene certificados configurados
     */
    public function tieneCertificados(): bool
    {
        return !empty($this->certificado_cer) && 
               !empty($this->certificado_key) && 
               !empty($this->certificado_password);
    }

    /**
     * Verificar si tiene PAC configurado (Facturama u otro)
     */
    public function tienePACConfigurado(): bool
    {
        if (in_array($this->pac_provider ?? 'facturama_sandbox', ['facturama_sandbox', 'facturama_production'], true)) {
            [$user, $pass] = $this->getFacturamaCredentials();
            return !empty($user) && !empty($pass);
        }
        return !empty($this->pac_nombre) && !empty($this->pac_api_key) && !empty($this->pac_endpoint);
    }

    /**
     * Obtener credenciales de Facturama según el proveedor activo (sandbox o producción).
     * Sandbox y producción son cuentas distintas — cada entorno usa solo sus propias credenciales.
     * Usa getRawOriginal para contraseñas (están en $hidden) y evitar cualquier pérdida de valor.
     */
    public function getFacturamaCredentials(): array
    {
        $provider = $this->pac_provider ?? 'facturama_sandbox';
        if ($provider === 'facturama_sandbox') {
            $user = trim((string) ($this->pac_facturama_user_sandbox ?? $this->pac_facturama_user ?? ''));
            $pass = trim((string) ($this->getRawOriginal('pac_facturama_password_sandbox') ?? $this->getRawOriginal('pac_facturama_password') ?? ''));
            return [$user, $pass];
        }
        if ($provider === 'facturama_production') {
            $user = trim((string) ($this->pac_facturama_user_production ?? $this->pac_facturama_user ?? ''));
            $pass = trim((string) ($this->getRawOriginal('pac_facturama_password_production') ?? $this->getRawOriginal('pac_facturama_password') ?? ''));
            return [$user, $pass];
        }
        $user = trim((string) ($this->pac_facturama_user ?? ''));
        $pass = trim((string) ($this->getRawOriginal('pac_facturama_password') ?? ''));
        return [$user, $pass];
    }

    /**
     * URL base de la API según proveedor (Facturama)
     */
    public function getFacturamaBaseUrlAttribute(): ?string
    {
        $provider = $this->pac_provider ?? 'facturama_sandbox';
        return match ($provider) {
            'facturama_sandbox' => 'https://apisandbox.facturama.mx',
            'facturama_production' => 'https://api.facturama.mx',
            default => null,
        };
    }

    /**
     * Verificar si certificado está vigente
     */
    public function certificadoVigente(): bool
    {
        if (!$this->certificado_vigencia) {
            return false;
        }
        
        return $this->certificado_vigencia->isFuture();
    }

    /**
     * Domicilio completo
     */
    public function getDomicilioCompletoAttribute(): string
    {
        $domicilio = $this->calle . ' ' . $this->numero_exterior;
        
        if ($this->numero_interior) {
            $domicilio .= ' Int. ' . $this->numero_interior;
        }
        
        $domicilio .= ', ' . $this->colonia;
        $domicilio .= ', ' . $this->ciudad;
        $domicilio .= ', ' . $this->estado;
        $domicilio .= ' CP ' . $this->codigo_postal;
        
        return $domicilio;
    }
}