<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * Catálogo y textos de cancelación fiscal (PAC/SAT).
 * El código 201 solo se muestra si el PAC lo devolvió; nunca se inventa.
 * El 201 confirma recepción de la solicitud ante el SAT; el estado final
 * lo definen Status PAC, Message e IsCancelable / consulta SAT.
 */
class EstatusCancelacionCfdi
{
    public static function descripcionCodigo(?string $codigo): string
    {
        $map = [
            'ADM' => 'Cancelación administrativa en ERP (pendiente de envío al SAT)',
            '201' => 'Solicitud de cancelación recibida por el SAT',
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

    /**
     * Mensajes terminales del PAC/SAT (p. ej. "Cancelado: Cancelado sin aceptación").
     * El código 201 del acuse solo confirma recepción; no niega un Status canceled.
     */
    public static function mensajeIndicaCancelacionTerminal(?string $mensaje): bool
    {
        $mensaje = mb_strtolower(trim((string) $mensaje));
        if ($mensaje === '') {
            return false;
        }

        return str_contains($mensaje, 'cancelado sin acept')
            || str_contains($mensaje, 'cancelado con acept')
            || str_contains($mensaje, 'cancelado plazo vencido')
            || str_contains($mensaje, 'cancelled without')
            || str_contains($mensaje, 'canceled without')
            || str_contains($mensaje, 'cancelled with acceptance')
            || str_contains($mensaje, 'canceled with acceptance');
    }

    public static function esCanceladaSat(
        ?string $statusPac,
        ?string $estatusSat,
        ?string $codigo = null,
        ?string $mensajePac = null
    ): bool {
        $status = self::normalizarStatusPac($statusPac);
        $sat = self::normalizarEstatusSat($estatusSat);
        $codigo = (string) $codigo;

        if ($sat === 'Vigente' || self::esRechazada($status, $codigo)) {
            return false;
        }
        if ($sat === 'Cancelado') {
            return true;
        }

        if (in_array($status, ['accepted', 'expired'], true) || $codigo === '202') {
            return true;
        }

        // Facturama: Status canceled = cierre en el PAC (incluye sin/con aceptación).
        // El 201 del acuse no debe impedir marcar cancelada.
        if ($status === 'canceled') {
            return true;
        }

        return self::mensajeIndicaCancelacionTerminal($mensajePac);
    }

    public static function esPendienteCancelacion(
        ?string $statusPac,
        ?string $estatusSat,
        ?string $codigo = null,
        ?string $mensajePac = null
    ): bool {
        $status = self::normalizarStatusPac($statusPac);
        $sat = self::normalizarEstatusSat($estatusSat);
        $codigo = (string) $codigo;

        if (self::esCanceladaSat($status, $sat, $codigo, $mensajePac)
            || self::esRechazada($status, $codigo)
            || $status === 'active') {
            return false;
        }

        // Desfase raro: PAC ya canceled pero la consulta SAT aún reporta Vigente.
        if ($status === 'canceled' && $sat === 'Vigente') {
            return true;
        }

        // El 201 no decide pendiente: solo confirma que el SAT recibió la solicitud.
        return $status === 'pending' || $sat === 'Pendiente';
    }

    public static function estatusSatParaUsuario(
        ?string $statusPac,
        ?string $estatusSat,
        ?string $codigo = null,
        ?string $mensajePac = null
    ): string {
        if (self::esCanceladaSat($statusPac, $estatusSat, $codigo, $mensajePac)) {
            return 'Cancelado';
        }
        if (self::esPendienteCancelacion($statusPac, $estatusSat, $codigo, $mensajePac)) {
            return self::normalizarEstatusSat($estatusSat) === 'Vigente'
                ? 'Vigente · cancelación pendiente'
                : 'Pendiente de cancelación';
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

        if (self::esCanceladaSat($statusPac, $estatusSat, $codigo, $mensajePac)) {
            $codigoTxt = (string) $codigo === '202'
                ? ' El código 202 indica que el UUID ya se encontraba cancelado.'
                : '';
            $detalleMsg = self::mensajeIndicaCancelacionTerminal($mensajePac) && $mensajePac !== ''
                ? ' ('.$mensajePac.').'
                : '';

            return 'Cancelación confirmada ante el SAT.'.$detalleMsg.$codigoTxt.$adminOps;
        }

        if (self::esPendienteCancelacion($statusPac, $estatusSat, $codigo, $mensajePac)) {
            $plazo = $expira
                ? ' El receptor tiene hasta el '.$expira.' para aceptar o rechazar.'
                : '';
            $cancelable = trim((string) ($resultado['is_cancelable'] ?? ''));
            $cond = $cancelable !== '' ? ' Condición SAT: '.$cancelable.'.' : '';

            return 'La cancelación está en proceso ante el SAT; el CFDI aún no figura como cancelado.'.$plazo.$cond.' Consulte de nuevo hasta obtener la confirmación final.'.$adminOps;
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
        if (self::esPendienteCancelacion($statusPac, $estatusSat, $codigo)) {
            return $esAdmin
                ? 'Cancelada en ERP · Pendiente de cancelación SAT'
                : 'Solicitud enviada · Pendiente de cancelación SAT';
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
