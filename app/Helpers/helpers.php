<?php

// UBICACIÓN: app/Helpers/helpers.php
//
// Este archivo contiene funciones globales que puedes usar en toda la app

if (!function_exists('importeEnLetra')) {
    /**
     * Convertir importe numérico a letra (formato fiscal México)
     * Ej: 100.34 -> "CIEN PESOS 34/100 M.N."
     */
    function importeEnLetra(float $numero): string
    {
        $parteEntera = (int) floor($numero);
        $centavos = (int) round(($numero - $parteEntera) * 100);
        if ($centavos >= 100) {
            $centavos = 0;
            $parteEntera++;
        }
        $letras = 'CERO';
        if ($parteEntera > 0) {
            if (class_exists(\NumberFormatter::class)) {
                $fmt = new \NumberFormatter('es', \NumberFormatter::SPELLOUT);
                $letras = $fmt->format($parteEntera);
            } else {
                $letras = numeroALetra($parteEntera);
            }
        }
        $centStr = str_pad((string) $centavos, 2, '0', STR_PAD_LEFT);
        return strtoupper($letras . ' PESOS ' . $centStr . '/100 M.N.');
    }
}
if (!function_exists('numeroALetra')) {
    /** Convertir número entero a letras (fallback sin Intl) */
    function numeroALetra(int $n): string
    {
        if ($n === 0) return 'cero';
        $u = ['', 'uno', 'dos', 'tres', 'cuatro', 'cinco', 'seis', 'siete', 'ocho', 'nueve'];
        $e = ['diez', 'once', 'doce', 'trece', 'catorce', 'quince', 'dieciséis', 'diecisiete', 'dieciocho', 'diecinueve'];
        $d = ['', '', 'veinte', 'treinta', 'cuarenta', 'cincuenta', 'sesenta', 'setenta', 'ochenta', 'noventa'];
        $c = ['', 'ciento', 'doscientos', 'trescientos', 'cuatrocientos', 'quinientos', 'seiscientos', 'setecientos', 'ochocientos', 'novecientos'];
        if ($n === 100) return 'cien';
        if ($n < 10) return $u[$n];
        if ($n < 20) return $e[$n - 10];
        if ($n < 100) {
            $dd = (int)($n / 10); $uu = $n % 10;
            if ($dd === 2 && $uu > 0) return 'veinti' . $u[$uu];
            return $d[$dd] . ($uu > 0 ? ' y ' . $u[$uu] : '');
        }
        if ($n < 1000) {
            $cc = (int)($n / 100); $r = $n % 100;
            return $c[$cc] . ($r > 0 ? ' ' . numeroALetra($r) : '');
        }
        if ($n < 1000000) {
            $m = (int)($n / 1000); $r = $n % 1000;
            $t = $m === 1 ? 'mil' : numeroALetra($m) . ' mil';
            return $t . ($r > 0 ? ' ' . numeroALetra($r) : '');
        }
        if ($n < 1000000000) {
            $mm = (int)($n / 1000000); $r = $n % 1000000;
            $t = $mm === 1 ? 'un millón' : numeroALetra($mm) . ' millones';
            return $t . ($r > 0 ? ' ' . numeroALetra($r) : '');
        }
        return (string) $n;
    }
}
if (!function_exists('formatMoney')) {
    /**
     * Formatear cantidad como moneda MXN
     */
    function formatMoney($amount)
    {
        return '$' . number_format($amount ?? 0, 2, '.', ',');
    }
}

if (!function_exists('formatDate')) {
    /**
     * Formatear fecha en español
     */
    function formatDate($date, $format = 'd/m/Y')
    {
        if (!$date) return '-';
        
        if (is_string($date)) {
            $date = \Carbon\Carbon::parse($date);
        }
        
        return $date->format($format);
    }
}

if (!function_exists('formatDateTime')) {
    /**
     * Formatear fecha y hora en español
     */
    function formatDateTime($date)
    {
        return formatDate($date, 'd/m/Y H:i');
    }
}

if (!function_exists('cleanRFC')) {
    /**
     * Limpiar RFC (mayúsculas, sin espacios)
     */
    function cleanRFC($rfc)
    {
        return strtoupper(trim(str_replace(' ', '', $rfc)));
    }
}

