<?php

namespace App\Services;

use App\Models\Factura;
use App\Models\ComplementoPago;
use App\Models\NotaCredito;
use App\Models\Empresa;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Integración con Facturama API REST (CFDI 4.0).
 * Documentación: https://apisandbox.facturama.mx/
 * Sandbox: https://apisandbox.facturama.mx/
 * Producción: https://api.facturama.mx/
 */
class FacturamaService
{
    protected string $baseUrl;
    protected string $user;
    protected string $password;

    public function __construct(Empresa $empresa)
    {
        $this->baseUrl = rtrim($empresa->facturama_base_url ?? 'https://apisandbox.facturama.mx', '/');
        [$this->user, $this->password] = $empresa->getFacturamaCredentials();
    }

    /**
     * Cliente HTTP con auth y opciones SSL (evita cURL error 60 en producción).
     * Usa CURL_CA_BUNDLE del .env o detecta automáticamente el bundle del sistema.
     */
    protected function http(): \Illuminate\Http\Client\PendingRequest
    {
        $verify = config('services.facturama.verify');

        if (is_string($verify) && strtolower($verify) === 'false') {
            $verify = false;
        } elseif (is_string($verify) && $verify !== '' && file_exists($verify)) {
            // Ruta explícita en .env (ej. /etc/pki/tls/certs/ca-bundle.crt)
        } elseif ($verify === null || $verify === '') {
            // Detección automática: rutas comunes del CA bundle del sistema
            $paths = [
                '/etc/pki/tls/certs/ca-bundle.crt',      // RHEL/CentOS/Fedora
                '/etc/ssl/certs/ca-certificates.crt',    // Debian/Ubuntu
                '/etc/ssl/cert.pem',                     // macOS
            ];
            foreach ($paths as $p) {
                if (file_exists($p)) {
                    $verify = $p;
                    break;
                }
            }
            if ($verify === null || $verify === '') {
                $verify = true; // Default de cURL
            }
        } else {
            $verify = true;
        }

        return Http::withBasicAuth($this->user, $this->password)
            ->withOptions(['verify' => $verify]);
    }

    /**
     * Timbrar factura enviando el CFDI a Facturama.
     *
     * @return array ['success' => bool, 'uuid' => string, 'xml' => string, 'fecha_timbrado' => Carbon, 'no_certificado_sat' => string, 'sello_cfdi' => string, 'sello_sat' => string, 'cadena_original' => string, 'message' => string]
     */
    public function timbrarFactura(Factura $factura): array
    {
        $factura->load(['detalles.impuestos', 'empresa']);

        $body = $this->buildCfdiBody($factura);

        $validacion = $this->validarCuerpoAntesDeEnviar($body);
        if ($validacion !== null) {
            return [
                'success' => false,
                'message' => 'Datos de la factura incompletos para Facturama: ' . $validacion,
            ];
        }

        $response = $this->http()
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl . '/3/cfdis', $body);

        if (!$response->successful()) {
            $bodyErr = $response->json();
            $rawBody = $response->body();
            $message = null;
            $modelStateLines = [];
            if (is_array($bodyErr)) {
                $message = $bodyErr['Message'] ?? $bodyErr['message'] ?? null;
                if (!empty($bodyErr['ModelState']) && is_array($bodyErr['ModelState'])) {
                    foreach ($bodyErr['ModelState'] as $campo => $errores) {
                        $lista = is_array($errores) ? $errores : [$errores];
                        foreach ($lista as $e) {
                            $modelStateLines[] = is_string($e) ? $e : (string) json_encode($e);
                        }
                    }
                    if ($modelStateLines !== []) {
                        $message = implode(' ', $modelStateLines);
                    }
                }
                if ($message === null && !empty($modelStateLines)) {
                    $message = implode(' ', $modelStateLines);
                }
            }
            if ($message === null || $message === '') {
                $message = preg_replace('/\s+/', ' ', strip_tags($rawBody));
                if (strlen($message) > 250) {
                    $message = substr($message, 0, 250) . '…';
                }
            }
            if (is_array($message)) {
                $message = json_encode($message);
            }
            $status = $response->status();
            Log::warning('Facturama timbrado fallido', [
                'status' => $status,
                'response_body' => $rawBody,
                'request_body' => $body,
            ]);
            $statusHint = $status === 401 ? ' Credenciales incorrectas (revisa usuario/contraseña de ' . (str_contains($this->baseUrl, 'sandbox') ? 'sandbox' : 'producción') . ').' : '';
            $mensajeFinal = trim((string) $message);
            if ($mensajeFinal === '') {
                $mensajeFinal = 'Revisa que el perfil fiscal y CSD estén cargados en tu cuenta Facturama.';
            }
            if (stripos($mensajeFinal, 'ExpeditionPlace') !== false || stripos($mensajeFinal, 'Lugares de expedición') !== false || stripos($mensajeFinal, 'Lugar de expedición') !== false) {
                $mensajeFinal = 'El código postal del lugar de expedición (' . ($body['ExpeditionPlace'] ?? '') . ') debe estar dado de alta en Facturama. Entra a tu cuenta Facturama (sandbox o producción) → Perfil fiscal → Lugares de expedición / Series y agrega un lugar con ese CP (o usa el CP que ya tengas configurado ahí). ' . $mensajeFinal;
            }
            if (stripos($mensajeFinal, 'Serie') !== false && (stripos($mensajeFinal, 'sucursal') !== false || stripos($mensajeFinal, 'existir') !== false)) {
                $serie = $body['Serie'] ?? $body['serie'] ?? 'A';
                $esProduccion = str_contains($this->baseUrl, 'api.facturama.mx') && !str_contains($this->baseUrl, 'sandbox');
                $entorno = $esProduccion ? 'producción (api.facturama.mx)' : 'sandbox (apisandbox.facturama.mx)';
                $mensajeFinal = 'La serie "' . $serie . '" debe existir en tu sucursal de Facturama. Estás usando ' . $entorno . '. Entra a Facturama en ese entorno → Perfil fiscal → Lugares de expedición → elige la sucursal (ej. CP ' . ($body['ExpeditionPlace'] ?? '') . ') → Series y crea una serie con el nombre "' . $serie . '". Si configuraste la serie en producción, asegúrate de tener "Producción Facturama" seleccionado en Configuración de empresa (no Sandbox). ' . $mensajeFinal;
            }
            if (stripos($mensajeFinal, 'Nombre del receptor') !== false && stripos($mensajeFinal, 'RFC') !== false) {
                $rfc = $body['Receiver']['Rfc'] ?? '';
                $nombre = $body['Receiver']['Name'] ?? '';
                $mensajeFinal = 'El nombre del receptor debe coincidir con el registrado en el SAT para el RFC ' . $rfc . '. Revisa en el cliente que el nombre sea exactamente el que aparece en la constancia de situación fiscal (sin abreviaturas, con acentos correctos). Nombre enviado: "' . $nombre . '". ' . $mensajeFinal;
            }
            return [
                'success' => false,
                'message' => 'Facturama (HTTP ' . $status . '):' . $statusHint . ' ' . $mensajeFinal,
            ];
        }

