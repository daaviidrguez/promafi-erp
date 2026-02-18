<?php

namespace App\Services;

// UBICACIÓN: app/Services/PACServiceInterface.php

use App\Models\Factura;
use App\Models\ComplementoPago;

interface PACServiceInterface
{
    /**
     * Timbrar una factura
     * 
     * @param Factura $factura
     * @return array ['success' => bool, 'uuid' => string, 'xml' => string, 'message' => string]
     */
    public function timbrarFactura(Factura $factura): array;

    /**
     * Timbrar un complemento de pago
     * 
     * @param ComplementoPago $complemento
     * @return array ['success' => bool, 'uuid' => string, 'xml' => string, 'message' => string]
     */
    public function timbrarComplemento(ComplementoPago $complemento): array;

    /**
     * Cancelar una factura
     * 
     * @param string $uuid
     * @param string $motivo Código del motivo (01, 02, 03, 04)
     * @param string|null $uuidSustitucion UUID de la factura que sustituye (si aplica)
     * @return array ['success' => bool, 'message' => string, 'acuse' => string]
     */
    public function cancelarFactura(string $uuid, string $motivo, ?string $uuidSustitucion = null): array;

    /**
     * Verificar el estado de una factura en el SAT
     * 
     * @param string $uuid
     * @return array ['success' => bool, 'estado' => string, 'cancelable' => bool]
     */
    public function verificarEstado(string $uuid): array;
}