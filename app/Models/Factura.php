<?php

namespace App\Models;

// UBICACIÓN: app/Models/Factura.php

use App\Models\Concerns\HasDesgloseTotalesCfdi;
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
     * Verificar si puede ser timbrada
     */
    public function puedeTimbrar(): bool
    {
        return $this->estado === 'borrador' && $this->total > 0;
    }

    /**
     * Timbrada en el PAC pero cancelada solo en ERP; falta la cancelación ante el PAC/SAT.
     * El inventario y saldo ya se revirtieron en cancelación administrativa.
     */
    public function pendienteCancelacionAntePac(): bool
    {
        return $this->estado === 'cancelada'
            && $this->cancelacion_administrativa
            && ! empty($this->uuid)
            && (string) ($this->codigo_estatus_cancelacion ?? '') === 'ADM';
    }

    /**
     * Verificar si puede ser cancelada ante el PAC (timbrada, o cancelada administrativamente pendiente de PAC).
     * Sin documentos relacionados que bloqueen (misma regla que flujo castada).
     */
    public function puedeCancelar(): bool
    {
        if ($this->tieneDocumentosRelacionados()) {
            return false;
        }

        if ($this->estado === 'timbrada') {
            return true;
        }

        return $this->pendienteCancelacionAntePac();
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
            if ($this->pendienteCancelacionAntePac()) {
                return 'Cancelada (Administrativa — ERP)';
            }
            if ($this->cancelacion_administrativa) {
                return 'Cancelada (Administrativa en ERP y ante el PAC/SAT)';
            }
            $cod = $this->codigo_estatus_cancelacion;
            if ($cod && $cod !== 'ADM') {
                return 'Cancelada ('.$cod.')';
            }

            return 'Cancelada';
        }

        return $this->estado ?? '—';
    }

    /**
     * Estatus de la solicitud de cancelación (paso a paso) — descripción SAT del código.
     */
    public function getEstatusSolicitudLabelAttribute(): ?string
    {
        if ($this->estado !== 'cancelada') {
            return null;
        }
        $cod = $this->codigo_estatus_cancelacion;
        if ($cod === null || $cod === '') {
            return null;
        }

        return self::descripcionCodigoCancelacion($cod);
    }

    /**
     * Descripción del código de estatus de cancelación SAT.
     * Documentación: https://apisandbox.facturama.mx/docs
     */
    public static function descripcionCodigoCancelacion(?string $codigo): string
    {
        $map = [
            'ADM' => 'Cancelación administrativa en ERP (pendiente ante PAC/SAT)',
            '201' => 'Solicitud procesada',
            '202' => 'UUID previamente enviado',
            '203' => 'UUID no corresponde al emisor',
            '204' => 'UUID no aplicable',
            '205' => 'UUID no existe',
            '206' => 'En proceso (pendiente aceptación)',
            '301' => 'Sello inválido',
            '302' => 'Certificado revocado/caduco',
            '401' => 'Fecha fuera de rango',
            '601' => 'No cancelable',
        ];
        $cod = (string) $codigo;
        if (str_starts_with($cod, 'R-')) {
            $num = substr($cod, 2);

            return ($map[$num] ?? $num).' (Rechazada)';
        }
        if (str_starts_with($cod, 'R') || str_starts_with($cod, 'Rechazada')) {
            return 'Rechazada';
        }

        return $map[$cod] ?? $codigo ?? '—';
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
