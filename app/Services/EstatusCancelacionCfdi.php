<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Catálogo y textos de cancelación fiscal (PAC/SAT).
 * El código 201 solo se muestra si el PAC lo devolvió; nunca se inventa.
 */
class EstatusCancelacionCfdi
{
    public static function descripcionCodigo(?string $codigo): string
    {
        $map = [
            'ADM' => 'Cancelación administrativa en ERP (pendiente de envío al SAT)',
            '201' => 'Solicitud de cancelación recibida (el CFDI puede seguir vigente)',
            '202' => 'UUID previamente cancelado',
            '203' => 'UUID no corresponde al emisor',
            '204' => 'UUID no aplicable para cancelación',
            '205' => 'UUID no existe en el SAT',
            '206' => 'UUID no corresponde a un CFDI del sector primario',
            '207' => 'No hay comprobante de sustitución válido',
            '208' => 'Folio de sustitución no válido',
            '209' => 'Folio de sustitución no requerido',
            '210' => 'Fecha de solicitud posterior a la declaración',
            '211' => 'Fuera del plazo de factura global',
            '212' => 'Relación inválida o inexistente',
            '213' => 'Rechazada por el receptor',
            '301' => 'Sello inválido',
            '302' => 'Certificado revocado o caduco',
            '310' => 'CSD no válido',
            '311' => 'Clave de motivo de cancelación inválida',
            '312' => 'El UUID no está relacionado según el motivo',
            '401' => 'Fecha fuera de rango',
            '601' => 'No cancelable',
        ];
        $cod = (string) $codigo;
        if ($cod === '') {
            return 'Sin código SAT todavía';
        }
        if (str_starts_with($cod, 'R-')) {
            $num = substr($cod, 2);

            return ($map[$num] ?? $num).' (error / rechazada)';
        }
        if (str_starts_with($cod, 'R') || str_starts_with($cod, 'Rechazada')) {
            return 'Rechazada';
        }

        return $map[$cod] ?? $cod;
    }

    public static function normalizarStatusPac(?string $status): ?string
    {
        $status = strtolower(trim((string) $status));
        if ($status === '') {
            return null;
        }
        if (in_array($status, ['cancelled', 'canceled'], true)) {
            return 'canceled';
        }
        if (in_array($status, ['pending', 'requested'], true)) {
            return 'pending';
        }
        if (in_array($status, ['rejected', 'reject'], true)) {
            return 'rejected';
        }
        if (in_array($status, ['active', 'expired', 'acepted', 'accepted'], true)) {
            return $status === 'acepted' ? 'accepted' : $status;
        }

        return $status;
    }

    public static function normalizarEstatusSat(?string $estatus): ?string
    {
        $estatus = trim((string) $estatus);
        if ($estatus === '') {
            return null;
        }
        $lower = mb_strtolower($estatus);
        if (str_contains($lower, 'cancel')) {
            return 'Cancelado';
        }
        if (str_contains($lower, 'vigente') || str_contains($lower, 'current')) {
            return 'Vigente';
        }
        if (str_contains($lower, 'no encontrado') || str_contains($lower, 'not found')) {
            return 'No Encontrado';
        }
        if (str_contains($lower, 'pendiente')) {
            return 'Pendiente';
        }

        return $estatus;
    }

    public static function esPendientePac(?string $statusPac): bool
    {
        return self::normalizarStatusPac($statusPac) === 'pending';
    }

    public static function esCanceladaSat(?string $statusPac, ?string $estatusSat, ?string $codigo = null): bool
    {
        if (self::normalizarStatusPac($statusPac) === 'canceled') {
            return true;
        }
        if (self::normalizarEstatusSat($estatusSat) === 'Cancelado') {
            return true;
        }

        return false;
    }

    public static function estatusSatParaUsuario(?string $statusPac, ?string $estatusSat, ?string $codigo = null): string
    {
        if (self::esCanceladaSat($statusPac, $estatusSat, $codigo)) {
            return 'Cancelado';
        }

        return self::normalizarEstatusSat($estatusSat) ?? 'Sin consultar';
    }

    public static function esRechazada(?string $statusPac, ?string $codigo = null): bool
    {
        if (self::normalizarStatusPac($statusPac) === 'rejected') {
            return true;
        }
        $cod = (string) $codigo;

        return $cod === '213' || str_starts_with($cod, 'R-213');
    }

    public static function solicitudAceptadaPorPac(?string $statusPac): bool
    {
        $status = self::normalizarStatusPac($statusPac);

        return in_array($status, ['canceled', 'pending'], true);
    }

