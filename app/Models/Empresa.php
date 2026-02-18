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
        'calle',
        'numero_exterior',
        'numero_interior',
        'colonia',
        'ciudad',
        'estado',
        'codigo_postal',
        'pais',
        'telefono',
        'email',
        'sitio_web',
        'logo_path',
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
        'serie_nota_credito',
        'folio_nota_credito',
        'serie_complemento',
        'folio_complemento',
        'pac_nombre',
        'pac_api_key',
        'pac_endpoint',
        'pac_modo_prueba',
        'activo',
    ];

    protected $casts = [
        'certificado_vigencia' => 'date',
        'pac_modo_prueba' => 'boolean',
        'activo' => 'boolean',
        'folio_factura' => 'integer',
        'folio_nota_credito' => 'integer',
        'folio_complemento' => 'integer',
    ];

    protected $hidden = [
        'certificado_password',
        'pac_api_key',
    ];

    /**
     * Obtener la empresa principal (singleton)
     */
    public static function principal()
    {
        return self::where('activo', true)->first();
    }

    /**
     * Obtener siguiente folio de factura
     */
    public function obtenerSiguienteFolioFactura(): string
    {
        return $this->serie_factura . '-' . str_pad($this->folio_factura, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Incrementar folio de factura
     */
    public function incrementarFolioFactura(): void
    {
        $this->increment('folio_factura');
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
     * Verificar si tiene certificados configurados
     */
    public function tieneCertificados(): bool
    {
        return !empty($this->certificado_cer) && 
               !empty($this->certificado_key) && 
               !empty($this->certificado_password);
    }

    /**
     * Verificar si tiene PAC configurado
     */
    public function tienePACConfigurado(): bool
    {
        return !empty($this->pac_nombre) && 
               !empty($this->pac_api_key) && 
               !empty($this->pac_endpoint);
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