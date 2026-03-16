<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\ComplementoPago;
use App\Models\CuentaPorCobrar;
use App\Models\DocumentoRelacionadoPago;
use App\Models\Empresa;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\FacturaImpuesto;
use App\Models\PagoRecibido;
use App\Services\FacturamaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Importador de CFDI 4.0 desde XML.
 * Registra facturas (I/E) y complementos de pago (P) en las tablas correspondientes
 * sin modificar la lógica existente de cuentas por cobrar.
 *
 * @see Anexo 20 CFDI 4.0
 */
class ImportadorCfdiService
{
    protected \DOMDocument $dom;
    protected \DOMXPath $xpath;
    protected array $errors = [];
    protected array $warnings = [];

    public function __construct()
    {
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $this->xpath = new \DOMXPath($this->dom);
    }

    /**
     * Importa un CFDI desde XML. Detecta tipo (I/E = factura, P = complemento de pago).
     *
     * @return array{ success: bool, tipo: string|null, modelo: Factura|ComplementoPago|null, errors: array, warnings: array }
     */
    public function importar(string $xml): array
    {
        $this->errors = [];
        $this->warnings = [];

        if (trim($xml) === '') {
            $this->errors[] = 'El XML está vacío.';
            return $this->resultado(false, null, null);
        }

        $prev = libxml_use_internal_errors(true);
        $this->dom = new \DOMDocument('1.0', 'UTF-8');
        $loaded = $this->dom->loadXML($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        if (!$loaded) {
            $this->errors[] = 'El archivo no es un XML válido.';
            return $this->resultado(false, null, null);
        }

        $this->xpath = new \DOMXPath($this->dom);
        $this->xpath->registerNamespace('cfdi', 'http://www.sat.gob.mx/cfd/4');
        $this->xpath->registerNamespace('tfd', 'http://www.sat.gob.mx/TimbreFiscalDigital');
        $this->xpath->registerNamespace('pago20', 'http://www.sat.gob.mx/Pagos20');

        $root = $this->dom->documentElement;
        if (!$root) {
            $this->errors[] = 'El XML no tiene elemento raíz.';
            return $this->resultado(false, null, null);
        }

        $localName = $root->localName ?? $root->nodeName;
        $ns = $root->namespaceURI ?? '';

        // Acuse de cancelación SAT (cancelacfd.sat.gob.mx)
        if (stripos($localName, 'Cancelacion') !== false || stripos($localName, 'Acuse') !== false
            || stripos($ns, 'cancelacfd') !== false || $this->pareceAcuseCancelacion($xml)) {
            return $this->importarAcuseCancelacion($xml);
        }

        if ($localName !== 'Comprobante') {
            $this->errors[] = 'No se encontró el elemento raíz Comprobante (CFDI 4.0) ni Acuse de cancelación.';
            return $this->resultado(false, null, null);
        }

        $comprobante = $root;
        $tipo = $this->attr($comprobante, 'TipoDeComprobante') ?: $this->attr($comprobante, 'tipoDeComprobante');
        if ($tipo === 'P') {
            return $this->importarComplementoPago($xml, $comprobante);
        }
        if ($tipo === 'I' || $tipo === 'E') {
            return $this->importarFactura($xml, $comprobante);
        }

        $this->errors[] = "Tipo de comprobante no soportado para importación: {$tipo}. Use I (ingreso), E (egreso) o P (pago).";
        return $this->resultado(false, null, null);
    }

    /**
     * Detecta si el XML parece un acuse de cancelación (por contenido).
     */
    protected function pareceAcuseCancelacion(string $xml): bool
    {
        $xml = trim($xml);
        return (stripos($xml, 'cancelacfd.sat.gob.mx') !== false)
            || (stripos($xml, 'Cancelacion') !== false && stripos($xml, 'ReferenciaUUID') !== false)
            || (stripos($xml, 'Acuse') !== false && stripos($xml, 'UUID') !== false);
    }

    /**
     * Importa un acuse de cancelación: busca la factura por UUID y la marca como cancelada.
     */
    protected function importarAcuseCancelacion(string $xml): array
    {
        $uuid = $this->extraerUuidDelAcuseCancelacion($xml);
        if (!$uuid) {
            $this->errors[] = 'No se encontró el UUID del CFDI cancelado en el acuse.';
            return $this->resultado(false, 'acuse_cancelacion', null);
        }

        $factura = Factura::where('uuid', $uuid)->first();
        if (!$factura) {
            $this->errors[] = "No existe una factura con UUID {$uuid}. Importe primero la factura y después el acuse de cancelación.";
            return $this->resultado(false, 'acuse_cancelacion', null);
        }

        if ($factura->estado === 'cancelada') {
            $this->warnings[] = "La factura con UUID {$uuid} ya estaba cancelada. Se actualiza el acuse guardado.";
        }

        $acuseBase64 = base64_encode($xml);
        $codigoEstatus = FacturamaService::extraerCodigoEstatusDelAcuse($acuseBase64);

        $factura->update([
            'estado' => 'cancelada',
            'acuse_cancelacion' => $acuseBase64,
            'fecha_cancelacion' => now(),
            'codigo_estatus_cancelacion' => $codigoEstatus,
        ]);

        return $this->resultado(true, 'acuse_cancelacion', $factura);
    }

    /**
     * Extrae el UUID del CFDI cancelado desde el XML del acuse (ReferenciaUUID o similar).
     */
    protected function extraerUuidDelAcuseCancelacion(string $xml): ?string
    {
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            if (preg_match('/ReferenciaUUID[^>]*>([a-f0-9\-]{36})</i', $xml, $m)) {
                return trim($m[1]);
            }
            if (preg_match('/UUID[^>]*>([a-f0-9\-]{36})</i', $xml, $m)) {
                return trim($m[1]);
            }
            if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/i', $xml, $m)) {
                return $m[1];
            }
            return null;
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);
        foreach (['ReferenciaUUID', 'UUID', 'referenciaUUID', 'uuid'] as $tag) {
            $nodes = $xpath->query('//*[local-name()="' . $tag . '"]');
            for ($i = 0; $i < $nodes->length; $i++) {
                $val = trim((string) $nodes->item($i)->textContent);
                if (preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$/i', $val)) {
                    return $val;
                }
            }
        }