    /**
     * @param  array{status_pac?: ?string, estatus_sat?: ?string, codigo_estatus?: ?string, mensaje_pac?: ?string, is_cancelable?: ?string, expiration_date?: ?string, acuse?: ?string, solicitud_aceptada?: bool}  $resultado
     */
    public static function mensajeUsuario(array $resultado, bool $esAdmin = false): string
    {
        $statusPac = self::normalizarStatusPac($resultado['status_pac'] ?? null);
        $estatusSat = self::normalizarEstatusSat($resultado['estatus_sat'] ?? null);
        $codigo = $resultado['codigo_estatus'] ?? null;
        $mensajePac = trim((string) ($resultado['mensaje_pac'] ?? ''));
        $expira = self::formatearFecha($resultado['expiration_date'] ?? null);
        $adminOps = $esAdmin
            ? ' La cancelación administrativa del ERP permanece registrada sin duplicar movimientos.'
            : '';

        if (self::esCanceladaSat($statusPac, $estatusSat, $codigo)) {
            if ($estatusSat === 'Vigente') {
                return 'La cancelación fue confirmada por el PAC, pero la consulta directa aún reporta el CFDI vigente. Consulte de nuevo más tarde.'.$adminOps;
            }

            $codigoTxt = (string) $codigo === '202'
                ? ' El código 202 indica que el UUID ya se encontraba cancelado.'
                : '';

            return 'Cancelación confirmada ante el SAT.'.$codigoTxt.$adminOps;
        }

        if ($statusPac === 'pending') {
            $plazo = $expira ? ' El receptor tiene hasta el '.$expira.' para aceptar o rechazar.' : ' El receptor tiene hasta 72 horas para aceptar o rechazar.';

            return 'La solicitud de cancelación sí se envió al SAT. El CFDI todavía está vigente.'.$plazo.' Si no responde, se cancelará sola.'.$adminOps;
        }

        if (self::esRechazada($statusPac, $codigo)) {
            $base = 'El receptor rechazó la cancelación. El CFDI sigue vigente ante el SAT.';
            if ($esAdmin) {
                return $base.' El ERP permanece cancelado administrativamente (el stock ya pudo usarse al facturar con relación). Puede reintentar el envío al SAT.';
            }

            return $base.' Se restauró el estado operativo de la factura (inventario y saldo).';
        }

        if ($statusPac === 'active') {
            $extra = $mensajePac !== '' ? ' '.$mensajePac : '';
            $cancelable = trim((string) ($resultado['is_cancelable'] ?? ''));
            if ($cancelable !== '') {
                $extra .= ' Condición: '.$cancelable.'.';
            }

            return 'Facturama no canceló el CFDI (sigue activo; suele deberse a documentos relacionados o a que no es cancelable).'.$extra;
        }

        if ($estatusSat === 'Vigente' && ! in_array($statusPac, ['pending', 'canceled'], true)) {
            return 'El CFDI sigue vigente ante el SAT. No hay una solicitud de cancelación pendiente en Facturama.'.$adminOps;
        }

        if ($estatusSat === 'No Encontrado') {
            return 'La consulta de estatus no devolvió información para este UUID. Intente de nuevo más tarde.';
        }

        if ($estatusSat === 'Pendiente') {
            return 'El SAT reporta la cancelación en proceso. Consulte de nuevo más tarde.'.$adminOps;
        }

        if ($mensajePac !== '') {
            $codigoTxt = $codigo ? ' Código '.$codigo.' — '.self::descripcionCodigo((string) $codigo).'.' : '';

            return $mensajePac.$codigoTxt.$adminOps;
        }

        if (! empty($resultado['solicitud_aceptada'])) {
            return 'Facturama aceptó la petición, pero no indicó si quedó pendiente o cancelada. Use «Actualizar estatus» para consultar el SAT.'.$adminOps;
        }

        return 'No se obtuvo una respuesta clara del PAC/SAT. Intente de nuevo; no se asume que la factura esté cancelada.';
    }

    public static function etiquetaListado(
        ?string $estado,
        bool $esAdmin,
        ?string $statusPac,
        ?string $estatusSat,
        ?string $codigo,
        bool $pendienteEnvioPac = false
    ): string {
        $statusPac = self::normalizarStatusPac($statusPac);
        if ($estado !== 'cancelada' && $estado !== 'cancelado') {
            return $estado ?? '—';
        }
        if (self::esCanceladaSat($statusPac, $estatusSat, $codigo)) {
            return $esAdmin
                ? 'Cancelada en ERP y ante el SAT'
                : 'Cancelada ante el SAT';
        }
        if ($statusPac === 'pending') {
            return $esAdmin
                ? 'Cancelada en ERP · Pendiente de aceptación SAT'
                : 'Solicitud enviada · Pendiente de aceptación SAT';
        }
        if (self::esRechazada($statusPac, $codigo)) {
            return $esAdmin
                ? 'Cancelada en ERP · SAT vigente (rechazada)'
                : 'Cancelación rechazada · SAT vigente';
        }
        if ($pendienteEnvioPac || ($esAdmin && ($codigo === 'ADM' || $statusPac === null))) {
            return 'Cancelada (Administrativa — ERP)';
        }
        if ($codigo && $codigo !== 'ADM') {
            return 'Cancelada ('.$codigo.')';
        }

        return 'Cancelada';
    }

    public static function descripcionMotivoSat(?string $motivo): string
    {
        return match ((string) $motivo) {
            '01' => '01 — Comprobante emitido con errores con relación',
            '02' => '02 — Comprobante emitido con errores sin relación',
            '03' => '03 — No se llevó a cabo la operación',
            '04' => '04 — Operación nominativa relacionada con factura global',
            default => $motivo ? (string) $motivo : '—',
        };
    }

    /**
     * No degrada un estado PAC más fuerte (canceled > pending > rejected).
     * Si la consulta nueva no trae Status, se conserva el guardado.
     */
    public static function resolverStatusPac(?string $guardado, ?string $nuevo): ?string
    {
        $rank = [
            'canceled' => 40,
            'pending' => 30,
            'rejected' => 20,
            'accepted' => 15,
            'active' => 10,
        ];
        $g = self::normalizarStatusPac($guardado);
        $n = self::normalizarStatusPac($nuevo);
        if ($n === null) {
            return $g;
        }
        if ($g === null) {
            return $n;
        }
        if (($rank[$n] ?? 0) >= ($rank[$g] ?? 0)) {
            return $n;
        }

        return $g;
    }

    public static function formatearFecha(mixed $fecha): ?string
    {
        if ($fecha === null || $fecha === '') {
            return null;
        }
        try {
            return Carbon::parse($fecha)->timezone(config('app.timezone'))->format('d/m/Y H:i');
        } catch (\Throwable) {
            return is_string($fecha) ? $fecha : null;
        }
    }
}
