<?php

namespace App\Support;

/**
 * Columnas ligeras para listados CFDI (sin xml_content / sellos / acuses / cadenas).
 * Evita I/O masivo en Hostinger al paginar.
 */
class CfdiListadoColumnas
{
    /**
     * @return list<string>
     */
    public static function factura(): array
    {
        // Index: sin xml/pdf paths ni textos PAC largos (van en show).
        // uuid (corto) sí: etiquetas de cancelación necesitan saber si hubo envío fiscal.
        return [
            'id',
            'serie',
            'folio',
            'uuid',
            'estado',
            'cliente_id',
            'fecha_emision',
            'fecha_timbrado',
            'metodo_pago',
            'total',
            'nombre_receptor',
            'rfc_receptor',
            'fecha_cancelacion',
            'fecha_cancelacion_pac',
            'codigo_estatus_cancelacion',
            'estatus_cancelacion_pac',
            'estatus_sat',
            'fecha_solicitud_cancelacion',
            'fecha_vencimiento_aceptacion',
            'cancelacion_administrativa',
            'deleted_at',
        ];
    }

    /**
     * Columnas mínimas del index de cotizaciones (sin observaciones / dirección / PDF).
     *
     * @return list<string>
     */
    public static function cotizacion(): array
    {
        return [
            'id',
            'folio',
            'estado',
            'cliente_id',
            'usuario_id',
            'cliente_nombre',
            'cliente_rfc',
            'fecha',
            'fecha_vencimiento',
            'total',
            'deleted_at',
        ];
    }

    /**
     * Eager load mínimo cuando solo se muestra folio/estado de la factura.
     *
     * @return list<string>
     */
    public static function facturaRelacion(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'estado',
            'cliente_id',
            'cotizacion_id',
            'deleted_at',
        ];
    }

    /**
     * @return list<string>
     */
    public static function complemento(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'estado',
            'cliente_id',
            'usuario_id',
            'fecha_emision',
            'fecha_timbrado',
            'monto_total',
            'uuid',
            'xml_path',
            'pdf_path',
            'fecha_cancelacion',
            'codigo_estatus_cancelacion',
            'estatus_cancelacion_pac',
            'estatus_sat',
            'is_cancelable',
            'mensaje_cancelacion_pac',
            'fecha_solicitud_cancelacion',
            'fecha_vencimiento_aceptacion',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * @return list<string>
     */
    public static function notaCredito(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'estado',
            'factura_id',
            'cliente_id',
            'usuario_id',
            'fecha_emision',
            'fecha_timbrado',
            'total',
            'uuid',
            'xml_path',
            'pdf_path',
            'fecha_cancelacion',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * @return list<string>
     */
    public static function facturaCompra(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'folio_interno',
            'estado',
            'proveedor_id',
            'usuario_id',
            'orden_compra_id',
            'fecha_emision',
            'total',
            'uuid',
            'nombre_emisor',
            'rfc_emisor',
            'xml_path',
            'pdf_path',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }
}
