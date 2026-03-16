<?php

namespace App\Services;

// UBICACIÓN: app/Services/PACServiceInterface.php

use App\Models\Factura;
use App\Models\ComplementoPago;
use App\Models\NotaCredito;

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
     * Timbrar una nota de crédito (CFDI tipo E)
     *
     * @param NotaCredito $notaCredito
     * @return array ['success' => bool, 'uuid' => string, 'xml' => string, 'message' => string, ...]
     */
    public function timbrarNotaCredito(NotaCredito $notaCredito): array;

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
     * Cancelar un complemento de pago
     *
     * @param ComplementoPago $complemento
     * @param string $motivo 01, 02, 03, 04
     * @param string|null $uuidSustitucion UUID del CFDI que sustituye (motivo 01)
     * @return array ['success' => bool, 'message' => string, 'acuse' => string|null, 'codigo_estatus' => string]
     */
    public function cancelarComplementoPago(ComplementoPago $complemento, string $motivo, ?string $uuidSustitucion = null): array;

    /**
     * Obtener acuse de cancelación de un complemento (para complementos ya cancelados sin acuse guardado).
     *
     * @param ComplementoPago $complemento
     * @return string|null Acuse XML en base64 o null
     */
    public function obtenerAcuseCancelacionPorComplemento(ComplementoPago $complemento): ?string;

    /**
     * Verificar el estado de una factura en el SAT
     * 
     * @param string $uuid
     * @return array ['success' => bool, 'estado' => string, 'cancelable' => bool]
     */
    public function verificarEstado(string $uuid): array;
}