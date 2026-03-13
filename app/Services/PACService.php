<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\ComplementoPago;
use App\Models\NotaCredito;
use App\Models\Empresa;

/**
 * Servicio de timbrado con Facturama.
 * Sandbox: ambiente de pruebas. Producción: timbrado real.
 * Siempre lee empresa fresca desde BD en cada método (no cachea).
 */
class PACService implements PACServiceInterface
{
    public function __construct()
    {
    }

    private function getEmpresa(): ?Empresa
    {
        return Empresa::principal()?->fresh();
    }

    private function getProvider(): string
    {
        return $this->getEmpresa()?->pac_provider ?? 'facturama_sandbox';
    }

    public function timbrarFactura(Factura $factura): array
    {
        try {
            if (!$this->validarFactura($factura)) {
                return [
                    'success' => false,
                    'message' => 'La factura no cumple con los requisitos para timbrar',
                ];
            }

            $empresa = $this->getEmpresa();
            if (!$empresa) {
                return ['success' => false, 'message' => 'No hay empresa configurada.'];
            }

            $provider = $empresa->pac_provider ?? 'facturama_sandbox';

            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                [$user, $pass] = $empresa->getFacturamaCredentials();
                if (empty($user) || empty($pass)) {
                    $entorno = $provider === 'facturama_production' ? 'producción' : 'sandbox';
                    return [
                        'success' => false,
                        'message' => "Configura usuario y contraseña de Facturama para {$entorno} en Configuración de empresa.",
                    ];
                }
                $facturama = new FacturamaService($empresa);
                return $facturama->timbrarFactura($factura);
            }

            return [
                'success' => false,
                'message' => 'Configura Facturama (sandbox o producción) en Configuración de empresa.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al timbrar: ' . $e->getMessage(),
            ];
        }
    }

    public function timbrarComplemento(ComplementoPago $complemento): array
    {
        try {
            $empresa = $this->getEmpresa();
            if (!$empresa) {
                return ['success' => false, 'message' => 'No hay empresa configurada.'];
            }

            $provider = $empresa->pac_provider ?? 'facturama_sandbox';

            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                [$user, $pass] = $empresa->getFacturamaCredentials();
                if (!empty($user) && !empty($pass)) {
                    $facturama = new FacturamaService($empresa);
                    return $facturama->timbrarComplementoPago($complemento);
                }
                $entorno = $provider === 'facturama_production' ? 'producción' : 'sandbox';
                return [
                    'success' => false,
                    'message' => "Configura usuario y contraseña de Facturama para {$entorno} en Configuración de empresa.",
                ];
            }

            return [
                'success' => false,
                'message' => 'Configura Facturama (sandbox o producción) en Configuración de empresa.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al timbrar complemento: ' . $e->getMessage(),
            ];
        }
    }

    public function timbrarNotaCredito(NotaCredito $notaCredito): array
    {
        try {
            $empresa = $this->getEmpresa();
            if (!$empresa) {
                return ['success' => false, 'message' => 'No hay empresa configurada.'];
            }

            $provider = $empresa->pac_provider ?? 'facturama_sandbox';

            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                [$user, $pass] = $empresa->getFacturamaCredentials();
                if (!empty($user) && !empty($pass)) {
                    $facturama = new FacturamaService($empresa);
                    return $facturama->timbrarNotaCredito($notaCredito);
                }
                $entorno = $provider === 'facturama_production' ? 'producción' : 'sandbox';
                return [
                    'success' => false,
                    'message' => "Configura usuario y contraseña de Facturama para {$entorno} en Configuración de empresa.",
                ];
            }

            return [
                'success' => false,
                'message' => 'Configura Facturama (sandbox o producción) en Configuración de empresa.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al timbrar nota de crédito: ' . $e->getMessage(),
            ];
        }
    }

    public function cancelarFactura(string $uuid, string $motivo, ?string $uuidSustitucion = null): array
    {
        try {
            $empresa = $this->getEmpresa();
            if (!$empresa) {
                return ['success' => false, 'message' => 'No hay empresa configurada.'];
            }

            $provider = $empresa->pac_provider ?? 'facturama_sandbox';

            if (in_array($provider, ['facturama_sandbox', 'facturama_production'], true)) {
                [$user, $pass] = $empresa->getFacturamaCredentials();
                if (!empty($user) && !empty($pass)) {
                    $factura   = Factura::where('uuid', $uuid)->first();
                    $pacCfdiId = $factura?->pac_cfdi_id;
                    $facturama = new FacturamaService($empresa);
                    $resultado = $facturama->cancelarFactura($uuid, $motivo, $uuidSustitucion, $pacCfdiId);
                    return array_merge($resultado, ['acuse' => $resultado['acuse'] ?? null]);
                }
                $entorno = $provider === 'facturama_production' ? 'producción' : 'sandbox';
                return [
                    'success' => false,
                    'message' => "Configura usuario y contraseña de Facturama para {$entorno} en Configuración de empresa.",
                ];
            }

            return [
                'success' => false,
                'message' => 'Configura Facturama (sandbox o producción) en Configuración de empresa.',
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Error al cancelar: ' . $e->getMessage(),
            ];
        }
    }

    public function verificarEstado(string $uuid): array
    {
        // TODO: Implementación real con servicio del SAT
        return [
            'success' => false,
            'message' => 'Verificación no disponible',
        ];
    }

    protected function validarFactura(Factura $factura): bool
    {
        if (empty($factura->rfc_emisor) || empty($factura->rfc_receptor)) {
            return false;
        }
        if ($factura->detalles->count() === 0) {
            return false;
        }
        if ($factura->total <= 0) {
            return false;
        }
        return true;
    }
}