if (!function_exists('percentFormat')) {
    /**
     * Formatear porcentaje
     */
    function percentFormat($value, $decimals = 2)
    {
        return number_format($value, $decimals) . '%';
    }
}

if (!function_exists('urlVerificacionSat')) {
    /**
     * Generar URL de verificación SAT para CFDI
     * Parámetros: id=UUID, re=RFC emisor, rr=RFC receptor, tt=Total, fe=últimos 8 del sello
     */
    function urlVerificacionSat(string $uuid, string $rfcEmisor, string $rfcReceptor, float $total, ?string $selloCfdi = null): string
    {
        // SAT requiere exactamente 6 decimales
        $tt = number_format($total, 6, '.', '');

        // Últimos 8 chars del sello en base64, los = NO deben ir url-encodeados
        $fe = $selloCfdi ? substr($selloCfdi, -8) : '';

        // Construir manualmente para evitar que http_build_query encodee los = del base64
        return 'https://verificacfdi.facturaelectronica.sat.gob.mx/default.aspx'
            . '?id='  . urlencode($uuid)
            . '&re=' . urlencode($rfcEmisor)
            . '&rr=' . urlencode($rfcReceptor)
            . '&tt=' . $tt
            . '&fe=' . $fe;
    }
}

if (!function_exists('qrCodeDataUri')) {
    /**
     * Generar QR code como data URI para incrustar en HTML/PDF
     */
    function qrCodeDataUri(string $data, int $size = 80): ?string
    {
        try {
            $builder = new \Endroid\QrCode\Builder\Builder(
                data: $data,
                size: $size,
                margin: 2,
            );
            $result = $builder->build();
            return $result->getDataUri();
        } catch (\Throwable $e) {
            \Log::error('qrCodeDataUri error: ' . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('parseCertificadoCer')) {
    /**
     * Extraer no_certificado (serie) y fecha de vigencia de un archivo .cer (DER o PEM)
     * @return array{no_certificado: ?string, certificado_vigencia: ?string} Fecha en Y-m-d
     */
    function parseCertificadoCer(string $storagePath): array
    {
        try {
            $fullPath = \Illuminate\Support\Facades\Storage::disk('local')->path($storagePath);
            if (!is_readable($fullPath)) {
                return ['no_certificado' => null, 'certificado_vigencia' => null];
            }
            $content = file_get_contents($fullPath);
            if ($content === false || $content === '') {
                return ['no_certificado' => null, 'certificado_vigencia' => null];
            }
            $pem = $content;
            if (strpos($content, '-----BEGIN CERTIFICATE-----') === false) {
                $b64 = base64_encode($content);
                $pem = "-----BEGIN CERTIFICATE-----\n" . chunk_split($b64, 64, "\n") . "-----END CERTIFICATE-----";
            }
            $cert = openssl_x509_read($pem);
            if ($cert === false) {
                return ['no_certificado' => null, 'certificado_vigencia' => null];
            }
            $parsed = openssl_x509_parse($cert);
            openssl_x509_free($cert);
            if (!$parsed) {
                return ['no_certificado' => null, 'certificado_vigencia' => null];
            }
            $serial = null;
            if (!empty($parsed['serialNumberHex'])) {
                $serial = strtoupper(ltrim($parsed['serialNumberHex'], '0'));
                if (strlen($serial) > 20) {
                    $serial = substr($serial, -20);
                } elseif (strlen($serial) < 20) {
                    $serial = str_pad($serial, 20, '0', STR_PAD_LEFT);
                }
            } elseif (!empty($parsed['serialNumber'])) {
                $hex = strtoupper(dechex((int) $parsed['serialNumber']));
                $serial = str_pad($hex, 20, '0', STR_PAD_LEFT);
                if (strlen($serial) > 20) {
                    $serial = substr($serial, -20);
                }
            }
            $vigencia = null;
            if (!empty($parsed['validTo_time_t'])) {
                $vigencia = date('Y-m-d', $parsed['validTo_time_t']);
            }
            return ['no_certificado' => $serial, 'certificado_vigencia' => $vigencia];
        } catch (\Throwable $e) {
            return ['no_certificado' => null, 'certificado_vigencia' => null];
        }
    }
}