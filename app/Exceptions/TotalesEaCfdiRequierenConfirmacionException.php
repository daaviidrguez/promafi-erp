<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Totales CFDI ≠ EA: requiere confirmación explícita del usuario para continuar.
 */
class TotalesEaCfdiRequierenConfirmacionException extends RuntimeException
{
    public function __construct(
        public readonly float $totalCfdi,
        public readonly float $totalEa,
        public readonly float $subtotalCfdi,
        public readonly float $subtotalEa,
        public readonly bool $eaCostosProvisionales,
    ) {
        $msg = 'El total del CFDI ($'.number_format($totalCfdi, 2)
            .') no coincide con el de las partidas de la entrada que cubre este comprobante ($'.number_format($totalEa, 2).'). '
            .'Confirme para vincular aplicando los precios fiscales del CFDI a los costos de producto.';

        parent::__construct($msg);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_cfdi' => $this->totalCfdi,
            'total_ea' => $this->totalEa,
            'subtotal_cfdi' => $this->subtotalCfdi,
            'subtotal_ea' => $this->subtotalEa,
            'ea_costos_provisionales' => $this->eaCostosProvisionales,
            'message' => $this->getMessage(),
        ];
    }
}
