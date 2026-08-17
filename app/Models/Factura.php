<?php

namespace App\Models;

// UBICACIÓN: app/Models/Factura.php

use App\Models\Concerns\HasDesgloseTotalesCfdi;
use App\Services\EstatusCancelacionCfdi;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Factura extends Model
{
    use HasDesgloseTotalesCfdi;
    use HasFactory, SoftDeletes;

    protected $fillable = [
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
        'sello_cfdi',
        'sello_sat',
        'cadena_original',
        'xml_content',
        'xml_path',
        'pdf_path',
        'motivo_cancelacion',
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
    ];

    protected $casts = [
        'fecha_emision' => 'datetime',
        'fecha_timbrado' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'fecha_cancelacion_pac' => 'datetime',
        'fecha_solicitud_cancelacion' => 'datetime',
        'fecha_vencimiento_aceptacion' => 'datetime',
        'cancelacion_administrativa' => 'boolean',
        'cancelacion_administrativa_at' => 'datetime',
        'tipo_cambio' => 'decimal:6',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    /**
     * Saldo acreditable máximo para una nota de crédito directa (sin devolución).
     * PPD: saldo_pendiente_real de la cuenta por cobrar.
     * PUE: total factura menos total de NCs timbradas.
     */
    public function getSaldoAcreditableAttribute(): float
    {
        if ($this->cuentaPorCobrar) {
            return max(0, (float) $this->cuentaPorCobrar->saldo_pendiente_real);
        }
        $ncTotal = (float) NotaCredito::where('factura_id', $this->id)->where('estado', 'timbrada')->sum('total');

        return max(0, (float) $this->total - $ncTotal);
    }

    /**
     * Relación con Cliente
     */
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Relación con Empresa
     */
    public function empresa()
    {
        return $this->belongsTo(Empresa::class);
    }

    // Nota: antes este campo estaba como FK a ordenes_compra, ahora es texto libre (orden_compra).

    /**
     * Relación con Usuario
     */
    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relación con Cotización
     */
    public function cotizacion()
    {
        return $this->belongsTo(Cotizacion::class);
    }

    /**
     * Acuse interno: factura firmada por quien recibe.
     * No forma parte del CFDI ni se incluye en el PDF fiscal.
     */
    public function soporte()
    {
        return $this->hasOne(FacturaSoporte::class);
    }

    public function puedeGestionarSoporte(): bool
    {
        return ! $this->esBorrador();
    }

    /**
     * Remisión que originó esta factura (si aplica). El inventario ya se descontó al marcar la remisión como enviada.
     */
    public function remisionVinculada()
    {
        return $this->hasOne(Remision::class, 'factura_id');
    }

    /**
     * Permite registrar un envío de logística tomando esta factura como documento origen.
     * Misma regla que {@see Remision::permiteNuevoEnvioDesdeElegirOrigen} considerando envíos
     * de esta factura y de la remisión vinculada: sin envíos activos → sí; con envíos activos → solo
     * si hay partidas pendientes en destino y hubo entrega parcial o marcas de entregado en destino.
     * No aplica si la remisión vinculada ya está entregada (trazabilidad por remisión).
     */
    public function permiteNuevoEnvioLogistica(): bool
    {
        $remision = $this->relationLoaded('remisionVinculada')
            ? $this->remisionVinculada
            : $this->remisionVinculada()->first();

        if ($remision && $remision->estado === 'entregada') {
            return false;
        }

        $this->loadMissing('logisticaEnvios');
        $envios = $this->relationLoaded('logisticaEnvios')
            ? $this->logisticaEnvios
            : $this->logisticaEnvios()->get();

        if ($remision) {
            $remision->loadMissing('logisticaEnvios');
            $envios = $envios->concat($remision->logisticaEnvios);
        }

        $enviosActivos = $envios->unique('id')->filter(fn ($e) => $e->estado !== 'cancelado');

        if ($enviosActivos->isEmpty()) {
            return true;
        }

        if (! $this->tienePartidasPendientesDeEnvioLogistica()) {
            return false;
        }

        if ($enviosActivos->contains('estado', 'entrega_parcial')) {
            return true;
        }

        foreach ($enviosActivos as $envio) {
            $items = $envio->relationLoaded('items')
                ? $envio->items
                : $envio->items()->get(['id', 'linea_entregada']);
            if ($items->contains('linea_entregada', true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * True si alguna línea tiene cantidad aún sin entregar en destino (según checks de logística).
     */
    public function tienePartidasPendientesDeEnvioLogistica(): bool
    {
        $this->loadMissing('detalles');

        foreach ($this->detalles as $d) {
            if (LogisticaEnvio::cantidadPendienteEntregaFacturaDetalle((int) $d->id) > 1e-6) {
                return true;
            }
        }

        return false;
    }

    /**
     * Envío a enlazar como "Ver envío" cuando no aplica un envío nuevo (remisión o factura con envío cerrado).
     */
    public function envioLogisticaParaAccionVer(): ?LogisticaEnvio
    {
        $remision = $this->relationLoaded('remisionVinculada')
            ? $this->remisionVinculada
            : $this->remisionVinculada()->first();

        if ($remision?->logisticaEnvio) {
            return $remision->logisticaEnvio;
        }

        $query = $this->relationLoaded('logisticaEnvios')
            ? $this->logisticaEnvios->sortByDesc('id')->values()
            : $this->logisticaEnvios()->orderByDesc('id')->get();

        $entregado = $query->firstWhere('estado', 'entregado');

        return $entregado ?? $query->first();
    }

    /**
     * Indica si no debe moverse inventario al timbrar/cancelar (salida ya registrada en la remisión).
     */
    public function inventarioDescontadoEnRemision(): bool
    {
        // Se descontó el inventario al enviar la remisión (salida de almacén).
        // Para trazabilidad, una remisión puede conservar una factura cancelada
        // en `remisiones.factura_id_cancelada`, por lo que aquí se valida ambos casos.
        return Remision::where('factura_id', $this->id)
            ->orWhere('factura_id_cancelada', $this->id)
            ->exists();
    }

    /**
     * Relación con Detalle (productos)
     */
    public function detalles()
    {
        return $this->hasMany(FacturaDetalle::class);
    }

    public function logisticaEnvios()
    {
        return $this->hasMany(LogisticaEnvio::class);
    }

    /**
     * Relación con Cuenta por Cobrar
     */
    public function cuentaPorCobrar()
    {
        return $this->hasOne(CuentaPorCobrar::class);
    }

    /**
     * Auditoría de cancelaciones administrativas (solo ERP).
     */
    public function cancelacionesAdministrativas()
    {
        return $this->hasMany(FacturaCancelacionAdministrativa::class);
    }

    public function cancelacionAdministrativaUsuario()
    {
        return $this->belongsTo(User::class, 'cancelacion_administrativa_user_id');
    }

    public function cancelacionEventos()
    {
        return $this->morphMany(CfdiCancelacionEvento::class, 'cancelable');
    }

    /**
     * Documentos relacionados en complementos de pago (pagos aplicados a esta factura)
     */
    public function documentosRelacionadosPago()
    {
        return $this->hasMany(DocumentoRelacionadoPago::class);
    }

    /**
     * Solo documentos cuyo complemento sigue timbrado (vigente).
     * Tras cancelar el complemento, las filas históricas siguen existiendo pero no deben bloquear la factura.
     */
    public function documentosRelacionadosPagoVigentes()
    {
        return $this->documentosRelacionadosPago()->whereHas('pagoRecibido.complementoPago', function ($q) {
            $q->where('estado', 'timbrado');
        });
    }

    /**
     * Devoluciones asociadas a esta factura
     */
    public function devoluciones()
    {
        return $this->hasMany(Devolucion::class);
    }

    /**
     * Notas de crédito que referencian esta factura
     */
    public function notasCredito()
    {
        return $this->hasMany(NotaCredito::class);
    }

    /**
     * Verificar si está timbrada
     */
    public function estaTimbrada(): bool
    {
        return $this->estado === 'timbrada' && ! empty($this->uuid);
    }

    /**
     * Verificar si está cancelada
     */
    public function estaCancelada(): bool
    {
        return $this->estado === 'cancelada';
    }

    /**
     * Verificar si es borrador
     */
    public function esBorrador(): bool
    {
        return $this->estado === 'borrador';
    }

    /**
     * Verificar si puede ser timbrada (stock, clave SAT y datos mínimos del PAC).
     */
    public function puedeTimbrar(): bool
    {
        return $this->motivoNoTimbrar() === null;
    }

    /**
     * Motivo por el cual no se puede timbrar (null = listo para timbrar).
     * Stock y clave SAT se exigen aquí, no al convertir cotización → factura borrador.
     */
    public function motivoNoTimbrar(): ?string
    {
        if ($this->estado !== 'borrador') {
            return 'Solo se pueden timbrar facturas en borrador.';
        }
        if ((float) $this->total <= 0) {
            return 'El total de la factura debe ser mayor a cero.';
        }

        $this->loadMissing(['detalles.producto']);

        if ($this->detalles->isEmpty()) {
            return 'La factura no tiene partidas para timbrar.';
        }

        $clavesPendientes = [];
        $unidadesPendientes = [];
        foreach ($this->detalles as $d) {
            $etiqueta = trim((string) ($d->descripcion ?: ($d->producto->nombre ?? 'partida')));
            $claveDetalle = trim((string) ($d->clave_prod_serv ?? ''));
            $claveProducto = trim((string) ($d->producto->clave_sat ?? ''));
            $claveEfectiva = $this->claveSatEfectivaPartida($claveDetalle, $claveProducto);
            if ($claveEfectiva === '' || $claveEfectiva === '01010101') {
                $clavesPendientes[] = $etiqueta;
            }

            $unidadDetalle = trim((string) ($d->clave_unidad ?? ''));
            $unidadProducto = trim((string) ($d->producto->clave_unidad_sat ?? ''));
            $unidadEfectiva = $unidadDetalle !== '' ? $unidadDetalle : $unidadProducto;
            if ($unidadEfectiva === '') {
                $unidadesPendientes[] = $etiqueta;
            }
        }

        if (! empty($clavesPendientes)) {
            return 'Falta clave SAT válida (no provisional 01010101) en: '
                . implode('; ', $clavesPendientes)
                . '.';
        }

        if (! empty($unidadesPendientes)) {
            return 'Falta clave de unidad SAT en: '
                . implode('; ', $unidadesPendientes)
                . '. Complétela en el catálogo antes de timbrar.';
        }

        if (! $this->inventarioDescontadoEnRemision()) {
            $sinStock = [];
            foreach ($this->detalles as $d) {
                $producto = $d->producto;
                if ($producto && $producto->controla_inventario && ! $producto->tieneStock((float) $d->cantidad)) {
                    $sinStock[] = $producto->nombre
                        . ' (requiere ' . $d->cantidad . ', hay ' . $producto->stock . ')';
                }
            }
            if (! empty($sinStock)) {
                return 'Falta stock: ' . implode('; ', $sinStock);
            }
        }

        return null;
    }

    /**
     * Si la partida aún tiene clave provisional (o unidad vacía), toma datos del producto del catálogo.
     * Solo aplica a borrador; no pisa claves SAT ya definidas a mano en la factura.
     *
     * @return int número de partidas actualizadas
     */
    public function sincronizarDatosFiscalesDesdeProductos(): int
    {
        if (! $this->esBorrador()) {
            return 0;
        }

        $this->loadMissing(['detalles.producto']);
        $actualizados = 0;

        foreach ($this->detalles as $d) {
            if (! $d->producto) {
                continue;
            }

            $updates = [];
            $claveDetalle = trim((string) ($d->clave_prod_serv ?? ''));
            $claveProducto = trim((string) ($d->producto->clave_sat ?? ''));
            if (($claveDetalle === '' || $claveDetalle === '01010101')
                && $claveProducto !== ''
                && $claveProducto !== '01010101') {
                $updates['clave_prod_serv'] = $claveProducto;
            }

            $unidadDetalle = trim((string) ($d->clave_unidad ?? ''));
            $unidadProducto = trim((string) ($d->producto->clave_unidad_sat ?? ''));
            if ($unidadDetalle === '' && $unidadProducto !== '') {
                $updates['clave_unidad'] = $unidadProducto;
            }

            if (! empty($updates)) {
                $d->update($updates);
                $actualizados++;
            }
        }

        if ($actualizados > 0) {
            $this->unsetRelation('detalles');
            $this->load(['detalles.producto']);
        }

        return $actualizados;
    }

    /**
     * Estado de claves/unidad SAT en borrador tras intentar sincronizar desde catálogo.
     * Define si hay que completar productos o si aún conviene mostrar «Actualizar claves SAT».
     *
     * @return array{
     *     puede_sincronizar_desde_catalogo: bool,
     *     pendiente_en_catalogo: bool,
     *     partidas: list<array{
     *         etiqueta: string,
     *         producto_id: int|null,
     *         falta_clave_catalogo: bool,
     *         falta_unidad_catalogo: bool,
     *         sincronizable: bool
     *     }>
     * }
     */
    public function diagnosticoDatosFiscalesBorrador(): array
    {
        $vacio = [
            'puede_sincronizar_desde_catalogo' => false,
            'pendiente_en_catalogo' => false,
            'partidas' => [],
        ];

        if (! $this->esBorrador()) {
            return $vacio;
        }

        $this->loadMissing(['detalles.producto']);
        $partidas = [];

        foreach ($this->detalles as $d) {
            if (! $d->producto) {
                continue;
            }

            $etiqueta = trim((string) ($d->descripcion ?: ($d->producto->nombre ?? 'partida')));
            $productoId = $d->producto_id ? (int) $d->producto_id : null;

            $claveDetalle = trim((string) ($d->clave_prod_serv ?? ''));
            $claveProducto = trim((string) ($d->producto->clave_sat ?? ''));
            $claveDetalleProvisional = ($claveDetalle === '' || $claveDetalle === '01010101');
            $claveProductoLista = ($claveProducto !== '' && $claveProducto !== '01010101');

            $unidadDetalle = trim((string) ($d->clave_unidad ?? ''));
            $unidadProducto = trim((string) ($d->producto->clave_unidad_sat ?? ''));
            $unidadDetalleVacia = $unidadDetalle === '';
            $unidadProductoLista = $unidadProducto !== '';

            $sincronizable = false;
            $faltaClaveCatalogo = false;
            $faltaUnidadCatalogo = false;

            if ($claveDetalleProvisional) {
                if ($claveProductoLista) {
                    $sincronizable = true;
                } else {
                    $faltaClaveCatalogo = true;
                }
            }

            if ($unidadDetalleVacia) {
                if ($unidadProductoLista) {
                    $sincronizable = true;
                } else {
                    $faltaUnidadCatalogo = true;
                }
            }

            if (! $sincronizable && ! $faltaClaveCatalogo && ! $faltaUnidadCatalogo) {
                continue;
            }

            $partidas[] = [
                'etiqueta' => $etiqueta,
                'producto_id' => $productoId,
                'falta_clave_catalogo' => $faltaClaveCatalogo,
                'falta_unidad_catalogo' => $faltaUnidadCatalogo,
                'sincronizable' => $sincronizable,
            ];
        }

        return [
            'puede_sincronizar_desde_catalogo' => collect($partidas)->contains('sincronizable', true),
            'pendiente_en_catalogo' => collect($partidas)->contains(fn ($p) => $p['falta_clave_catalogo'] || $p['falta_unidad_catalogo']),
            'partidas' => $partidas,
        ];
    }

    private function claveSatEfectivaPartida(string $claveDetalle, string $claveProducto): string
    {
        if ($claveDetalle !== '' && $claveDetalle !== '01010101') {
            return $claveDetalle;
        }
        if ($claveProducto !== '') {
            return $claveProducto;
        }

        return $claveDetalle;
    }

    /**
     * Timbrada en el PAC pero cancelada solo en ERP; falta enviar la cancelación ante el PAC/SAT.
     * El inventario y saldo ya se revirtieron en cancelación administrativa.
     * No aplica si ya hay solicitud pending o si el SAT ya canceló.
     */
    public function pendienteCancelacionAntePac(): bool
    {
        return $this->estado === 'cancelada'
            && $this->cancelacion_administrativa
            && ! empty($this->uuid)
            && ! $this->canceladaAnteSat()
            && ! $this->solicitudFiscalPendiente()
            && ! $this->cancelacionFiscalRechazada()
            && empty($this->estatus_cancelacion_pac)
            && empty($this->fecha_solicitud_cancelacion);
    }

    public function canceladaAnteSat(): bool
    {
        return EstatusCancelacionCfdi::esCanceladaSat(
            $this->estatus_cancelacion_pac,
            $this->estatus_sat,
            $this->codigo_estatus_cancelacion
        );
    }

    public function solicitudFiscalPendiente(): bool
    {
        return EstatusCancelacionCfdi::esPendientePac($this->estatus_cancelacion_pac);
    }

    public function cancelacionFiscalRechazada(): bool
    {
        return EstatusCancelacionCfdi::esRechazada(
            $this->estatus_cancelacion_pac,
            $this->codigo_estatus_cancelacion
        );
    }

    /**
     * Verificar si puede ser cancelada ante el PAC (timbrada, o cancelada administrativamente pendiente de PAC).
     * Sin documentos relacionados que bloqueen (misma regla que flujo castada).
     * Si ya hay solicitud pending, no reenviar: usar actualizar estatus.
     */
    public function puedeCancelar(): bool
    {
        if ($this->tieneDocumentosRelacionados()) {
            return false;
        }

        if ($this->estado === 'timbrada') {
            return true;
        }

        return $this->pendienteCancelacionAntePac() || $this->puedeReintentarCancelacionFiscal();
    }

    public function puedeReintentarCancelacionFiscal(): bool
    {
        return $this->estado === 'cancelada'
            && $this->cancelacion_administrativa
            && ! empty($this->uuid)
            && ! $this->canceladaAnteSat()
            && ! $this->solicitudFiscalPendiente()
            && $this->cancelacionFiscalRechazada();
    }

    public function puedeConsultarEstatusCancelacion(): bool
    {
        return ! empty($this->uuid)
            && ($this->estado === 'cancelada' || $this->cancelacion_administrativa);
    }

    /**
     * Verificar si la factura tiene documentos relacionados que impiden cancelarla.
     * Incluye: complementos de pago vigentes, NC timbradas y devoluciones autorizadas.
     */
    public function tieneDocumentosRelacionados(): bool
    {
        return $this->documentosRelacionadosPagoVigentes()->exists()
            || $this->notasCredito()->where('estado', 'timbrada')->exists()
            || $this->devoluciones()->where('estado', 'autorizada')->exists();
    }

    /**
     * Obtener detalle de documentos relacionados para mensajes informativos.
     */
    public function getDocumentosRelacionadosDetalle(): array
    {
        $detalle = [];
        if ($this->documentosRelacionadosPagoVigentes()->exists()) {
            $count = $this->documentosRelacionadosPagoVigentes()->count();
            $detalle[] = $count === 1
                ? '1 complemento de pago aplicado'
                : "{$count} complementos de pago aplicados";
        }
        $ncQuery = $this->notasCredito()->where('estado', 'timbrada');
        if ($ncQuery->exists()) {
            $count = $ncQuery->count();
            $detalle[] = $count === 1
                ? '1 nota de crédito emitida'
                : "{$count} notas de crédito emitidas";
        }
        $devQuery = $this->devoluciones()->where('estado', 'autorizada');
        if ($devQuery->exists()) {
            $count = $devQuery->count();
            $detalle[] = $count === 1
                ? '1 devolución registrada'
                : "{$count} devoluciones registradas";
        }

        return $detalle;
    }

    /**
     * Verificar si es a crédito (PPD)
     */
    public function esCredito(): bool
    {
        return $this->metodo_pago === 'PPD';
    }

    /**
     * Obtener folio completo
     */
    public function getFolioCompletoAttribute(): string
    {
        return $this->serie.'-'.str_pad($this->folio, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular solo IVA (traslados); no incluye retenciones (ISR, etc.).
     */
    public function calcularIVA(): float
    {
        $iva = $this->detalles->sum(function ($d) {
            return $d->impuestos->sum(function ($i) {
                return ($i->tipo ?? '') === 'traslado' ? (float) ($i->importe ?? 0) : 0.0;
            });
        });
        if ($iva > 0 || $this->detalles->count() === 0) {
            return (float) $iva;
        }
        $baseIva = $this->subtotal - $this->descuento;

        return round($baseIva * 0.16, 2);
    }

    /**
     * Scope para facturas timbradas
     */
    public function scopeTimbradas($query)
    {
        return $query->where('estado', 'timbrada');
    }

    /**
     * Scope para facturas de un periodo
     */
    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;

        return $query->whereMonth('fecha_emision', $mes)
            ->whereYear('fecha_emision', $anio);
    }

    /**
     * Etiqueta del estado para mostrar en listados (incluye código SAT de cancelación).
     * Códigos SAT: https://apisandbox.facturama.mx/docs
     */
    public function getEstadoEtiquetaAttribute(): string
    {
        if ($this->estado === 'borrador') {
            return 'Borrador';
        }
        if ($this->estado === 'timbrada') {
            $cod = $this->codigo_estatus_cancelacion;
            if ($cod && (str_starts_with($cod, 'R') || str_starts_with($cod, 'Rechazada'))) {
                return 'Timbrada ('.self::descripcionCodigoCancelacion($cod).')';
            }

            return 'Timbrada';
        }
        if ($this->estado === 'cancelada') {
            return EstatusCancelacionCfdi::etiquetaListado(
                $this->estado,
                (bool) $this->cancelacion_administrativa,
                $this->estatus_cancelacion_pac,
                $this->estatus_sat,
                $this->codigo_estatus_cancelacion,
                $this->pendienteCancelacionAntePac()
            );
        }

        return $this->estado ?? '—';
    }

    /**
     * Estatus de la solicitud de cancelación (paso a paso) — descripción SAT del código.
     */
    public function getEstatusSolicitudLabelAttribute(): ?string
    {
        if ($this->estado !== 'cancelada' && ! $this->cancelacion_administrativa) {
            return null;
        }
        if ($this->solicitudFiscalPendiente()) {
            $hasta = $this->fecha_vencimiento_aceptacion
                ? $this->fecha_vencimiento_aceptacion->format('d/m/Y H:i')
                : null;

            return $hasta
                ? 'Pendiente de aceptación del receptor (hasta '.$hasta.')'
                : 'Pendiente de aceptación del receptor (hasta 72 h)';
        }
        if ($this->canceladaAnteSat()) {
            return 'Cancelada ante el SAT';
        }
        if ($this->cancelacionFiscalRechazada()) {
            return 'Rechazada por el receptor · SAT vigente';
        }
        if ($this->pendienteCancelacionAntePac()) {
            return 'Cancelación administrativa en ERP (pendiente de envío al SAT)';
        }
        if ($this->estatus_sat) {
            return 'SAT: '.$this->estatus_sat;
        }
        $cod = $this->codigo_estatus_cancelacion;
        if ($cod === null || $cod === '') {
            return null;
        }

        return self::descripcionCodigoCancelacion($cod);
    }

    public static function descripcionCodigoCancelacion(?string $codigo): string
    {
        return EstatusCancelacionCfdi::descripcionCodigo($codigo);
    }

    /**
     * Scope para búsqueda
     */
    public function scopeBuscar($query, $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('folio', 'like', "%{$search}%")
                ->orWhere('serie', 'like', "%{$search}%")
                ->orWhere('uuid', 'like', "%{$search}%")
                ->orWhere('nombre_receptor', 'like', "%{$search}%")
                ->orWhere('rfc_receptor', 'like', "%{$search}%");
        });
    }
}
