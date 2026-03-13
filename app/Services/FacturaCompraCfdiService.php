<?php

namespace App\Services;

use App\Models\FacturaCompra;
use App\Models\FacturaCompraDetalle;
use App\Models\FacturaCompraImpuesto;
use App\Models\Proveedor;
use App\Models\Empresa;
use App\Models\CuentaPorPagar;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

/**
 * Servicio para parsear CFDI XML (facturas de compra) y crear el registro.
 * Soporta CFDI 4.0.
 */
class FacturaCompraCfdiService
{
    protected string $cfdiNs = 'http://www.sat.gob.mx/cfd/4';
    protected string $tfdNs = 'http://www.sat.gob.mx/TimbreFiscalDigital';

    /**
     * Parsea XML y crea FacturaCompra con detalles e impuestos.
     *
     * @return array ['success' => bool, 'factura_compra' => FacturaCompra|null, 'message' => string]
     */
    public function parsearYGuardar(string $xmlContent): array
    {
        $xml = $this->normalizarXml($xmlContent);
        $datos = $this->extraerDatos($xml);
        if (isset($datos['error'])) {
            return ['success' => false, 'factura_compra' => null, 'message' => $datos['error']];
        }

        // Validar que sea Egreso (compra) - emisor es proveedor, receptor es empresa
        $tipoComprobante = $datos['tipo_comprobante'] ?? 'E';
        if (!in_array($tipoComprobante, ['E', 'I', 'P', 'N'])) {
            return ['success' => false, 'factura_compra' => null, 'message' => 'Tipo de comprobante no soportado: ' . $tipoComprobante];
        }

        // Verificar UUID duplicado
        if (!empty($datos['uuid'])) {
            $existe = FacturaCompra::where('uuid', $datos['uuid'])->first();
            if ($existe) {
                return ['success' => false, 'factura_compra' => null, 'message' => 'Ya existe una factura de compra con UUID ' . $datos['uuid']];
            }
        }

        $empresa = Empresa::principal();
        if (!$empresa) {
            return ['success' => false, 'factura_compra' => null, 'message' => 'Configure la empresa principal'];
        }

        // El receptor debe ser la empresa (quien compra)
        $rfcReceptor = $datos['rfc_receptor'] ?? '';
        if (strtoupper($rfcReceptor) !== strtoupper($empresa->rfc ?? '')) {
            return ['success' => false, 'factura_compra' => null, 'message' => 'El RFC receptor del CFDI (' . $rfcReceptor . ') no coincide con el RFC de la empresa (' . ($empresa->rfc ?? '') . '). Esta factura no corresponde a esta empresa.'];
        }

        DB::beginTransaction();
        try {
            $proveedor = Proveedor::where('rfc', $datos['rfc_emisor'])->first();

            $fc = FacturaCompra::create([
                'serie' => $datos['serie'] ?? null,
                'folio' => $datos['folio'] ?? '0',
                'tipo_comprobante' => $tipoComprobante,
                'estado' => 'registrada',
                'proveedor_id' => $proveedor?->id,
                'empresa_id' => $empresa->id,
                'rfc_emisor' => $datos['rfc_emisor'],
                'nombre_emisor' => $datos['nombre_emisor'],
                'regimen_fiscal_emisor' => $datos['regimen_fiscal_emisor'] ?? null,
                'rfc_receptor' => $datos['rfc_receptor'],
                'nombre_receptor' => $datos['nombre_receptor'],
                'regimen_fiscal_receptor' => $datos['regimen_fiscal_receptor'] ?? null,
                'lugar_expedicion' => $datos['lugar_expedicion'] ?? null,
                'fecha_emision' => $datos['fecha_emision'],
                'forma_pago' => $datos['forma_pago'] ?? null,
                'metodo_pago' => $datos['metodo_pago'] ?? null,
                'moneda' => $datos['moneda'] ?? 'MXN',
                'tipo_cambio' => $datos['tipo_cambio'] ?? 1,
                'subtotal' => $datos['subtotal'],
                'descuento' => $datos['descuento'] ?? 0,
                'total' => $datos['total'],
                'uuid' => $datos['uuid'] ?? null,
                'fecha_timbrado' => $datos['fecha_timbrado'] ?? null,
                'no_certificado_sat' => $datos['no_certificado_sat'] ?? null,
                'xml_content' => $xmlContent,
                'usuario_id' => auth()->id(),
            ]);

            $orden = 0;
            foreach ($datos['conceptos'] ?? [] as $concepto) {
                $detalle = FacturaCompraDetalle::create([
                    'factura_compra_id' => $fc->id,
                    'producto_id' => null,
                    'clave_prod_serv' => $concepto['clave_prod_serv'] ?? '01010101',
                    'clave_unidad' => $concepto['clave_unidad'] ?? 'H87',
                    'unidad' => $concepto['unidad'] ?? 'Pieza',
                    'no_identificacion' => $concepto['no_identificacion'] ?? null,
                    'descripcion' => $concepto['descripcion'] ?? '',
                    'cantidad' => $concepto['cantidad'],
                    'valor_unitario' => $concepto['valor_unitario'],
                    'importe' => $concepto['importe'],
                    'descuento' => $concepto['descuento'] ?? 0,
                    'base_impuesto' => $concepto['base_impuesto'] ?? $concepto['importe'],
                    'objeto_impuesto' => $concepto['objeto_impuesto'] ?? '02',
                    'orden' => $orden++,
                ]);
                foreach ($concepto['impuestos'] ?? [] as $imp) {
                    FacturaCompraImpuesto::create([
                        'factura_compra_detalle_id' => $detalle->id,
                        'tipo' => $imp['tipo'],
                        'impuesto' => $imp['impuesto'],
                        'tipo_factor' => $imp['tipo_factor'] ?? 'Tasa',
                        'tasa_o_cuota' => $imp['tasa_o_cuota'] ?? null,
                        'base' => $imp['base'],
                        'importe' => $imp['importe'] ?? null,
                    ]);
                }
            }

            // Crear cuenta por pagar si es PPD y el proveedor tiene días de crédito
            $diasCredito = $proveedor ? (int) ($proveedor->dias_credito ?? 0) : 0;
            if (($datos['metodo_pago'] ?? '') === 'PPD' && $diasCredito > 0 && $proveedor) {
                $fechaEmision = Carbon::parse($fc->fecha_emision);
                $fechaVencimiento = $fechaEmision->copy()->addDays($diasCredito);
                CuentaPorPagar::create([
                    'factura_compra_id' => $fc->id,
                    'orden_compra_id' => null,
                    'proveedor_id' => $proveedor->id,
                    'monto_total' => $fc->total,
                    'monto_pagado' => 0,
                    'monto_pendiente' => $fc->total,
                    'fecha_emision' => $fechaEmision,
                    'fecha_vencimiento' => $fechaVencimiento,
                    'estado' => 'pendiente',
                ]);
            }

            DB::commit();
            return ['success' => true, 'factura_compra' => $fc->fresh(['detalles.impuestos', 'proveedor', 'cuentaPorPagar']), 'message' => 'Factura de compra registrada correctamente'];
        } catch (\Throwable $e) {
            DB::rollBack();
            return ['success' => false, 'factura_compra' => null, 'message' => 'Error al guardar: ' . $e->getMessage()];
        }
    }