        $data = $response->json();
        $cfdiId = $data['Id'] ?? $data['id'] ?? null;
        if (!$cfdiId) {
            return [
                'success' => false,
                'message' => 'Facturama no devolvió el Id del CFDI',
            ];
        }

        // UUID puede venir en la respuesta o en el XML timbrado
        $uuid = $data['Uuid'] ?? $data['uuid'] ?? null;
        $xml = null;
        $fechaTimbrado = isset($data['Date']) ? \Carbon\Carbon::parse($data['Date']) : now();

        $xmlResponse = $this->http()
            ->acceptJson()
            ->timeout(15)
            ->get($this->baseUrl . '/cfdi/xml/issued/' . $cfdiId);

        $selloCfdi = null;
        $selloSat = null;
        $cadenaOriginal = null;

        if ($xmlResponse->successful()) {
            $fileData = $xmlResponse->json();
            $content = $fileData['Content'] ?? null;
            if ($content) {
                $xml = base64_decode($content, true);
                if ($xml) {
                    if (!$uuid) {
                        $uuid = $this->extraerUuidDelXml($xml);
                    }
                    $timbre = $this->extraerTimbreDelXml($xml);
                    $selloCfdi = $timbre['sello_cfdi'] ?? null;
                    $selloSat = $timbre['sello_sat'] ?? null;
                    $cadenaOriginal = $timbre['cadena_original'] ?? null;
                    if (!empty($timbre['fecha_timbrado'])) {
                        $fechaTimbrado = \Carbon\Carbon::parse($timbre['fecha_timbrado']);
                    }
                    if (empty($data['CertNumber']) && !empty($timbre['no_certificado_sat'])) {
                        $data['CertNumber'] = $timbre['no_certificado_sat'];
                    }
                }
            }
        }

        if (!$uuid) {
            $uuid = $data['Folio'] ?? $cfdiId;
        }