        if (preg_match('/([a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12})/i', $xml, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Importa factura CFDI (I o E).
     */
    protected function importarFactura(string $xml, \DOMElement $comp): array
    {
        $emisor = $this->nodo($comp, 'cfdi:Emisor');
        $receptor = $this->nodo($comp, 'cfdi:Receptor');
        $conceptos = $this->nodo($comp, 'cfdi:Conceptos');
        $timbre = $this->obtenerTimbre();

        if (!$emisor || !$receptor || !$conceptos) {
            $this->errors[] = 'Faltan elementos requeridos: Emisor, Receptor o Conceptos.';
            return $this->resultado(false, 'factura', null);
        }

        $rfcEmisor = $this->attr($emisor, 'Rfc') ?: $this->attr($emisor, 'rfc');
        $rfcReceptor = $this->attr($receptor, 'Rfc') ?: $this->attr($receptor, 'rfc');
        $empresa = Empresa::where('rfc', $rfcEmisor)->first() ?? Empresa::principal();
        $cliente = Cliente::where('rfc', $rfcReceptor)->first();

        if (!$empresa) {
            $this->errors[] = "No hay empresa configurada con RFC emisor {$rfcEmisor}. Configure la empresa en Sistema > Configuración.";
            return $this->resultado(false, 'factura', null);
        }
        if (!$cliente) {
            $this->errors[] = "Cliente con RFC {$rfcReceptor} no existe. Regístrelo en Administración > Clientes.";
            return $this->resultado(false, 'factura', null);
        }

        $uuid = $timbre['UUID'] ?? null;
        if ($uuid && Factura::withTrashed()->where('uuid', $uuid)->exists()) {
            $this->warnings[] = "La factura con UUID {$uuid} ya existe. Se omite la importación.";
            return $this->resultado(false, 'factura', null);
        }

        $serie = $this->attr($comp, 'Serie') ?: $this->attr($comp, 'serie') ?: 'A';
        $folio = $this->attr($comp, 'Folio') ?: $this->attr($comp, 'folio') ?: '0';
        $folio = (is_numeric($folio) ? (int) $folio : (int) preg_replace('/\D/', '', $folio)) ?: 0;
        if (Factura::where('serie', $serie)->where('folio', $folio)->exists()) {
            $this->errors[] = "Ya existe una factura con serie {$serie} y folio {$folio}.";
            return $this->resultado(false, 'factura', null);
        }

        $fecha = $this->attr($comp, 'Fecha') ?: $this->attr($comp, 'fecha');
        $fechaEmision = $fecha ? \Carbon\Carbon::parse($fecha) : now();
        $formaPago = $this->attr($comp, 'FormaPago') ?: $this->attr($comp, 'formaPago') ?: '99';
        $metodoPago = $this->attr($comp, 'MetodoPago') ?: $this->attr($comp, 'metodoPago') ?: 'PUE';
        $subtotal = (float) ($this->attr($comp, 'SubTotal') ?: $this->attr($comp, 'subTotal') ?: 0);
        $descuento = (float) ($this->attr($comp, 'Descuento') ?: $this->attr($comp, 'descuento') ?: 0);
        $total = (float) ($this->attr($comp, 'Total') ?: $this->attr($comp, 'total') ?: 0);
        $moneda = $this->attr($comp, 'Moneda') ?: $this->attr($comp, 'moneda') ?: 'MXN';
        $tipoCambio = (float) ($this->attr($comp, 'TipoCambio') ?: $this->attr($comp, 'tipoCambio') ?: 1);
        $lugarExp = $this->attr($comp, 'LugarExpedicion') ?: $this->attr($comp, 'lugarExpedicion') ?: $empresa->codigo_postal ?? '01000';

        DB::beginTransaction();
        try {
            $factura = Factura::create([
                'serie' => $serie,
                'folio' => $folio,
                'tipo_comprobante' => $this->attr($comp, 'TipoDeComprobante') ?: 'I',
                'estado' => 'timbrada',
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresa->id,
                'rfc_emisor' => $rfcEmisor,
                'nombre_emisor' => $this->attr($emisor, 'Nombre') ?: $this->attr($emisor, 'nombre') ?: $empresa->razon_social,
                'regimen_fiscal_emisor' => $this->attr($emisor, 'RegimenFiscal') ?: $this->attr($emisor, 'regimenFiscal') ?: $empresa->regimen_fiscal,
                'rfc_receptor' => $rfcReceptor,
                'nombre_receptor' => $this->attr($receptor, 'Nombre') ?: $this->attr($receptor, 'nombre') ?: $cliente->nombre,
                'uso_cfdi' => $this->attr($receptor, 'UsoCFDI') ?: $this->attr($receptor, 'usoCFDI') ?: ($cliente->uso_cfdi_default ?? 'G03'),
                'regimen_fiscal_receptor' => $this->attr($receptor, 'RegimenFiscalReceptor') ?: $this->attr($receptor, 'regimenFiscalReceptor') ?: $cliente->regimen_fiscal,
                'domicilio_fiscal_receptor' => $this->attr($receptor, 'DomicilioFiscalReceptor') ?: $this->attr($receptor, 'domicilioFiscalReceptor') ?: $cliente->codigo_postal,
                'lugar_expedicion' => $lugarExp,
                'fecha_emision' => $fechaEmision,
                'forma_pago' => strlen($formaPago) === 2 ? $formaPago : '99',
                'metodo_pago' => in_array($metodoPago, ['PUE', 'PPD'], true) ? $metodoPago : 'PUE',
                'moneda' => $moneda,
                'tipo_cambio' => $tipoCambio,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'total' => $total,
                'uuid' => $uuid,
                'fecha_timbrado' => !empty($timbre['FechaTimbrado']) ? \Carbon\Carbon::parse($timbre['FechaTimbrado']) : null,
                'no_certificado_sat' => $timbre['NoCertificadoSAT'] ?? null,
                'sello_cfdi' => $timbre['SelloCFDI'] ?? null,
                'sello_sat' => $timbre['SelloSAT'] ?? null,
                'cadena_original' => $timbre['CadenaOriginal'] ?? null,
                'xml_content' => $xml,
                'usuario_id' => auth()->id(),
            ]);

            $orden = 0;
            foreach ($this->xpath->query('cfdi:Concepto', $conceptos) as $concepto) {
                if (!$concepto instanceof \DOMElement) {
                    continue;
                }
                $cantidad = (float) ($this->attr($concepto, 'Cantidad') ?: $this->attr($concepto, 'cantidad') ?: 1);
                $valorUnitario = (float) ($this->attr($concepto, 'ValorUnitario') ?: $this->attr($concepto, 'valorUnitario') ?: 0);
                $importe = (float) ($this->attr($concepto, 'Importe') ?: $this->attr($concepto, 'importe') ?: $cantidad * $valorUnitario);
                $descuentoConcepto = (float) ($this->attr($concepto, 'Descuento') ?: $this->attr($concepto, 'descuento') ?: 0);
                $objetoImp = $this->attr($concepto, 'ObjetoImp') ?: $this->attr($concepto, 'objetoImp') ?: '02';
                $baseImpuesto = $importe - $descuentoConcepto;

                $detalle = FacturaDetalle::create([
                    'factura_id' => $factura->id,
                    'producto_id' => null,
                    'clave_prod_serv' => $this->attr($concepto, 'ClaveProdServ') ?: $this->attr($concepto, 'claveProdServ') ?: '01010101',
                    'clave_unidad' => $this->attr($concepto, 'ClaveUnidad') ?: $this->attr($concepto, 'claveUnidad') ?: 'H87',
                    'unidad' => $this->attr($concepto, 'Unidad') ?: $this->attr($concepto, 'unidad') ?: 'Pieza',
                    'no_identificacion' => $this->attr($concepto, 'NoIdentificacion') ?: $this->attr($concepto, 'noIdentificacion'),
                    'descripcion' => $this->attr($concepto, 'Descripcion') ?: $this->attr($concepto, 'descripcion') ?: 'Concepto',
                    'cantidad' => $cantidad,
                    'valor_unitario' => $valorUnitario,
                    'importe' => $importe,
                    'descuento' => $descuentoConcepto,
                    'base_impuesto' => $baseImpuesto,
                    'objeto_impuesto' => in_array($objetoImp, ['01', '02', '03'], true) ? $objetoImp : '02',
                    'orden' => $orden++,
                ]);

                $impuestos = $this->xpath->query('cfdi:Impuestos', $concepto)->item(0);
                if ($impuestos) {
                    $traslados = $this->xpath->query('cfdi:Traslados/cfdi:Traslado', $impuestos);
                    foreach ($traslados as $t) {
                        if ($t instanceof \DOMElement) {
                            $base = (float) ($this->attr($t, 'Base') ?: $this->attr($t, 'base') ?: $baseImpuesto);
                            $importeImp = (float) ($this->attr($t, 'Importe') ?: $this->attr($t, 'importe') ?: 0);
                            $tasa = (float) ($this->attr($t, 'TasaOCuota') ?: $this->attr($t, 'tasaOCuota') ?: 0);
                            FacturaImpuesto::create([
                                'factura_detalle_id' => $detalle->id,
                                'tipo' => 'traslado',
                                'impuesto' => $this->attr($t, 'Impuesto') ?: $this->attr($t, 'impuesto') ?: '002',
                                'tipo_factor' => $this->attr($t, 'TipoFactor') ?: $this->attr($t, 'tipoFactor') ?: 'Tasa',
                                'tasa_o_cuota' => $tasa,
                                'base' => $base,
                                'importe' => $importeImp,
                            ]);
                        }
                    }
                    $retenciones = $this->xpath->query('cfdi:Retenciones/cfdi:Retencion', $impuestos);
                    foreach ($retenciones as $r) {
                        if ($r instanceof \DOMElement) {
                            $base = (float) ($this->attr($r, 'Base') ?: $this->attr($r, 'base') ?: $baseImpuesto);
                            $importeImp = (float) ($this->attr($r, 'Importe') ?: $this->attr($r, 'importe') ?: 0);
                            $tasa = (float) ($this->attr($r, 'TasaOCuota') ?: $this->attr($r, 'tasaOCuota') ?: 0);
                            FacturaImpuesto::create([
                                'factura_detalle_id' => $detalle->id,
                                'tipo' => 'retencion',
                                'impuesto' => $this->attr($r, 'Impuesto') ?: $this->attr($r, 'impuesto') ?: '001',
                                'tipo_factor' => $this->attr($r, 'TipoFactor') ?: $this->attr($r, 'tipoFactor') ?: 'Tasa',
                                'tasa_o_cuota' => $tasa,
                                'base' => $base,
                                'importe' => $importeImp,
                            ]);
                        }
                    }
                }
            }

            if ($factura->metodo_pago === 'PPD') {
                $diasCredito = (int) ($cliente->dias_credito ?? 30);
                $fechaVencimiento = $fechaEmision->copy()->addDays($diasCredito);
                CuentaPorCobrar::create([
                    'factura_id' => $factura->id,
                    'cliente_id' => $cliente->id,
                    'monto_total' => $total,
                    'monto_pagado' => 0,
                    'monto_pendiente' => $total,
                    'fecha_emision' => $fechaEmision->toDateString(),
                    'fecha_vencimiento' => $fechaVencimiento->toDateString(),
                    'estado' => 'pendiente',
                ]);
                $cliente->actualizarSaldo();
            }

            $xmlPath = $this->guardarXmlEnStorage('facturas', $factura->folio_completo . '.xml', $xml);
            $factura->update(['xml_path' => $xmlPath]);

            DB::commit();
            return $this->resultado(true, 'factura', $factura);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ImportadorCfdi factura', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            $this->errors[] = 'Error al guardar: ' . $e->getMessage();
            return $this->resultado(false, 'factura', null);
        }
    }

    /**
     * Importa complemento de pago CFDI (P).
     */
    protected function importarComplementoPago(string $xml, \DOMElement $comp): array
    {
        $emisor = $this->nodo($comp, 'cfdi:Emisor');
        $receptor = $this->nodo($comp, 'cfdi:Receptor');
        $timbre = $this->obtenerTimbre();
        $pagos = $this->xpath->query('//pago20:Pagos', $this->dom)->item(0);
        if (!$pagos) {
            $pagos = $this->xpath->query('//*[local-name()="Pagos"]', $this->dom)->item(0);
        }

        if (!$emisor || !$receptor || !$pagos) {
            $this->errors[] = 'Faltan elementos: Emisor, Receptor o Pagos (complemento de pago).';
            return $this->resultado(false, 'complemento', null);
        }

        $rfcEmisor = $this->attr($emisor, 'Rfc') ?: $this->attr($emisor, 'rfc');
        $rfcReceptor = $this->attr($receptor, 'Rfc') ?: $this->attr($receptor, 'rfc');
        $empresa = Empresa::where('rfc', $rfcEmisor)->first() ?? Empresa::principal();
        $cliente = Cliente::where('rfc', $rfcReceptor)->first();

        if (!$empresa || !$cliente) {
            $this->errors[] = $cliente ? "Empresa con RFC {$rfcEmisor} no encontrada." : "Cliente con RFC {$rfcReceptor} no existe.";
            return $this->resultado(false, 'complemento', null);
        }

        $uuid = $timbre['UUID'] ?? null;
        if ($uuid && ComplementoPago::withTrashed()->where('uuid', $uuid)->exists()) {
            $this->warnings[] = "El complemento con UUID {$uuid} ya existe. Se omite.";
            return $this->resultado(false, 'complemento', null);
        }

        $serie = $this->attr($comp, 'Serie') ?: $this->attr($comp, 'serie') ?: 'P';
        $folio = $this->attr($comp, 'Folio') ?: $this->attr($comp, 'folio') ?: '0';
        $folio = (is_numeric($folio) ? (int) $folio : (int) preg_replace('/\D/', '', $folio)) ?: 0;
        if (ComplementoPago::where('serie', $serie)->where('folio', $folio)->exists()) {
            $this->errors[] = "Ya existe un complemento con serie {$serie} y folio {$folio}.";
            return $this->resultado(false, 'complemento', null);
        }

        $fecha = $this->attr($comp, 'Fecha') ?: $this->attr($comp, 'fecha');
        $fechaEmision = $fecha ? \Carbon\Carbon::parse($fecha) : now();
        $lugarExp = $this->attr($comp, 'LugarExpedicion') ?: $this->attr($comp, 'lugarExpedicion') ?: $empresa->codigo_postal ?? '01000';

        $montoTotal = 0;
        $listaPagos = [];
        $pagoNodes = $this->xpath->query('.//pago20:Pago | .//*[local-name()="Pago"]', $pagos);
        foreach ($pagoNodes as $pagoNode) {
            if (!$pagoNode instanceof \DOMElement) {
                continue;
            }
            $fechaPago = $this->attr($pagoNode, 'FechaPago') ?: $this->attr($pagoNode, 'fechaPago');
            $formaPago = $this->attr($pagoNode, 'FormaPago') ?: $this->attr($pagoNode, 'formaPago') ?: '03';
            $moneda = $this->attr($pagoNode, 'Moneda') ?: $this->attr($pagoNode, 'moneda') ?: 'MXN';
            $monto = (float) ($this->attr($pagoNode, 'Monto') ?: $this->attr($pagoNode, 'monto') ?: 0);
            $tipoCambio = (float) ($this->attr($pagoNode, 'TipoCambio') ?: $this->attr($pagoNode, 'tipoCambio') ?: 1);
            $docRel = $this->xpath->query('.//pago20:DoctoRelacionado | .//*[local-name()="DoctoRelacionado"]', $pagoNode);
            $doctos = [];
            foreach ($docRel as $dr) {
                if ($dr instanceof \DOMElement) {
                    $doctos[] = [
                        'IdDocumento' => $this->attr($dr, 'IdDocumento') ?: $this->attr($dr, 'idDocumento'),
                        'Folio' => $this->attr($dr, 'Folio') ?: $this->attr($dr, 'folio'),
                        'Moneda' => $this->attr($dr, 'Moneda') ?: $this->attr($dr, 'moneda') ?: 'MXN',
                        'ImpSaldoAnt' => (float) ($this->attr($dr, 'ImpSaldoAnt') ?: $this->attr($dr, 'impSaldoAnt') ?: 0),
                        'ImpPagado' => (float) ($this->attr($dr, 'ImpPagado') ?: $this->attr($dr, 'impPagado') ?: 0),
                        'ImpSaldoInsoluto' => (float) ($this->attr($dr, 'ImpSaldoInsoluto') ?: $this->attr($dr, 'impSaldoInsoluto') ?: 0),
                        'NumParcialidad' => (int) ($this->attr($dr, 'NumParcialidad') ?: $this->attr($dr, 'numParcialidad') ?: 1),
                    ];
                }
            }
            $montoTotal += $monto;
            $listaPagos[] = [
                'fecha_pago' => $fechaPago ? \Carbon\Carbon::parse($fechaPago) : $fechaEmision,
                'forma_pago' => strlen($formaPago) === 2 ? $formaPago : '03',
                'moneda' => $moneda,
                'tipo_cambio' => $tipoCambio,
                'monto' => $monto,
                'doctos' => $doctos,
            ];
        }

        if (empty($listaPagos)) {
            $this->errors[] = 'El complemento de pago no tiene nodos Pago.';
            return $this->resultado(false, 'complemento', null);
        }

        if ($montoTotal <= 0) {
            $montoTotal = (float) ($this->attr($comp, 'Total') ?: $this->attr($comp, 'total') ?: 0);
        }

        DB::beginTransaction();
        try {
            $complemento = ComplementoPago::create([
                'serie' => $serie,
                'folio' => $folio,
                'estado' => 'timbrado',
                'cliente_id' => $cliente->id,
                'empresa_id' => $empresa->id,
                'rfc_emisor' => $rfcEmisor,
                'nombre_emisor' => $this->attr($emisor, 'Nombre') ?: $empresa->razon_social,
                'rfc_receptor' => $rfcReceptor,
                'nombre_receptor' => $this->attr($receptor, 'Nombre') ?: $cliente->nombre,
                'fecha_emision' => $fechaEmision,
                'lugar_expedicion' => $lugarExp,
                'monto_total' => $montoTotal,
                'uuid' => $uuid,
                'fecha_timbrado' => !empty($timbre['FechaTimbrado']) ? \Carbon\Carbon::parse($timbre['FechaTimbrado']) : null,
                'no_certificado_sat' => $timbre['NoCertificadoSAT'] ?? null,
                'sello_cfdi' => $timbre['SelloCFDI'] ?? null,
                'sello_sat' => $timbre['SelloSAT'] ?? null,
                'cadena_original' => $timbre['CadenaOriginal'] ?? null,
                'xml_content' => $xml,
                'usuario_id' => auth()->id(),
            ]);

            foreach ($listaPagos as $p) {
                $pagoRecibido = PagoRecibido::create([
                    'complemento_pago_id' => $complemento->id,
                    'fecha_pago' => $p['fecha_pago'],
                    'forma_pago' => $p['forma_pago'],
                    'moneda' => $p['moneda'],
                    'tipo_cambio' => $p['tipo_cambio'],
                    'monto' => $p['monto'],
                ]);

                foreach ($p['doctos'] as $doc) {
                    $facturaUuid = $doc['IdDocumento'] ?? '';
                    $factura = Factura::where('uuid', $facturaUuid)->first();
                    if (!$factura) {
                        $this->warnings[] = "Factura con UUID {$facturaUuid} no encontrada. Importe antes las facturas relacionadas para que el complemento actualice cuentas por cobrar.";
                        continue;
                    }

                    DocumentoRelacionadoPago::create([
                        'pago_recibido_id' => $pagoRecibido->id,
                        'factura_id' => $factura->id,
                        'factura_uuid' => $facturaUuid,
                        'serie' => $factura->serie ?? '',
                        'folio' => $doc['Folio'] ?? (string) $factura->folio,
                        'moneda' => $doc['Moneda'] ?? 'MXN',
                        'monto_total' => (float) $factura->total,
                        'parcialidad' => $doc['NumParcialidad'] ?? 1,
                        'saldo_anterior' => $doc['ImpSaldoAnt'],
                        'monto_pagado' => $doc['ImpPagado'],
                        'saldo_insoluto' => $doc['ImpSaldoInsoluto'],
                    ]);

                    if ($factura->cuentaPorCobrar) {
                        $factura->cuentaPorCobrar->registrarPago((float) $doc['ImpPagado']);
                    }
                }
            }

            $xmlPath = $this->guardarXmlEnStorage('complementos', $complemento->folio_completo . '.xml', $xml);
            $complemento->update(['xml_path' => $xmlPath]);

            DB::commit();
            return $this->resultado(true, 'complemento', $complemento);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ImportadorCfdi complemento', ['message' => $e->getMessage()]);
            $this->errors[] = 'Error al guardar complemento: ' . $e->getMessage();
            return $this->resultado(false, 'complemento', null);
        }
    }

    protected function obtenerTimbre(): array
    {
        $tfd = $this->xpath->query('//tfd:TimbreFiscalDigital | //*[local-name()="TimbreFiscalDigital"]', $this->dom)->item(0);
        if (!$tfd instanceof \DOMElement) {
            return [];
        }
        $out = [];
        foreach (['Version', 'UUID', 'FechaTimbrado', 'SelloCFDI', 'NoCertificadoSAT', 'SelloSAT'] as $name) {
            $alt = lcfirst($name);
            $v = $this->attr($tfd, $name) ?: $this->attr($tfd, $alt);
            if ($v !== null) {
                $out[$name] = $v;
            }
        }
        if (!empty($out['Version']) && !empty($out['UUID']) && !empty($out['FechaTimbrado']) && !empty($out['SelloCFDI']) && !empty($out['NoCertificadoSAT'])) {
            $out['CadenaOriginal'] = implode('|', [$out['Version'], $out['UUID'], $out['FechaTimbrado'], $out['SelloCFDI'], $out['NoCertificadoSAT']]);
        }
        return $out;
    }

    protected function nodo(\DOMElement $parent, string $name): ?\DOMElement
    {
        $list = $this->xpath->query($name, $parent);
        $el = $list->item(0);
        return $el instanceof \DOMElement ? $el : null;
    }

    protected function attr(\DOMElement $el, string $name): ?string
    {
        $v = $el->getAttribute($name);
        if ($v !== '') {
            return $v;
        }
        $v = $el->getAttribute(lcfirst($name));
        return $v !== '' ? $v : null;
    }

    /**
     * Guarda el XML en storage (misma ruta que FacturaController/ComplementoPagoController al timbrar).
     * Usa storage_path('app/...') para que descargarXML encuentre el archivo.
     */
    protected function guardarXmlEnStorage(string $carpeta, string $nombreArchivo, string $xml): string
    {
        $subcarpeta = $carpeta . '/' . now()->format('Y/m');
        $directory = storage_path('app/' . $subcarpeta);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $filepath = $directory . '/' . $nombreArchivo;
        file_put_contents($filepath, $xml);
        return $subcarpeta . '/' . $nombreArchivo;
    }

    protected function resultado(bool $success, ?string $tipo, $modelo): array
    {
        return [
            'success' => $success,
            'tipo' => $tipo,
            'modelo' => $modelo,
            'errors' => $this->errors,
            'warnings' => $this->warnings,
        ];
    }
}