    protected function normalizarXml(string $xml): string
    {
        $xml = trim($xml);
        if (stripos($xml, '<?xml') !== 0) {
            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . $xml;
        }
        return $xml;
    }

    /**
     * Extrae datos del CFDI XML.
     */
    protected function extraerDatos(string $xml): array
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xml)) {
            return ['error' => 'XML inválido'];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cfdi', $this->cfdiNs);
        $xpath->registerNamespace('tfd', $this->tfdNs);

        // Comprobante - CFDI 4.0 o 3.3 (diferentes namespaces)
        $comp = $dom->getElementsByTagNameNS($this->cfdiNs, 'Comprobante')->item(0)
            ?? $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Comprobante')->item(0)
            ?? $dom->getElementsByTagName('Comprobante')->item(0);
        if (!$comp) {
            $comp = $dom->documentElement;
            if (!$comp || (stripos($comp->nodeName, 'Comprobante') === false && $comp->localName !== 'Comprobante')) {
                return ['error' => 'No se encontró el nodo Comprobante'];
            }
        }

        $getAttr = fn ($el, $name) => $el->getAttribute($name) ?: null;
        $comprobanteAttr = fn ($n) => $getAttr($comp, $n);

        $emisor = $dom->getElementsByTagNameNS($this->cfdiNs, 'Emisor')->item(0)
            ?? $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Emisor')->item(0)
            ?? $dom->getElementsByTagName('Emisor')->item(0);
        $receptor = $dom->getElementsByTagNameNS($this->cfdiNs, 'Receptor')->item(0)
            ?? $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Receptor')->item(0)
            ?? $dom->getElementsByTagName('Receptor')->item(0);

        $e = fn ($el, $n) => $el ? $getAttr($el, $n) : null;

        $fechaEmision = $comprobanteAttr('Fecha');
        if (!$fechaEmision) {
            return ['error' => 'Fecha de emisión no encontrada'];
        }
        try {
            Carbon::parse($fechaEmision);
        } catch (\Throwable $t) {
            return ['error' => 'Fecha de emisión inválida: ' . $fechaEmision];
        }

        $conceptos = [];
        $conceptosNode = $dom->getElementsByTagNameNS($this->cfdiNs, 'Conceptos')->item(0)
            ?? $dom->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Conceptos')->item(0)
            ?? $dom->getElementsByTagName('Conceptos')->item(0);
        if ($conceptosNode) {
            $listaConceptos = $conceptosNode->getElementsByTagNameNS($this->cfdiNs, 'Concepto');
            if ($listaConceptos->length === 0) {
                $listaConceptos = $conceptosNode->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Concepto');
            }
            if ($listaConceptos->length === 0) {
                $listaConceptos = $conceptosNode->getElementsByTagName('Concepto');
            }
            foreach ($listaConceptos as $i => $con) {
                $impuestos = [];
                $impNode = $con->getElementsByTagNameNS($this->cfdiNs, 'Impuestos')->item(0)
                    ?? $con->getElementsByTagNameNS('http://www.sat.gob.mx/cfd/3', 'Impuestos')->item(0)
                    ?? $con->getElementsByTagName('Impuestos')->item(0);
                if ($impNode) {
                    $traslados = $impNode->getElementsByTagNameNS($this->cfdiNs, 'Traslado');
                    if ($traslados->length === 0) {
                        $traslados = $impNode->getElementsByTagName('Traslado');
                    }
                    foreach ($traslados as $tr) {
                        $impuestos[] = [
                            'tipo' => 'traslado',
                            'impuesto' => $getAttr($tr, 'Impuesto') ?: '002',
                            'tipo_factor' => $getAttr($tr, 'TipoFactor') ?: 'Tasa',
                            'tasa_o_cuota' => $getAttr($tr, 'TasaOCuota') ? (float) $getAttr($tr, 'TasaOCuota') : null,
                            'base' => (float) ($getAttr($tr, 'Base') ?: 0),
                            'importe' => (float) ($getAttr($tr, 'Importe') ?: 0),
                        ];
                    }
                    $retenciones = $impNode->getElementsByTagNameNS($this->cfdiNs, 'Retencion');
                    if ($retenciones->length === 0) {
                        $retenciones = $impNode->getElementsByTagName('Retencion');
                    }
                    foreach ($retenciones as $ret) {
                        $impuestos[] = [
                            'tipo' => 'retencion',
                            'impuesto' => $getAttr($ret, 'Impuesto') ?: '001',
                            'tipo_factor' => $getAttr($ret, 'TipoFactor') ?: 'Tasa',
                            'tasa_o_cuota' => $getAttr($ret, 'TasaOCuota') ? (float) $getAttr($ret, 'TasaOCuota') : null,
                            'base' => (float) ($getAttr($ret, 'Base') ?: 0),
                            'importe' => (float) ($getAttr($ret, 'Importe') ?: 0),
                        ];
                    }
                }
                $cantidad = (float) ($getAttr($con, 'Cantidad') ?: 1);
                $valorUnit = (float) ($getAttr($con, 'ValorUnitario') ?: 0);
                $importe = (float) ($getAttr($con, 'Importe') ?: ($cantidad * $valorUnit));
                $descuento = (float) ($getAttr($con, 'Descuento') ?: 0);
                $baseImp = $importe - $descuento;
                $conceptos[] = [
                    'clave_prod_serv' => $getAttr($con, 'ClaveProdServ') ?: '01010101',
                    'clave_unidad' => $getAttr($con, 'ClaveUnidad') ?: 'H87',
                    'unidad' => $getAttr($con, 'Unidad') ?: 'Pieza',
                    'no_identificacion' => $getAttr($con, 'NoIdentificacion'),
                    'descripcion' => $getAttr($con, 'Descripcion') ?: 'Concepto',
                    'cantidad' => $cantidad,
                    'valor_unitario' => $valorUnit,
                    'importe' => $importe,
                    'descuento' => $descuento,
                    'base_impuesto' => $baseImp,
                    'objeto_impuesto' => $getAttr($con, 'ObjetoImp') ?: '02',
                    'impuestos' => $impuestos,
                ];
            }
        }

        // Timbre
        $uuid = null;
        $fechaTimbrado = null;
        $noCertificadoSat = null;
        if (preg_match('/<tfd:TimbreFiscalDigital[^>]+UUID="([a-f0-9\-]{36})"/i', $xml, $m)) {
            $uuid = $m[1];
        }
        if (preg_match('/<tfd:TimbreFiscalDigital[^>]+FechaTimbrado="([^"]+)"/i', $xml, $m)) {
            $fechaTimbrado = $m[1];
        }
        if (preg_match('/<tfd:TimbreFiscalDigital[^>]+NoCertificadoSAT="([^"]+)"/i', $xml, $m)) {
            $noCertificadoSat = $m[1];
        }
        if (!$uuid && preg_match('/UUID="([a-f0-9\-]{36})"/i', $xml, $m)) {
            $uuid = $m[1];
        }

        $subtotal = (float) ($comprobanteAttr('SubTotal') ?: 0);
        $descuento = (float) ($comprobanteAttr('Descuento') ?: 0);
        $total = (float) ($comprobanteAttr('Total') ?: 0);

        return [
            'serie' => $comprobanteAttr('Serie'),
            'folio' => $comprobanteAttr('Folio') ?: '0',
            'tipo_comprobante' => $comprobanteAttr('TipoDeComprobante') ?: 'E',
            'fecha_emision' => $fechaEmision,
            'forma_pago' => $comprobanteAttr('FormaPago'),
            'metodo_pago' => $comprobanteAttr('MetodoPago'),
            'moneda' => $comprobanteAttr('Moneda') ?: 'MXN',
            'tipo_cambio' => (float) ($comprobanteAttr('TipoCambio') ?: 1),
            'subtotal' => $subtotal,
            'descuento' => $descuento,
            'total' => $total,
            'lugar_expedicion' => $comprobanteAttr('LugarExpedicion'),
            'rfc_emisor' => $emisor ? $e($emisor, 'Rfc') : '',
            'nombre_emisor' => $emisor ? $e($emisor, 'Nombre') : '',
            'regimen_fiscal_emisor' => $emisor ? $e($emisor, 'RegimenFiscal') : null,
            'rfc_receptor' => $receptor ? $e($receptor, 'Rfc') : '',
            'nombre_receptor' => $receptor ? $e($receptor, 'Nombre') : '',
            'regimen_fiscal_receptor' => $receptor ? $e($receptor, 'RegimenFiscalReceptor') : null,
            'uuid' => $uuid,
            'fecha_timbrado' => $fechaTimbrado,
            'no_certificado_sat' => $noCertificadoSat,
            'conceptos' => $conceptos,
        ];
    }
}
