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
     * Show de factura: sin xml_content / sellos / cadena (I/O). Paths y acuse sí (UI cancelación).
     *
     * @return list<string>
     */
    public static function facturaShow(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'tipo_comprobante',
            'estado',
            'cliente_id',
            'empresa_id',
            'orden_compra',
            'rfc_emisor',
            'nombre_emisor',
            'regimen_fiscal_emisor',
            'rfc_receptor',
            'nombre_receptor',
            'uso_cfdi',
            'regimen_fiscal_receptor',
            'domicilio_fiscal_receptor',
            'lugar_expedicion',
            'fecha_emision',
            'forma_pago',
            'metodo_pago',
            'moneda',
            'tipo_cambio',
            'subtotal',
            'descuento',
            'total',
            'uuid',
            'pac_cfdi_id',
            'fecha_timbrado',
            'no_certificado_sat',
            'xml_path',
            'pdf_path',
            'motivo_cancelacion',
            'uuid_sustitucion_cancelacion',
            'fecha_cancelacion',
            'fecha_cancelacion_pac',
            'acuse_cancelacion',
            'codigo_estatus_cancelacion',
            'estatus_cancelacion_pac',
            'mensaje_cancelacion_pac',
            'is_cancelable',
            'fecha_solicitud_cancelacion',
            'fecha_vencimiento_aceptacion',
            'estatus_sat',
            'cotizacion_id',
            'observaciones',
            'uuid_referencia',
            'tipo_relacion',
            'usuario_id',
            'cancelacion_administrativa',
            'cancelacion_administrativa_motivo',
            'cancelacion_administrativa_at',
            'cancelacion_administrativa_user_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Complemento embebido en pagos del show de factura (sin XML/sellos).
     *
     * @return list<string>
     */
    public static function complementoRelacionPago(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'estado',
            'cliente_id',
            'deleted_at',
        ];
    }

    /**
     * Index de complementos: sin xml/sellos/paths (van en show/descargas).
     *
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
            'fecha_cancelacion',
            'codigo_estatus_cancelacion',
            'estatus_cancelacion_pac',
            'estatus_sat',
            'is_cancelable',
            'fecha_solicitud_cancelacion',
            'fecha_vencimiento_aceptacion',
            'deleted_at',
        ];
    }

    /**
     * Show HTML de complemento: sin xml_content / sellos / cadena.
     *
     * @return list<string>
     */
    public static function complementoShow(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'estado',
            'cliente_id',
            'empresa_id',
            'rfc_emisor',
            'nombre_emisor',
            'rfc_receptor',
            'nombre_receptor',
            'fecha_emision',
            'lugar_expedicion',
            'monto_total',
            'uuid',
            'pac_cfdi_id',
            'fecha_timbrado',
            'no_certificado_sat',
            'xml_path',
            'pdf_path',
            'fecha_cancelacion',
            'acuse_cancelacion',
            'codigo_estatus_cancelacion',
            'estatus_cancelacion_pac',
            'mensaje_cancelacion_pac',
            'is_cancelable',
            'fecha_solicitud_cancelacion',
            'fecha_vencimiento_aceptacion',
            'estatus_sat',
            'motivo_cancelacion',
            'uuid_sustitucion_cancelacion',
            'uuid_referencia',
            'tipo_relacion',
            'usuario_id',
            'created_at',
            'updated_at',
            'deleted_at',
        ];
    }

    /**
     * Factura embebida en create/edit/API de complemento (sin blobs).
     *
     * @return list<string>
     */
    public static function facturaParaComplemento(): array
    {
        return [
            'id',
            'serie',
            'folio',
            'uuid',
            'estado',
            'cliente_id',
            'fecha_emision',
            'total',
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
