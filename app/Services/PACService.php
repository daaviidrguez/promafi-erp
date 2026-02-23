<?php

namespace App\Services;

// UBICACIÓN: app/Services/PACService.php

use App\Models\Factura;
use App\Models\ComplementoPago;
use App\Models\NotaCredito;
use App\Models\Empresa;
use Illuminate\Support\Str;
use Carbon\Carbon;

/**
 * Servicio de timbrado PAC
 * 
 * Esta es una implementación base/mock para desarrollo.
 * En producción, debes implementar la conexión real con tu PAC
 * (Factel, Finkok, SW, etc.)
 */
class PACService implements PACServiceInterface
{
    protected $empresa;
    protected $modoPrueba;

    public function __construct()
    {
        $this->empresa = Empresa::principal();
        $this->modoPrueba = ($this->empresa->pac_provider ?? 'fake') === 'fake' && ($this->empresa->pac_modo_prueba ?? true);
    }

    /**
     * Timbrar una factura
     */
    public function timbrarFactura(Factura $factura): array
    {
        try {
            // Validar que la factura esté lista para timbrar
            if (!$this->validarFactura($factura)) {
                return [
                    'success' => false,
                    'message' => 'La factura no cumple con los requisitos para timbrar',
                ];
            }

            // Modo prueba (UUID fake): no llama a ningún PAC
            if ($this->modoPrueba) {
                $xml = $this->generarXMLFactura($factura);
                $uuid = Str::uuid()->toString();
                $fechaTimbrado = now();
                $noCertificadoSAT = '00001000000123456789';
                $selloCFDI = $this->generarSelloFake();
                $selloSAT = $this->generarSelloFake();
                $cadenaOriginal = $this->generarCadenaOriginal($uuid, $fechaTimbrado);

                return [
                    'success' => true,
                    'uuid' => $uuid,
                    'xml' => $xml,
                    'fecha_timbrado' => $fechaTimbrado,
                    'no_certificado_sat' => $noCertificadoSAT,
                    'sello_cfdi' => $selloCFDI,
                    'sello_sat' => $selloSAT,
                    'cadena_original' => $cadenaOriginal,
                    'message' => '✅ Factura timbrada exitosamente (MODO PRUEBA - UUID fake)',
                ];
            }

            // Facturama (sandbox o producción)
            $provider = $this->empresa->pac_provider ?? 'fake';
            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                if (empty($this->empresa->pac_facturama_user) || empty($this->empresa->pac_facturama_password)) {
                    return [
                        'success' => false,
                        'message' => 'Configura usuario y contraseña de Facturama en Datos de la empresa.',
                    ];
                }
                $facturama = new \App\Services\FacturamaService($this->empresa);
                return $facturama->timbrarFactura($factura);
            }

            return [
                'success' => false,
                'message' => 'PAC no configurado. Elige Modo Prueba (UUID fake) o Facturama en la empresa.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al timbrar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Timbrar complemento de pago
     */
    public function timbrarComplemento(ComplementoPago $complemento): array
    {
        try {
            // Generar XML del complemento
            $xml = $this->generarXMLComplemento($complemento);

            if ($this->modoPrueba) {
                $uuid = Str::uuid()->toString();
                $fechaTimbrado = now();

                return [
                    'success' => true,
                    'uuid' => $uuid,
                    'xml' => $xml,
                    'fecha_timbrado' => $fechaTimbrado,
                    'message' => '✅ Complemento timbrado exitosamente (MODO PRUEBA)',
                ];
            }

            $provider = $this->empresa->pac_provider ?? 'fake';
            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                if (empty($this->empresa->pac_facturama_user) || empty($this->empresa->pac_facturama_password)) {
                    return [
                        'success' => false,
                        'message' => 'Configura usuario y contraseña de Facturama en Datos de la empresa.',
                    ];
                }
                $facturama = new \App\Services\FacturamaService($this->empresa);
                return $facturama->timbrarComplementoPago($complemento);
            }

            return [
                'success' => false,
                'message' => 'Timbrar complementos no disponible. Usa Modo Prueba o Facturama.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al timbrar complemento: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Timbrar nota de crédito (CFDI tipo E)
     */
    public function timbrarNotaCredito(NotaCredito $notaCredito): array
    {
        try {
            if ($this->modoPrueba) {
                $uuid = Str::uuid()->toString();
                return [
                    'success' => true,
                    'uuid' => $uuid,
                    'xml' => '<?xml version="1.0"?><NotaCredito uuid="' . $uuid . '" modo="prueba"/>',
                    'fecha_timbrado' => now(),
                    'no_certificado_sat' => '00001000000123456789',
                    'sello_cfdi' => '',
                    'sello_sat' => '',
                    'cadena_original' => '',
                    'message' => 'Nota de crédito timbrada (MODO PRUEBA).',
                ];
            }

            $provider = $this->empresa->pac_provider ?? 'fake';
            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                if (empty($this->empresa->pac_facturama_user) || empty($this->empresa->pac_facturama_password)) {
                    return [
                        'success' => false,
                        'message' => 'Configura usuario y contraseña de Facturama en la empresa.',
                    ];
                }
                $facturama = new \App\Services\FacturamaService($this->empresa);
                return $facturama->timbrarNotaCredito($notaCredito);
            }

            return [
                'success' => false,
                'message' => 'Timbrado de notas de crédito no configurado. Usa Modo Prueba o Facturama.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al timbrar nota de crédito: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Cancelar factura
     */
    public function cancelarFactura(string $uuid, string $motivo, ?string $uuidSustitucion = null): array
    {
        try {
            if ($this->modoPrueba) {
                return [
                    'success' => true,
                    'message' => '✅ Factura cancelada exitosamente (MODO PRUEBA)',
                    'acuse' => base64_encode('ACUSE DE CANCELACION - MODO PRUEBA'),
                ];
            }

            $provider = $this->empresa->pac_provider ?? 'fake';
            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                if (empty($this->empresa->pac_facturama_user) || empty($this->empresa->pac_facturama_password)) {
                    return [
                        'success' => false,
                        'message' => 'Configura usuario y contraseña de Facturama en Datos de la empresa.',
                    ];
                }
                $factura = Factura::where('uuid', $uuid)->first();
                $pacCfdiId = $factura ? $factura->pac_cfdi_id : null;
                $facturama = new \App\Services\FacturamaService($this->empresa);
                $resultado = $facturama->cancelarFactura($uuid, $motivo, $uuidSustitucion, $pacCfdiId);
                return array_merge($resultado, ['acuse' => $resultado['acuse'] ?? null]);
            }

            return [
                'success' => false,
                'message' => 'Cancelación no disponible. Configura tu PAC.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cancelar: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Verificar estado en el SAT
     */
    public function verificarEstado(string $uuid): array
    {
        if ($this->modoPrueba) {
            return [
                'success' => true,
                'estado' => 'Vigente',
                'cancelable' => true,
            ];
        }

        // TODO: Implementación real con servicio del SAT

        return [
            'success' => false,
            'message' => 'Verificación no disponible',
        ];
    }

    /**
     * Validar que la factura esté completa
     */
    protected function validarFactura(Factura $factura): bool
    {
        // Validar datos básicos
        if (empty($factura->rfc_emisor) || empty($factura->rfc_receptor)) {
            return false;
        }

        // Validar que tenga al menos un concepto
        if ($factura->detalles->count() === 0) {
            return false;
        }

        // Validar montos
        if ($factura->total <= 0) {
            return false;
        }

        return true;
    }

    /**
     * Generar XML CFDI 4.0
     */
    protected function generarXMLFactura(Factura $factura): string
    {
        // Esta es una estructura básica de XML CFDI 4.0
        // En producción, usar una librería especializada
        
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<cfdi:Comprobante' . "\n";
        $xml .= '  xmlns:cfdi="http://www.sat.gob.mx/cfd/4"' . "\n";
        $xml .= '  xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"' . "\n";
        $xml .= '  xsi:schemaLocation="http://www.sat.gob.mx/cfd/4 http://www.sat.gob.mx/sitio_internet/cfd/4/cfdv40.xsd"' . "\n";
        $xml .= '  Version="4.0"' . "\n";
        $xml .= '  Serie="' . $factura->serie . '"' . "\n";
        $xml .= '  Folio="' . $factura->folio . '"' . "\n";
        $xml .= '  Fecha="' . $factura->fecha_emision->format('Y-m-d\TH:i:s') . '"' . "\n";
        $xml .= '  FormaPago="' . $factura->forma_pago . '"' . "\n";
        $xml .= '  SubTotal="' . number_format($factura->subtotal, 2, '.', '') . '"' . "\n";
        $xml .= '  Moneda="' . $factura->moneda . '"' . "\n";
        $xml .= '  Total="' . number_format($factura->total, 2, '.', '') . '"' . "\n";
        $xml .= '  TipoDeComprobante="' . $factura->tipo_comprobante . '"' . "\n";
        $xml .= '  MetodoPago="' . $factura->metodo_pago . '"' . "\n";
        $xml .= '  LugarExpedicion="' . $factura->lugar_expedicion . '">' . "\n";
        
        // TODO: Agregar Emisor, Receptor, Conceptos, Impuestos
        
        $xml .= '</cfdi:Comprobante>';
        
        return $xml;
    }

    /**
     * Generar XML de complemento de pago
     */
    protected function generarXMLComplemento(ComplementoPago $complemento): string
    {
        // TODO: Implementar generación de XML de complemento de pago
        return '<?xml version="1.0" encoding="UTF-8"?><cfdi:Comprobante></cfdi:Comprobante>';
    }

    /**
     * Generar sello fake para modo prueba
     */
    protected function generarSelloFake(): string
    {
        return base64_encode(Str::random(128));
    }

    /**
     * Generar cadena original
     */
    protected function generarCadenaOriginal(string $uuid, Carbon $fecha): string
    {
        return "||4.0|{$uuid}|{$fecha->format('Y-m-d\TH:i:s')}||";
    }
}