        return [
            'success' => true,
            'uuid' => (string) $uuid,
            'pac_cfdi_id' => (string) $cfdiId,
            'xml' => $xml ?: '',
            'fecha_timbrado' => $fechaTimbrado,
            'no_certificado_sat' => $data['CertNumber'] ?? $data['NoCertificado'] ?? null,
            'sello_cfdi' => $selloCfdi ?? '',
            'sello_sat' => $selloSat ?? '',
            'cadena_original' => $cadenaOriginal ?? '',
            'message' => 'Factura timbrada con Facturama correctamente',
        ];
    }

    /**
     * Timbrar complemento de pago (CFDI tipo P) en Facturama.
     * POST /3/cfdis con CfdiType "P", Complemento.Payments y RelatedDocuments.
     *
     * @return array ['success' => bool, 'uuid' => string, 'xml' => string, 'fecha_timbrado' => Carbon, 'message' => string]
     */
    public function timbrarComplementoPago(ComplementoPago $complemento): array
    {
        $complemento->load(['pagosRecibidos.documentosRelacionados.factura.detalles.impuestos', 'cliente', 'empresa']);
        $empresa = $complemento->empresa ?? Empresa::principal();
        $cliente = $complemento->cliente;

        $lugarExp = $this->normalizarCodigoPostal($complemento->lugar_expedicion ?: $empresa->codigo_postal ?? null);
        if ($lugarExp === '00000') {
            $lugarExp = '01000';
        }
        $cpReceptor = $this->normalizarCodigoPostal($cliente->codigo_postal ?? null);
        if ($cpReceptor === '00000') {
            $cpReceptor = '01000';
        }

        // Validar que todas las facturas tengan UUID válido antes de enviar
        foreach ($complemento->pagosRecibidos as $pago) {
            foreach ($pago->documentosRelacionados as $doc) {
                $uuid = trim((string) ($doc->factura_uuid ?? ''));
                if ($uuid === '' || !preg_match('/^[a-f0-9\-]{36}$/i', $uuid)) {
                    return [
                        'success' => false,
                        'message' => 'La factura ' . ($doc->serie ?? '') . '-' . $doc->folio . ' no tiene UUID válido. Solo se pueden incluir facturas timbradas.',
                    ];
                }
            }
        }

        $payments = [];
        foreach ($complemento->pagosRecibidos as $pago) {
            $relatedDocs = [];
            foreach ($pago->documentosRelacionados as $doc) {
                $factura = $doc->factura;
                $taxesForDoc = $this->impuestosParaDoctoRelacionado($factura, (float) $doc->monto_pagado);
                $taxObject = !empty($taxesForDoc) ? '02' : '01';
                $prevBalance = round((float) $doc->saldo_anterior, 2);
                $amountPaid = round((float) $doc->monto_pagado, 2);
                $saldoInsoluto = round($prevBalance - $amountPaid, 2);
                $relatedDoc = [
                    'TaxObject' => $taxObject,
                    'Uuid' => trim((string) $doc->factura_uuid),
                    'Serie' => trim((string) ($doc->serie ?? '')) !== '' ? trim($doc->serie) : 'NA',
                    'Folio' => (string) $doc->folio,
                    'PaymentMethod' => $factura ? ($factura->metodo_pago ?? 'PUE') : 'PUE',
                    'PartialityNumber' => (string) $doc->parcialidad,
                    'PreviousBalanceAmount' => $prevBalance,
                    'AmountPaid' => $amountPaid,
                    'ImpSaldoInsoluto' => $saldoInsoluto,
                    'Currency' => $doc->moneda ?? 'MXN',
                ];
                if ($taxObject === '02' && !empty($taxesForDoc)) {
                    $relatedDoc['Taxes'] = $taxesForDoc;
                }
                $relatedDocs[] = $relatedDoc;
            }
            $payment = [
                'Date' => $pago->fecha_pago ? \Carbon\Carbon::parse($pago->fecha_pago)->format('Y-m-d\TH:i:s') : now()->format('Y-m-d\TH:i:s'),
                'PaymentForm' => $pago->forma_pago ?? '03',
                'Amount' => round((float) $pago->monto, 2),
                'RelatedDocuments' => $relatedDocs,
            ];
            if (!empty($pago->moneda) && $pago->moneda !== 'MXN') {
                $payment['Currency'] = $pago->moneda;
                $payment['ExchangeRate'] = round((float) ($pago->tipo_cambio ?? 1), 6);
            }
            if (!empty($pago->num_operacion)) {
                $payment['OperationNumber'] = substr((string) $pago->num_operacion, 0, 100);
            }
            $payments[] = $payment;
        }

        $body = [
            'CfdiType' => 'P',
            'NameId' => '14',
            'Folio' => (string) $complemento->folio,
            'ExpeditionPlace' => $lugarExp,
            'Receiver' => [
                'Rfc' => trim($complemento->rfc_receptor),
                'CfdiUse' => 'CP01',
                'Name' => trim($complemento->nombre_receptor),
                'FiscalRegime' => preg_match('/^\d{3}$/', (string) ($cliente->regimen_fiscal ?? '')) ? $cliente->regimen_fiscal : '616',
                'TaxZipCode' => $cpReceptor,
            ],
            'Complemento' => [
                'Payments' => $payments,
            ],
        ];

        $response = $this->http()
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl . '/3/cfdis', $body);

        if (!$response->successful()) {
            $bodyErr = $response->json();
            $rawBody = $response->body();
            $message = null;
            $modelStateLines = [];
            if (is_array($bodyErr)) {
                $message = $bodyErr['Message'] ?? $bodyErr['message'] ?? null;
                if (!empty($bodyErr['ModelState']) && is_array($bodyErr['ModelState'])) {
                    foreach ($bodyErr['ModelState'] as $campo => $errores) {
                        $lista = is_array($errores) ? $errores : [$errores];
                        foreach ($lista as $e) {
                            $modelStateLines[] = is_string($e) ? $e : (string) json_encode($e);
                        }
                    }
                    if (!empty($modelStateLines)) {
                        $message = implode(' ', $modelStateLines);
                    }
                }
            }
            if ($message === null || $message === '') {
                $message = preg_replace('/\s+/', ' ', strip_tags($rawBody));
                if (strlen($message) > 500) {
                    $message = substr($message, 0, 500) . '…';
                }
            }
            if (is_array($message)) {
                $message = json_encode($message);
            }
            Log::error('Facturama complemento de pago fallido', [
                'status' => $response->status(),
                'response_body' => $rawBody,
                'request_body' => $body,
            ]);
            return [
                'success' => false,
                'message' => 'Facturama: ' . trim((string) $message),
            ];
        }

        $data = $response->json();
        $cfdiId = $data['Id'] ?? $data['id'] ?? null;
        if (!$cfdiId) {
            return [
                'success' => false,
                'message' => 'Facturama no devolvió el Id del CFDI',
            ];
        }

        $uuid = $data['Uuid'] ?? $data['uuid'] ?? null;
        $xml = null;
        $xmlResponse = $this->http()
            ->acceptJson()
            ->timeout(15)
            ->get($this->baseUrl . '/cfdi/xml/issued/' . $cfdiId);
        if ($xmlResponse->successful()) {
            $fileData = $xmlResponse->json();
            $content = $fileData['Content'] ?? null;
            if ($content) {
                $xml = base64_decode($content, true);
                if ($xml && !$uuid) {
                    $uuid = $this->extraerUuidDelXml($xml);
                }
            }
        }
        if (!$uuid) {
            $uuid = $data['Folio'] ?? $cfdiId;
        }
        $fechaTimbrado = isset($data['Date']) ? \Carbon\Carbon::parse($data['Date']) : now();

        return [
            'success' => true,
            'uuid' => (string) $uuid,
            'xml' => $xml ?: '',
            'fecha_timbrado' => $fechaTimbrado,
            'message' => 'Complemento de pago timbrado con Facturama correctamente',
        ];
    }

    /**
     * Impuestos proporcionales para un DoctoRelacionado del complemento de pago.
     * Cuando TaxObject es 02 el SAT exige enviar impuestos; se prorratean según monto pagado / total factura.
     *
     * @return array Lista de impuestos en formato Facturama (Name, Base, Rate, Total, IsRetention, IsQuota)
     */
    protected function impuestosParaDoctoRelacionado(?Factura $factura, float $montoPagado): array
    {
        if (!$factura || $montoPagado < 0.01) {
            return [];
        }
        $totalFactura = (float) $factura->total;
        if ($totalFactura < 0.01) {
            return [];
        }
        $proporcion = $montoPagado / $totalFactura;

        $impuestosAgrupados = [];
        foreach ($factura->detalles ?? [] as $detalle) {
            foreach ($detalle->impuestos ?? [] as $imp) {
                $key = ($imp->tipo ?? 'traslado') . '-' . ($imp->impuesto ?? '002');
                if (!isset($impuestosAgrupados[$key])) {
                    $impuestosAgrupados[$key] = [
                        'tipo' => $imp->tipo ?? 'traslado',
                        'impuesto' => $imp->impuesto ?? '002',
                        'base' => 0.0,
                        'importe' => 0.0,
                        'tasa_o_cuota' => (float) ($imp->tasa_o_cuota ?? 0),
                        'tipo_factor' => $imp->tipo_factor ?? 'Tasa',
                    ];
                }
                $impuestosAgrupados[$key]['base'] += (float) $imp->base;
                $impuestosAgrupados[$key]['importe'] += (float) $imp->importe;
            }
        }
        // Si la factura no tiene impuestos (objeto 01), no inventar; el DoctoRelacionado usará TaxObject 01 sin Taxes
        if (empty($impuestosAgrupados)) {
            return [];
        }
        $taxes = [];
        foreach ($impuestosAgrupados as $row) {
            $base = round($row['base'] * $proporcion, 2);
            if ($base < 0.01) {
                $base = 0.01;
            }
            $rate = round((float) $row['tasa_o_cuota'], 6);
            $esCuota = ($row['tipo_factor'] ?? 'Tasa') === 'Cuota';
            // Facturama exige: Total = Base * Rate (para Tasa). Usar Total = round(Base * Rate, 2) para evitar "Total incorrecto Base por Rate debe ser igual al Total".
            $total = $esCuota
                ? round($row['importe'] * $proporcion, 2)
                : round($base * $rate, 2);
            $impuesto = $row['impuesto'] ?? '002';
            $tipo = $row['tipo'] ?? 'traslado';
            $name = \App\Models\FacturaImpuesto::nombreParaFacturama($impuesto, $tipo, $row['tipo_factor'] ?? null);
            $taxes[] = [
                'Name' => $name,
                'Base' => $base,
                'Rate' => $rate,
                'Total' => $total,
                'IsRetention' => ($row['tipo'] ?? '') === 'retencion',
                'IsQuota' => $esCuota,
            ];
        }
        return $taxes;
    }

    /**
     * Cancelar CFDI en Facturama.
     * DELETE /cfdi/{id}?type=issued&motive={01|02|03|04}&uuidReplacement={uuid}
     *
     * @param string $uuid UUID del CFDI a cancelar
     * @param string $motivo 01|02|03|04 (SAT)
     * @param string|null $uuidSustitucion UUID del comprobante que sustituye (opcional)
     * @param string|null $pacCfdiId Id del CFDI en Facturama (si no se tiene, se intenta buscar por keyword)
     * @return array ['success' => bool, 'message' => string, 'acuse' => string|null]
     */
    public function cancelarFactura(string $uuid, string $motivo, ?string $uuidSustitucion = null, ?string $pacCfdiId = null): array
    {
        $cfdiId = $pacCfdiId;
        if ($cfdiId === null || $cfdiId === '') {
            $cfdiId = $this->obtenerCfdiIdPorUuid($uuid);
            if ($cfdiId === null) {
                return [
                    'success' => false,
                    'message' => 'No se encontró el CFDI en Facturama para el UUID indicado. Solo se puede cancelar facturas timbradas con Facturama.',
                ];
            }
        }

        $type = 'issued';
        $params = [
            'type' => $type,
            'motive' => in_array($motivo, ['01', '02', '03', '04'], true) ? $motivo : '02',
        ];
        if ($uuidSustitucion !== null && $uuidSustitucion !== '') {
            $params['uuidReplacement'] = $uuidSustitucion;
        }

        $url = $this->baseUrl . '/cfdi/' . $cfdiId . '?' . http_build_query($params);

        $response = $this->http()
            ->acceptJson()
            ->timeout(30)
            ->delete($url);

        if (!$response->successful()) {
            $body = $response->json();
            $message = is_array($body) ? ($body['Message'] ?? $body['message'] ?? $response->body()) : $response->body();
            if (is_array($message)) {
                $message = json_encode($message);
            }
            Log::warning('Facturama cancelación fallida', [
                'uuid' => $uuid,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            return [
                'success' => false,
                'message' => 'Facturama: ' . trim((string) $message),
            ];
        }

        $data = $response->json();
        $acuse = $data['AcuseXmlBase64'] ?? null;
        $codigoEstatus = $acuse ? self::extraerCodigoEstatusDelAcuse($acuse) : '201';

        return [
            'success' => true,
            'message' => 'Factura cancelada en el SAT correctamente.',
            'acuse' => $acuse,
            'codigo_estatus' => $codigoEstatus,
        ];
    }

    /**
     * Extrae el código de estatus SAT del acuse de cancelación (XML base64).
     * Estructura SAT: Folios/EstatusUUID o CodigoEstatus.
     *
     * @param string $acuseBase64 XML del acuse en base64
     * @return string Código (201, 202, 601, etc.) o '201' por defecto
     */
    public static function extraerCodigoEstatusDelAcuse(string $acuseBase64): string
    {
        $xml = @base64_decode($acuseBase64, true);
        if ($xml === false || trim($xml) === '') {
            return '201';
        }
        $prev = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        if (!$dom->loadXML($xml)) {
            libxml_clear_errors();
            libxml_use_internal_errors($prev);
            return '201';
        }
        libxml_clear_errors();
        libxml_use_internal_errors($prev);

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('cfdi', 'http://cancelacfd.sat.gob.mx');
        $xpath->registerNamespace('', 'http://cancelacfd.sat.gob.mx');

        $nodes = $xpath->query('//*[local-name()="EstatusUUID"]');
        if ($nodes->length > 0 && trim($nodes->item(0)->textContent) !== '') {
            return trim($nodes->item(0)->textContent);
        }
        $nodes = $xpath->query('//*[local-name()="CodigoEstatus"]');
        if ($nodes->length > 0 && trim($nodes->item(0)->textContent) !== '') {
            return trim($nodes->item(0)->textContent);
        }
        $nodes = $xpath->query('//*[local-name()="estatusuuid"]');
        if ($nodes->length > 0 && trim($nodes->item(0)->textContent) !== '') {
            return trim($nodes->item(0)->textContent);
        }
        return '201';
    }

    /**
     * Obtener el Id del CFDI en Facturama buscando por UUID (keyword).
     * GET /cfdi/{type}?keyword={uuid}&status=active&invoiceType=issued&page=1
     */
    protected function obtenerCfdiIdPorUuid(string $uuid): ?string
    {
        $url = $this->baseUrl . '/cfdi/issued?keyword=' . urlencode($uuid) . '&status=active&invoiceType=issued&page=1';
        $response = $this->http()
            ->acceptJson()
            ->timeout(15)
            ->get($url);

        if (!$response->successful()) {
            return null;
        }

        $list = $response->json();
        if (!is_array($list)) {
            return null;
        }

        foreach ($list as $item) {
            $itemUuid = $item['Uuid'] ?? $item['uuid'] ?? null;
            if ($itemUuid !== null && strcasecmp((string) $itemUuid, $uuid) === 0) {
                return (string) ($item['Id'] ?? $item['id'] ?? null);
            }
        }
        return null;
    }

    /**
     * Normalizar código postal a 5 dígitos (SAT México).
     */
    /**
     * Validar que el cuerpo tenga los datos mínimos que Facturama exige.
     */
    protected function validarCuerpoAntesDeEnviar(array $body): ?string
    {
        if (empty($body['Receiver']['Rfc'])) {
            return 'RFC del receptor es obligatorio.';
        }
        if (empty($body['Receiver']['Name'])) {
            return 'Nombre del receptor es obligatorio.';
        }
        if (empty($body['Receiver']['CfdiUse']) || !preg_match('/^[A-Z0-9]{3}$/', $body['Receiver']['CfdiUse'])) {
            return 'Uso de CFDI del receptor debe ser una clave de 3 caracteres (ej. G03, P01).';
        }
        if (empty($body['ExpeditionPlace']) || strlen($body['ExpeditionPlace']) !== 5) {
            return 'Lugar de expedición debe ser un código postal de 5 dígitos. Revisa la configuración de la empresa.';
        }
        if (empty($body['Receiver']['TaxZipCode']) || strlen($body['Receiver']['TaxZipCode']) !== 5) {
            return 'Código postal del receptor es obligatorio (5 dígitos). Revisa los datos del cliente.';
        }
        if (empty($body['Items'])) {
            return 'La factura debe tener al menos un concepto.';
        }
        foreach ($body['Items'] as $i => $item) {
            if (empty($item['Description'])) {
                return 'El concepto ' . ($i + 1) . ' debe tener descripción.';
            }
            if (($item['Quantity'] ?? 0) <= 0) {
                return 'El concepto ' . ($i + 1) . ' debe tener cantidad mayor a 0.';
            }
        }
        return null;
    }

    /**
     * Normalizar código postal a 5 dígitos (SAT México).
     */
    protected function normalizarCodigoPostal(?string $cp): string
    {
        $cp = preg_replace('/\D/', '', (string) $cp);
        if (strlen($cp) >= 5) {
            return substr($cp, 0, 5);
        }
        return str_pad($cp, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Construir el cuerpo JSON para POST /3/cfdis (formato Facturama API Web).
     * Validaciones según documentación: https://apisandbox.facturama.mx/docs/api/POST-3-cfdis
     */
    protected function buildCfdiBody(Factura $factura): array
    {
        $empresa = $factura->empresa ?? Empresa::principal();

        $items = [];
        foreach ($factura->detalles as $d) {
            $subtotal = (float) $d->importe;
            $descuento = (float) ($d->descuento ?? 0);
            $taxes = [];
            $impuestosTotal = 0;
            $tieneImpuestos = $d->impuestos && $d->impuestos->isNotEmpty();
            foreach ($d->impuestos ?? [] as $imp) {
                $monto = (float) $imp->importe;
                $impuestosTotal += ($imp->tipo ?? 'traslado') === 'retencion' ? -$monto : $monto;
                $nombre = \App\Models\FacturaImpuesto::nombreParaFacturama(
                    $imp->impuesto ?? '002',
                    $imp->tipo ?? 'traslado',
                    $imp->tipo_factor ?? null
                );
                $taxes[] = [
                    'Name' => $nombre,
                    'Base' => round((float) $imp->base, 2),
                    'Rate' => round((float) $imp->tasa_o_cuota, 6),
                    'Total' => round((float) $imp->importe, 2),
                    'IsRetention' => $imp->tipo === 'retencion',
                    'IsQuota' => ($imp->tipo_factor ?? 'Tasa') === 'Cuota',
                    'TaxObject' => $d->objeto_impuesto ?? '02',
                ];
            }
            // Total del concepto = Subtotal - Descuento + Impuestos (Facturama/SAT)
            $totalLinea = round($subtotal - $descuento + $impuestosTotal, 2);
            $objetoImpuesto = $d->objeto_impuesto ?? ($tieneImpuestos ? '02' : '01');
            $items[] = [
                'ProductCode' => $d->clave_prod_serv ?: '01010101',
                'IdentificationNumber' => trim((string) ($d->no_identificacion ?? '')) !== '' ? $d->no_identificacion : 'N/A',
                'Description' => $d->descripcion,
                'Unit' => $d->unidad ?? 'Pieza',
                'UnitCode' => $d->clave_unidad ?: 'H87',
                'UnitPrice' => round((float) $d->valor_unitario, 6),
                'Quantity' => (float) $d->cantidad,
                'Subtotal' => round($subtotal, 2),
                'Discount' => round($descuento, 2),
                'TaxObject' => $objetoImpuesto,
                'Taxes' => $taxes,
                'Total' => $totalLinea,
            ];
        }

        $lugarExp = $this->normalizarCodigoPostal($factura->lugar_expedicion ?: $empresa->codigo_postal ?? null);
        if ($lugarExp === '00000') {
            $lugarExp = '01000';
        }

        $cpReceptor = $this->normalizarCodigoPostal($factura->domicilio_fiscal_receptor ?? null);
        if ($cpReceptor === '00000') {
            $cpReceptor = '01000';
        }

        $receiver = [
            'Rfc' => trim($factura->rfc_receptor),
            'Name' => trim($factura->nombre_receptor),
            'CfdiUse' => $factura->uso_cfdi ?? 'G03',
            'FiscalRegime' => preg_match('/^\d{3}$/', (string) ($factura->regimen_fiscal_receptor ?? '')) ? $factura->regimen_fiscal_receptor : '616',
            'TaxZipCode' => $cpReceptor,
        ];

        $folio = is_numeric($factura->folio) ? (string) $factura->folio : (string) $factura->folio;
        $serie = trim($factura->serie ?? 'A');
        if ($serie === '') {
            $serie = 'A';
        }
        $subtotalDoc = round((float) $factura->subtotal, 2);
        $descuentoDoc = round((float) ($factura->descuento ?? 0), 2);
        $totalDoc = round((float) $factura->total, 2);

        $paymentConditions = $factura->metodo_pago === 'PPD' ? 'CRÉDITO' : 'CONTADO';
        $paymentConditions = mb_substr($paymentConditions, 0, 100);

        // Cuando el método de pago es PPD, Facturama exige Forma de pago = 99 (Por definir)
        $formaPago = $factura->forma_pago ?? '03';
        if (($factura->metodo_pago ?? '') === 'PPD') {
            $formaPago = '99';
        } elseif (strlen($formaPago) !== 2) {
            $formaPago = '03';
        }

        $body = [
            'NameId' => 1,
            'Date' => $factura->fecha_emision->format('Y-m-d\TH:i:s'),
            'Serie' => $serie,
            'Folio' => $folio,
            'CfdiType' => $factura->tipo_comprobante ?? 'I',
            'PaymentForm' => $formaPago,
            'PaymentMethod' => in_array($factura->metodo_pago, ['PUE', 'PPD'], true) ? $factura->metodo_pago : 'PUE',
            'ExpeditionPlace' => $lugarExp,
            'Currency' => $factura->moneda ?? 'MXN',
            'CurrencyExchangeRate' => round((float) ($factura->tipo_cambio ?? 1), 6),
            'Exportation' => '01',
            'PaymentConditions' => $paymentConditions,
            'Subtotal' => $subtotalDoc,
            'Discount' => $descuentoDoc,
            'Total' => $totalDoc,
            'Receiver' => $receiver,
            'Items' => $items,
        ];

        if (trim((string) ($factura->observaciones ?? '')) !== '') {
            $body['Observations'] = mb_substr(trim($factura->observaciones), 0, 1000);
        }

        // CFDI 4.0: cuando el receptor es público en general (RFC genérico), el SAT exige el nodo GlobalInformation
        if ($this->esReceptorPublicoEnGeneral($body['Receiver']['Rfc'] ?? '', $body['Receiver']['Name'] ?? '')) {
            $body['GlobalInformation'] = $this->buildGlobalInformation($factura->fecha_emision);
        }

        return $body;
    }

    /**
     * Indica si el receptor es "Público en general" (RFC genérico XAXX010101000).
     * En ese caso el SAT exige el nodo GlobalInformation en el CFDI 4.0.
     */
    protected function esReceptorPublicoEnGeneral(string $rfc, string $nombre): bool
    {
        $rfc = strtoupper(trim($rfc));
        $nombre = strtoupper(trim($nombre));
        return $rfc === 'XAXX010101000' && $nombre === 'PUBLICO EN GENERAL';
    }

    /**
     * Construye el nodo GlobalInformation requerido para factura al público en general (CFDI 4.0).
     * Periodicidad: 01=Diario, 02=Semanal, 03=Quincenal, 04=Mensual, 05=Bimestral.
     *
     * @param \Carbon\Carbon|\DateTimeInterface $fechaEmision
     * @return array{Periodicity: string, Months: string, Year: int}
     */
    protected function buildGlobalInformation($fechaEmision): array
    {
        $fecha = $fechaEmision instanceof \Carbon\Carbon
            ? $fechaEmision
            : \Carbon\Carbon::parse($fechaEmision);
        $mes = $fecha->format('m'); // 01-12
        $anio = (int) $fecha->format('Y');
        return [
            'Periodicity' => '04', // Mensual (común para factura global)
            'Months' => $mes,
            'Year' => $anio,
        ];
    }

    /**
     * Timbrar nota de crédito (CFDI tipo E) en Facturama.
     */
    public function timbrarNotaCredito(NotaCredito $notaCredito): array
    {
        $notaCredito->load(['detalles.impuestos', 'empresa', 'factura']);
        $empresa = $notaCredito->empresa ?? Empresa::principal();

        $items = [];
        foreach ($notaCredito->detalles as $d) {
            $subtotal = (float) $d->importe;
            $descuento = (float) ($d->descuento ?? 0);
            $taxes = [];
            $impuestosTotal = 0;
            foreach ($d->impuestos ?? [] as $imp) {
                $monto = (float) $imp->importe;
                $impuestosTotal += ($imp->tipo ?? 'traslado') === 'retencion' ? -$monto : $monto;
                $nombre = \App\Models\FacturaImpuesto::nombreParaFacturama(
                    $imp->impuesto ?? '002',
                    $imp->tipo ?? 'traslado',
                    $imp->tipo_factor ?? null
                );
                $taxes[] = [
                    'Name' => $nombre,
                    'Base' => round((float) $imp->base, 2),
                    'Rate' => round((float) $imp->tasa_o_cuota, 6),
                    'Total' => round((float) $imp->importe, 2),
                    'IsRetention' => $imp->tipo === 'retencion',
                    'IsQuota' => ($imp->tipo_factor ?? 'Tasa') === 'Cuota',
                    'TaxObject' => $d->objeto_impuesto ?? '02',
                ];
            }
            $totalLinea = round($subtotal - $descuento + $impuestosTotal, 2);
            $objetoImpuesto = $d->objeto_impuesto ?? '02';
            $items[] = [
                'ProductCode' => $d->clave_prod_serv ?: '01010101',
                'IdentificationNumber' => trim((string) ($d->no_identificacion ?? '')) !== '' ? $d->no_identificacion : 'N/A',
                'Description' => $d->descripcion,
                'Unit' => $d->unidad ?? 'Pieza',
                'UnitCode' => $d->clave_unidad ?? 'H87',
                'UnitPrice' => round((float) $d->valor_unitario, 6),
                'Quantity' => (float) $d->cantidad,
                'Subtotal' => round($subtotal, 2),
                'Discount' => round($descuento, 2),
                'TaxObject' => $objetoImpuesto,
                'Taxes' => $taxes,
                'Total' => $totalLinea,
            ];
        }

        $lugarExp = $this->normalizarCodigoPostal($notaCredito->lugar_expedicion ?: $empresa->codigo_postal ?? null);
        if ($lugarExp === '00000') {
            $lugarExp = '01000';
        }
        $cpReceptor = $this->normalizarCodigoPostal($notaCredito->domicilio_fiscal_receptor ?? null);
        if ($cpReceptor === '00000') {
            $cpReceptor = '01000';
        }

        $uuidRef = trim((string) $notaCredito->uuid_referencia);
        if ($uuidRef === '') {
            return [
                'success' => false,
                'message' => 'La nota de crédito debe tener el UUID de la factura que se acredita (uuid_referencia).',
            ];
        }

        $formaPagoNc = in_array($notaCredito->forma_pago, ['23', '15', '03'], true) ? $notaCredito->forma_pago : '23';

        $body = [
            'NameId' => 1,
            'Date' => $notaCredito->fecha_emision->format('Y-m-d\TH:i:s'),
            'Serie' => trim($notaCredito->serie ?? 'NC') ?: 'NC',
            'Folio' => (string) $notaCredito->folio,
            'CfdiType' => 'E',
            'PaymentForm' => $formaPagoNc,
            'PaymentMethod' => 'PUE',
            'ExpeditionPlace' => $lugarExp,
            'Currency' => $notaCredito->moneda ?? 'MXN',
            'CurrencyExchangeRate' => round((float) ($notaCredito->tipo_cambio ?? 1), 6),
            'Exportation' => '01',
            'PaymentConditions' => 'CONTADO',
            'Subtotal' => round((float) $notaCredito->subtotal, 2),
            'Discount' => round((float) ($notaCredito->descuento ?? 0), 2),
            'Total' => round((float) $notaCredito->total, 2),
            'Receiver' => [
                'Rfc' => trim($notaCredito->rfc_receptor),
                'Name' => trim($notaCredito->nombre_receptor),
                'CfdiUse' => $notaCredito->uso_cfdi ?? 'G03',
                'FiscalRegime' => preg_match('/^\d{3}$/', (string) ($notaCredito->regimen_fiscal_receptor ?? '')) ? $notaCredito->regimen_fiscal_receptor : '616',
                'TaxZipCode' => $cpReceptor,
            ],
            'Items' => $items,
            'Relations' => [
                'Type' => $notaCredito->tipo_relacion ?? '01',
                'Cfdis' => [['Uuid' => $uuidRef]],
            ],
        ];

        if (trim((string) ($notaCredito->observaciones ?? '')) !== '') {
            $body['Observations'] = mb_substr(trim($notaCredito->observaciones), 0, 1000);
        }

        $response = $this->http()
            ->acceptJson()
            ->timeout(30)
            ->post($this->baseUrl . '/3/cfdis', $body);

        if (!$response->successful()) {
            $rawBody = $response->body();
            $bodyErr = $response->json();
            $message = is_array($bodyErr) ? ($bodyErr['Message'] ?? $bodyErr['message'] ?? $rawBody) : $rawBody;
            $mensajeFinal = is_string($message) ? $message : json_encode($message);
            if (stripos($mensajeFinal, 'Serie') !== false && (stripos($mensajeFinal, 'sucursal') !== false || stripos($mensajeFinal, 'existir') !== false)) {
                $serie = $body['Serie'] ?? 'NC';
                $mensajeFinal = 'La serie "' . $serie . '" debe existir en tu sucursal de Facturama. Entra a Facturama → Perfil fiscal → Lugares de expedición → elige la sucursal (ej. CP ' . ($body['ExpeditionPlace'] ?? '') . ') → Series y crea una serie con el nombre "' . $serie . '" para notas de crédito (o en Configuración de la empresa cambia "Serie nota de crédito" a una que ya tengas en Facturama). ' . $mensajeFinal;
            }
            Log::warning('Facturama nota de crédito fallida', ['status' => $response->status(), 'response' => $rawBody]);
            return [
                'success' => false,
                'message' => 'Facturama: ' . $mensajeFinal,
            ];
        }

        $data = $response->json();
        $cfdiId = $data['Id'] ?? $data['id'] ?? null;
        if (!$cfdiId) {
            return ['success' => false, 'message' => 'Facturama no devolvió el Id del CFDI'];
        }

        $uuid = $data['Uuid'] ?? $data['uuid'] ?? null;
        $xml = null;
        $xmlRes = $this->http()
            ->acceptJson()
            ->timeout(15)
            ->get($this->baseUrl . '/cfdi/xml/issued/' . $cfdiId);
        if ($xmlRes->successful()) {
            $fileData = $xmlRes->json();
            $content = $fileData['Content'] ?? null;
            if ($content) {
                $xml = base64_decode($content, true);
                if ($xml && !$uuid) {
                    $uuid = $this->extraerUuidDelXml($xml);
                }
            }
        }
        if (!$uuid) {
            $uuid = $data['Folio'] ?? $cfdiId;
        }

        return [
            'success' => true,
            'uuid' => (string) $uuid,
            'pac_cfdi_id' => (string) $cfdiId,
            'xml' => $xml ?: '',
            'fecha_timbrado' => isset($data['Date']) ? \Carbon\Carbon::parse($data['Date']) : now(),
            'no_certificado_sat' => $data['NoCertificadoSat'] ?? null,
            'sello_cfdi' => $data['SelloCFDI'] ?? null,
            'sello_sat' => $data['SelloSat'] ?? null,
            'cadena_original' => $data['CadenaOriginal'] ?? null,
            'message' => 'Nota de crédito timbrada con Facturama correctamente',
        ];
    }

    protected function extraerUuidDelXml(string $xml): ?string
    {
        // Priorizar TimbreFiscalDigital: es el UUID de este CFDI (no el de CfdiRelacionado)
        if (preg_match('/<tfd:TimbreFiscalDigital[^>]+UUID="([a-f0-9\-]{36})"/i', $xml, $m)) {
            return $m[1];
        }
        if (preg_match('/TimbreFiscalDigital[^>]+UUID="([a-f0-9\-]{36})"/i', $xml, $m)) {
            return $m[1];
        }
        if (preg_match('/UUID="([a-f0-9\-]{36})"/i', $xml, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * Extrae del XML timbrado los datos del complemento TimbreFiscalDigital para sellos, fecha y cadena original.
     * Orden cadena SAT: Version|UUID|FechaTimbrado|SelloCFDI|NoCertificadoSAT
     *
     * @return array{sello_cfdi: ?string, sello_sat: ?string, fecha_timbrado: ?string, no_certificado_sat: ?string, cadena_original: ?string}
     */
    protected function extraerTimbreDelXml(string $xml): array
    {
        $out = ['sello_cfdi' => null, 'sello_sat' => null, 'fecha_timbrado' => null, 'no_certificado_sat' => null, 'cadena_original' => null];
        // Atributos pueden venir con namespace o sin; nombres en mayúscula o mixto según PAC
        if (!preg_match('/<(?:\w+:)?TimbreFiscalDigital\s+([^>]+)>/i', $xml, $tag)) {
            return $out;
        }
        $attrs = $tag[1];
        $get = function (string $name) use ($attrs): ?string {
            if (preg_match('/\s' . preg_quote($name, '/') . '="([^"]*)"/i', $attrs, $m)) {
                return $m[1] !== '' ? $m[1] : null;
            }
            $alt = str_replace(['CFDI', 'SAT'], ['Cfdi', 'Sat'], $name);
            if (preg_match('/\s' . preg_quote($alt, '/') . '="([^"]*)"/i', $attrs, $m)) {
                return $m[1] !== '' ? $m[1] : null;
            }
            return null;
        };
        $version = $get('Version') ?? $get('version') ?? '1.1';
        $uuid = $get('UUID') ?? $get('Uuid') ?? '';
        $fechaTimbrado = $get('FechaTimbrado') ?? $get('FechaTimbrado');
        $selloCfdi = $get('SelloCFDI') ?? $get('SelloCFD') ?? $get('SelloCfdi');
        $noCertSat = $get('NoCertificadoSAT') ?? $get('NoCertificadoSat');
        $selloSat = $get('SelloSAT') ?? $get('SelloSat');

        $out['sello_cfdi'] = $selloCfdi;
        $out['sello_sat'] = $selloSat;
        $out['fecha_timbrado'] = $fechaTimbrado;
        $out['no_certificado_sat'] = $noCertSat;

        if ($version && $uuid && $fechaTimbrado && $selloCfdi && $noCertSat) {
            $out['cadena_original'] = implode('|', [$version, $uuid, $fechaTimbrado, $selloCfdi, $noCertSat]);
        }

        return $out;
    }
}
