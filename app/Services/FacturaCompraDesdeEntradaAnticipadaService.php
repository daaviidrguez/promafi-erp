<?php

namespace App\Services;

use App\Events\FacturaCompraDesdeCfdiRegistrada;
use App\Exceptions\TotalesEaCfdiRequierenConfirmacionException;
use App\Models\CotizacionCompraDetalle;
use App\Models\CuentaPorPagar;
use App\Models\Empresa;
use App\Models\EntradaAnticipada;
use App\Models\FacturaCompra;
use App\Models\FacturaCompraDetalle;
use App\Models\FacturaCompraImpuesto;
use App\Models\FacturaDetalle;
use App\Models\Producto;
use App\Models\Proveedor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class FacturaCompraDesdeEntradaAnticipadaService
{
    public function __construct(
        private EntradaAnticipadaInventarioService $inventarioService,
        private EntradaAnticipadaService $entradaAnticipadaService
    ) {}

    /**
     * Resumen de la última corrección de costo_unitario_timbrado (reporte utilidad).
     *
     * @var array{lineas:int,folios:array<int,string>}
     */
    public array $ultimoResumenCorreccionUtilidad = [
        'lineas' => 0,
        'folios' => [],
    ];

    public function eaPuedeFacturarse(EntradaAnticipada $ea): bool
    {
        return $ea->puedeFacturarse();
    }

    /**
     * Crea compra manual (sin CFDI) vinculada a EA. Inventario ya aplicado.
     *
     * @param  array<int, array<string, mixed>>  $lineas
     */
    public function crearCompraManual(
        EntradaAnticipada $ea,
        array $encabezado,
        array $lineas,
        ?UploadedFile $pdf = null
    ): FacturaCompra {
        if (! $this->eaPuedeFacturarse($ea)) {
            throw new \RuntimeException('Esta entrada anticipada no admite facturación.');
        }

        $ea->loadMissing(['detalles.producto', 'proveedor', 'ordenCompra', 'empresa']);
        $proveedor = $ea->proveedor;
        $empresa = Empresa::principal();
        if (! $empresa || ! $proveedor) {
            throw new \RuntimeException('Datos de empresa o proveedor incompletos.');
        }

        return DB::transaction(function () use ($ea, $encabezado, $lineas, $pdf, $proveedor, $empresa) {
            $this->validarLineasContraEa($ea, $lineas);

            $folioInterno = FacturaCompra::generarFolioInterno();
            $metodoPago = $encabezado['metodo_pago'] ?? 'PUE';
            $totales = $this->calcularTotalesDesdeLineas($lineas);

            $fc = FacturaCompra::create([
                'serie' => '',
                'folio' => $folioInterno,
                'folio_interno' => $folioInterno,
                'tipo_comprobante' => 'E',
                'estado' => 'recibida',
                'origen' => 'entrada_anticipada',
                'proveedor_id' => $proveedor->id,
                'empresa_id' => $empresa->id,
                'orden_compra_id' => $ea->orden_compra_id,
                'entrada_anticipada_id' => $ea->id,
                'rfc_emisor' => $proveedor->rfc ?? '',
                'nombre_emisor' => $proveedor->nombre,
                'regimen_fiscal_emisor' => $proveedor->regimen_fiscal,
                'rfc_receptor' => $empresa->rfc ?? '',
                'nombre_receptor' => $empresa->razon_social ?? '',
                'regimen_fiscal_receptor' => $empresa->regimen_fiscal,
                'fecha_emision' => $encabezado['fecha_emision'],
                'forma_pago' => $encabezado['forma_pago'] ?? null,
                'metodo_pago' => $metodoPago,
                'moneda' => $ea->moneda ?? 'MXN',
                'tipo_cambio' => $ea->tipo_cambio ?? 1,
                'subtotal' => $totales['subtotal'],
                'descuento' => $totales['descuento'],
                'total' => $totales['total'],
                'observaciones' => $encabezado['observaciones'] ?? null,
                'fecha_recepcion' => $ea->fecha_recepcion,
                'usuario_id' => auth()->id(),
            ]);

            if ($pdf) {
                $fc->update(['pdf_path' => $this->guardarPdf($pdf, $fc)]);
            }

            $this->crearDetallesCompra($fc, $lineas);
            $this->ultimoResumenCorreccionUtilidad = ['lineas' => 0, 'folios' => []];
            $this->ajustarCostosDesdeEa($ea, $lineas);
            $this->marcarEaFacturada($ea, $fc);
            $this->crearCuentaPorPagarSiAplica($fc, $proveedor, $metodoPago);

            if ($ea->ordenCompra) {
                $this->entradaAnticipadaService->actualizarEstadoOrdenTrasFacturar($ea->ordenCompra);
            }

            return $fc->fresh(['detalles.producto', 'cuentaPorPagar']);
        });
    }

    /**
     * Crea compra desde datos CFDI parseados, vinculada a EA.
     *
     * @param  array<string, mixed>  $datos
     * @param  array<int, array<string, mixed>>  $productosForm
     * @param  bool  $confirmarDesfaseTotales  Si true, permite |total CFDI − total EA| > 0.05 y aplica costos fiscales.
     */
    public function crearCompraDesdeCfdi(
        EntradaAnticipada $ea,
        array $datos,
        array $productosForm,
        array $encabezado,
        ?UploadedFile $pdf = null,
        bool $confirmarDesfaseTotales = false
    ): FacturaCompra {
        if (! $this->eaPuedeFacturarse($ea)) {
            throw new \RuntimeException('Esta entrada anticipada no admite facturación.');
        }

        $ea->loadMissing(['detalles.producto', 'proveedor', 'ordenCompra']);
        $proveedor = Proveedor::findOrFail((int) $encabezado['proveedor_id']);
        $this->asegurarRfcProveedorCoincideConCfdi($proveedor, $datos);

        $empresa = Empresa::principal();
        if (! $empresa) {
            throw new \RuntimeException('Configura la empresa primero.');
        }

        $conceptos = $datos['conceptos'] ?? [];
        $lineas = [];
        foreach ($productosForm as $p) {
            $idx = (int) ($p['concepto_index'] ?? -1);
            if (! isset($conceptos[$idx])) {
                throw new \RuntimeException('Datos de detalle CFDI inválidos.');
            }
            if (empty($p['producto_id'])) {
                throw new \RuntimeException('Faltan productos por vincular en el detalle del CFDI.');
            }
            $concepto = $conceptos[$idx];
            $eaDetalleId = (int) ($p['entrada_detalle_id'] ?? 0);
            $lineas[] = [
                'entrada_detalle_id' => $eaDetalleId > 0 ? $eaDetalleId : null,
                'producto_id' => (int) $p['producto_id'],
                'descripcion' => $concepto['descripcion'] ?? '',
                'cantidad' => (float) $concepto['cantidad'],
                'precio_unitario' => (float) $concepto['valor_unitario'],
                'descuento_porcentaje' => 0,
                'tasa_iva' => null,
                'concepto' => $concepto,
            ];
        }

        $this->validarLineasContraEa($ea, $lineas);

        $ea->loadMissing('detalles');
        app(EntradaAnticipadaService::class)->normalizarImportesDetalle($ea);
        $ea->refresh()->load(['detalles', 'proveedor', 'ordenCompra']);

        $totalCfdi = (float) ($datos['total'] ?? 0);
        $subtotalCfdi = (float) ($datos['subtotal'] ?? 0);
        $totalEa = (float) $ea->total;
        $hayDesfaseTotales = abs($totalCfdi - $totalEa) > 0.05;

        if ($hayDesfaseTotales && ! $confirmarDesfaseTotales) {
            throw new TotalesEaCfdiRequierenConfirmacionException(
                $totalCfdi,
                $totalEa,
                $subtotalCfdi,
                (float) $ea->subtotal,
                $this->eaTieneCostosProvisionales($ea)
            );
        }

        return DB::transaction(function () use (
            $ea,
            $datos,
            $lineas,
            $encabezado,
            $pdf,
            $proveedor,
            $empresa,
            $hayDesfaseTotales,
            $totalCfdi,
            $totalEa
        ) {
            $notaReasignacion = $this->sincronizarProveedorEaConSeleccionCfdi($ea, $proveedor);

            $folioInterno = FacturaCompra::generarFolioInterno();
            $serie = $this->normalizarSerie((string) ($datos['serie'] ?? ''));
            $metodoPago = $encabezado['metodo_pago'] ?? ($datos['metodo_pago'] ?? 'PUE');

            $obsBase = trim((string) ($encabezado['observaciones'] ?? ''));
            if ($hayDesfaseTotales) {
                $notaDesfase = 'Desfase de totales confirmado: EA $'.number_format($totalEa, 2)
                    .' → CFDI $'.number_format($totalCfdi, 2)
                    .'. Se aplicaron precios fiscales a costo / costo promedio de productos.';
                $obsBase = $obsBase === '' ? $notaDesfase : ($obsBase.' · '.$notaDesfase);
            }
            if ($notaReasignacion !== null) {
                $obsBase = $obsBase === '' ? $notaReasignacion : ($obsBase.' · '.$notaReasignacion);
            }

            $fc = FacturaCompra::create([
                'serie' => $serie !== '' ? $serie : null,
                'folio' => $datos['folio'] ?? '0',
                'folio_interno' => $folioInterno,
                'tipo_comprobante' => $datos['tipo_comprobante'] ?? 'E',
                'estado' => 'recibida',
                'origen' => 'entrada_anticipada',
                'proveedor_id' => $proveedor->id,
                'empresa_id' => $empresa->id,
                'orden_compra_id' => $ea->orden_compra_id,
                'entrada_anticipada_id' => $ea->id,
                'rfc_emisor' => $datos['rfc_emisor'] ?? $proveedor->rfc,
                'nombre_emisor' => $datos['nombre_emisor'] ?? $proveedor->nombre,
                'regimen_fiscal_emisor' => $datos['regimen_fiscal_emisor'] ?? $proveedor->regimen_fiscal,
                'rfc_receptor' => $datos['rfc_receptor'] ?? $empresa->rfc,
                'nombre_receptor' => $datos['nombre_receptor'] ?? $empresa->razon_social,
                'regimen_fiscal_receptor' => $datos['regimen_fiscal_receptor'] ?? $empresa->regimen_fiscal,
                'lugar_expedicion' => $datos['lugar_expedicion'] ?? null,
                'fecha_emision' => $encabezado['fecha_emision'],
                'forma_pago' => $encabezado['forma_pago'] ?? ($datos['forma_pago'] ?? null),
                'metodo_pago' => $metodoPago,
                'moneda' => $datos['moneda'] ?? 'MXN',
                'tipo_cambio' => (float) ($datos['tipo_cambio'] ?? 1),
                'subtotal' => (float) ($datos['subtotal'] ?? 0),
                'descuento' => (float) ($datos['descuento'] ?? 0),
                'total' => (float) ($datos['total'] ?? 0),
                'uuid' => $datos['uuid'] ?? null,
                'fecha_timbrado' => ! empty($datos['fecha_timbrado']) ? $datos['fecha_timbrado'] : null,
                'no_certificado_sat' => $datos['no_certificado_sat'] ?? null,
                'xml_content' => $datos['xml_content'] ?? null,
                'observaciones' => $obsBase !== '' ? $obsBase : null,
                'fecha_recepcion' => $ea->fecha_recepcion,
                'usuario_id' => auth()->id(),
            ]);

            if ($pdf) {
                $fc->update(['pdf_path' => $this->guardarPdf($pdf, $fc)]);
            }

            foreach ($lineas as $index => $linea) {
                $concepto = $linea['concepto'];
                $producto = Producto::find($linea['producto_id']);
                $detalle = FacturaCompraDetalle::create([
                    'factura_compra_id' => $fc->id,
                    'producto_id' => $producto?->id,
                    'clave_prod_serv' => $producto?->clave_sat ?? $concepto['clave_prod_serv'] ?? '01010101',
                    'clave_unidad' => $producto?->clave_unidad_sat ?? $concepto['clave_unidad'] ?? 'H87',
                    'unidad' => $producto?->unidad ?? $concepto['unidad'] ?? 'Pieza',
                    'no_identificacion' => $concepto['no_identificacion'] ?? $producto?->codigo,
                    'descripcion' => $concepto['descripcion'] ?? '',
                    'cantidad' => $concepto['cantidad'],
                    'valor_unitario' => $concepto['valor_unitario'],
                    'importe' => $concepto['importe'],
                    'descuento' => $concepto['descuento'] ?? 0,
                    'base_impuesto' => $concepto['base_impuesto'] ?? $concepto['importe'],
                    'objeto_impuesto' => $producto && in_array($producto->objeto_impuesto ?? '02', ['02', '03'], true) ? '02' : ($concepto['objeto_impuesto'] ?? '02'),
                    'orden' => $index,
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

            $this->ultimoResumenCorreccionUtilidad = ['lineas' => 0, 'folios' => []];
            $this->ajustarCostosDesdeEa($ea, $lineas);
            if ($hayDesfaseTotales) {
                $this->anotarDesfaseTotalesEnEa($ea, $totalEa, $totalCfdi);
            }
            $this->marcarEaFacturada($ea, $fc);
            $this->crearCuentaPorPagarSiAplica($fc, $proveedor, $metodoPago);

            if ($ea->ordenCompra) {
                $this->entradaAnticipadaService->actualizarEstadoOrdenTrasFacturar($ea->ordenCompra);
            }

            $fc = $fc->fresh(['detalles.producto', 'cuentaPorPagar']);
            event(new FacturaCompraDesdeCfdiRegistrada($fc));

            return $fc;
        });
    }

    /**
     * Revierte la facturación de una compra vinculada a EA (sin tocar stock de la entrada anticipada).
     */
    public function revertirFacturacionCompra(FacturaCompra $fc): void
    {
        if (! $fc->entrada_anticipada_id) {
            throw new \RuntimeException('Esta compra no proviene de una entrada anticipada.');
        }

        $ea = EntradaAnticipada::with(['detalles.producto', 'ordenCompra'])
            ->find($fc->entrada_anticipada_id);

        if (! $ea || (int) $ea->factura_compra_id !== (int) $fc->id) {
            throw new \RuntimeException('La entrada anticipada vinculada no coincide con esta compra.');
        }

        $fc->loadMissing('detalles.producto');

        foreach ($fc->detalles as $detCompra) {
            if (! $detCompra->producto_id || ! $detCompra->producto) {
                continue;
            }

            $eaDet = $ea->detalles->firstWhere('producto_id', $detCompra->producto_id);
            if (! $eaDet) {
                continue;
            }

            $cantidad = (float) $detCompra->cantidad;
            $this->inventarioService->revertirAjusteCostoPorFactura(
                $detCompra->producto,
                $cantidad,
                (float) $eaDet->precio_unitario_estimado,
                (float) $detCompra->valor_unitario
            );

            // Restaura último costo al estimado de la EA (antes del CFDI).
            $detCompra->producto->refresh();
            $detCompra->producto->update([
                'costo' => max(0, round((float) $eaDet->precio_unitario_estimado, 2)),
            ]);

            $eaDet->update([
                'cantidad_facturada' => max(0, round((float) $eaDet->cantidad_facturada - $cantidad, 2)),
            ]);
        }

        $ea->update([
            'estado' => 'confirmada',
            'factura_compra_id' => null,
            'fecha_facturacion' => null,
        ]);

        $this->entradaAnticipadaService->revertirOrdenTrasCancelarFacturacionEa($ea->ordenCompra);
    }

    /**
     * El proveedor elegido al vincular debe coincidir por RFC con el emisor del CFDI.
     *
     * @param  array<string, mixed>  $datos
     */
    private function asegurarRfcProveedorCoincideConCfdi(Proveedor $proveedor, array $datos): void
    {
        $rfcXml = strtoupper(preg_replace('/\s+/', '', (string) ($datos['rfc_emisor'] ?? '')));
        $rfcProv = strtoupper(preg_replace('/\s+/', '', (string) ($proveedor->rfc ?? '')));

        if ($rfcXml === '' || $rfcProv === '' || $rfcXml !== $rfcProv) {
            throw new \RuntimeException(
                'El RFC del proveedor seleccionado debe coincidir con el RFC emisor del CFDI'
                .($rfcXml !== '' ? ' ('.$rfcXml.')' : '').'.'
            );
        }
    }

    /**
     * Si el proveedor seleccionado difiere del de la EA, actualiza EA (y OC vinculada) y deja traza.
     *
     * @return string|null Nota de auditoría para observaciones de la compra
     */
    private function sincronizarProveedorEaConSeleccionCfdi(EntradaAnticipada $ea, Proveedor $proveedor): ?string
    {
        if ((int) $proveedor->id === (int) $ea->proveedor_id) {
            return null;
        }

        $ea->loadMissing('proveedor', 'ordenCompra');
        $anterior = $ea->proveedor;
        $quien = auth()->user()?->name ?? ('#'.(auth()->id() ?? '0'));
        $nota = sprintf(
            'Proveedor reasignado al vincular CFDI (%s, %s): #%d %s (%s) → #%d %s (%s).',
            now()->format('Y-m-d H:i'),
            $quien,
            $anterior?->id ?? 0,
            $anterior?->nombre ?? '—',
            $anterior?->rfc ?? '—',
            $proveedor->id,
            $proveedor->nombre,
            $proveedor->rfc ?? '—'
        );

        $obsEa = trim((string) ($ea->observaciones ?? ''));
        $ea->update([
            'proveedor_id' => $proveedor->id,
            'observaciones' => $obsEa === '' ? $nota : ($obsEa."\n".$nota),
        ]);
        $ea->setRelation('proveedor', $proveedor);

        $oc = $ea->ordenCompra;
        if ($oc && (int) $oc->proveedor_id !== (int) $proveedor->id) {
            $obsOc = trim((string) ($oc->observaciones ?? ''));
            $oc->update([
                'proveedor_id' => $proveedor->id,
                'proveedor_nombre' => $proveedor->nombre,
                'proveedor_rfc' => $proveedor->rfc,
                'proveedor_regimen_fiscal' => $proveedor->regimen_fiscal,
                'observaciones' => $obsOc === '' ? $nota : ($obsOc."\n".$nota),
            ]);
        }

        return $nota;
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function validarLineasContraEa(EntradaAnticipada $ea, array $lineas): void
    {
        if (empty($lineas)) {
            throw new \RuntimeException('Agrega al menos una línea para facturar.');
        }

        foreach ($lineas as $linea) {
            $cantidad = (float) ($linea['cantidad'] ?? 0);
            if ($cantidad <= 0) {
                throw new \RuntimeException('Las cantidades a facturar deben ser mayores a cero.');
            }

            $eaDetalleId = (int) ($linea['entrada_detalle_id'] ?? 0);
            if ($eaDetalleId > 0) {
                $det = $ea->detalles->firstWhere('id', $eaDetalleId);
                if (! $det) {
                    throw new \RuntimeException('Línea de entrada anticipada no encontrada.');
                }
                $pendiente = (float) $det->cantidad_recibida - (float) $det->cantidad_facturada;
                if ($cantidad > $pendiente + 0.001) {
                    throw new \RuntimeException("La cantidad a facturar excede lo recibido en «{$det->descripcion}».");
                }
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function calcularTotalesDesdeLineas(array $lineas): array
    {
        $subtotal = $descuento = $total = 0.0;
        foreach ($lineas as $linea) {
            $imp = CotizacionCompraDetalle::calcularImportes([
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'descuento_porcentaje' => $linea['descuento_porcentaje'] ?? 0,
                'tasa_iva' => $linea['tasa_iva'] ?? null,
            ]);
            $subtotal += $imp['subtotal'];
            $descuento += $imp['descuento_monto'];
            $total += $imp['total'];
        }

        return [
            'subtotal' => round($subtotal, 2),
            'descuento' => round($descuento, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function crearDetallesCompra(FacturaCompra $fc, array $lineas): void
    {
        foreach ($lineas as $index => $linea) {
            $producto = Producto::find($linea['producto_id']);
            $imp = CotizacionCompraDetalle::calcularImportes([
                'cantidad' => $linea['cantidad'],
                'precio_unitario' => $linea['precio_unitario'],
                'descuento_porcentaje' => $linea['descuento_porcentaje'] ?? 0,
                'tasa_iva' => $linea['tasa_iva'] ?? null,
            ]);

            $detalle = FacturaCompraDetalle::create([
                'factura_compra_id' => $fc->id,
                'producto_id' => $producto?->id,
                'clave_prod_serv' => $producto?->clave_sat ?? '01010101',
                'clave_unidad' => $producto?->clave_unidad_sat ?? 'H87',
                'unidad' => $producto?->unidad ?? 'Pieza',
                'no_identificacion' => $linea['codigo_proveedor'] ?? $producto?->codigo,
                'descripcion' => $linea['descripcion'] ?? $producto?->nombre,
                'cantidad' => $linea['cantidad'],
                'valor_unitario' => $linea['precio_unitario'],
                'importe' => $imp['subtotal'],
                'descuento' => $imp['descuento_monto'],
                'base_impuesto' => $imp['base_imponible'],
                'objeto_impuesto' => $producto && in_array($producto->objeto_impuesto ?? '02', ['02', '03'], true) ? '02' : '01',
                'orden' => $index,
            ]);

            if ($imp['iva_monto'] > 0) {
                FacturaCompraImpuesto::create([
                    'factura_compra_detalle_id' => $detalle->id,
                    'tipo' => 'traslado',
                    'impuesto' => '002',
                    'tipo_factor' => 'Tasa',
                    'tasa_o_cuota' => 0.16,
                    'base' => $imp['base_imponible'],
                    'importe' => $imp['iva_monto'],
                ]);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $lineas
     */
    private function ajustarCostosDesdeEa(EntradaAnticipada $ea, array $lineas): void
    {
        foreach ($lineas as $linea) {
            $eaDetalleId = (int) ($linea['entrada_detalle_id'] ?? 0);
            $det = $eaDetalleId > 0
                ? $ea->detalles->firstWhere('id', $eaDetalleId)
                : $ea->detalles->firstWhere('producto_id', $linea['producto_id']);

            if (! $det || ! $det->producto) {
                continue;
            }

            $costoEstimado = (float) $det->precio_unitario_estimado;
            $costoFiscal = (float) ($linea['precio_unitario'] ?? $linea['concepto']['valor_unitario'] ?? $costoEstimado);
            $cantidad = (float) ($linea['cantidad'] ?? $det->cantidad_recibida);

            $this->inventarioService->ajustarCostoPorFactura(
                $det->producto,
                $cantidad,
                $costoEstimado,
                $costoFiscal
            );

            // Último costo de catálogo = precio fiscal de la factura.
            $det->producto->refresh();
            $det->producto->update([
                'costo' => max(0, round($costoFiscal, 2)),
            ]);

            // Corrige reporte de utilidad: solo snapshots provisionales ($0 / null).
            if ($costoFiscal > 0.0001) {
                $this->corregirCostoTimbradoProvisionalVenta(
                    (int) $det->producto_id,
                    $costoFiscal,
                    $ea
                );
            }

            if ($det) {
                $det->update([
                    'cantidad_facturada' => (float) $det->cantidad_facturada + $cantidad,
                ]);
            }
        }
    }

    /**
     * Preview: partidas de venta timbradas con costo provisional que se corregirían al facturar la EA.
     *
     * @return array{lineas:int,folios:array<int,string>}
     */
    public function previsualizarCorreccionCostoTimbradoParaEa(EntradaAnticipada $ea): array
    {
        $ea->loadMissing('detalles');
        $productoIds = $ea->detalles
            ->pluck('producto_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($productoIds === []) {
            return ['lineas' => 0, 'folios' => []];
        }

        $detalles = $this->queryDetallesVentaCostoProvisional($productoIds, $ea)->with('factura')->get();

        return $this->resumenDesdeDetallesVenta($detalles);
    }

    /**
     * Actualiza costo_unitario_timbrado solo si era null o ≈ 0 (provisional).
     */
    private function corregirCostoTimbradoProvisionalVenta(int $productoId, float $costoFiscal, EntradaAnticipada $ea): void
    {
        if ($productoId <= 0 || $costoFiscal <= 0.0001) {
            return;
        }

        $detalles = $this->queryDetallesVentaCostoProvisional([$productoId], $ea)->with('factura')->get();
        if ($detalles->isEmpty()) {
            return;
        }

        $costo = round($costoFiscal, 6);
        foreach ($detalles as $detalle) {
            $detalle->update(['costo_unitario_timbrado' => $costo]);
        }

        $resumen = $this->resumenDesdeDetallesVenta($detalles);
        $this->ultimoResumenCorreccionUtilidad['lineas'] += $resumen['lineas'];
        $this->ultimoResumenCorreccionUtilidad['folios'] = array_values(array_unique(array_merge(
            $this->ultimoResumenCorreccionUtilidad['folios'],
            $resumen['folios']
        )));
    }

    /**
     * @param  array<int, int>  $productoIds
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\FacturaDetalle>
     */
    private function queryDetallesVentaCostoProvisional(array $productoIds, EntradaAnticipada $ea)
    {
        $fechaDesde = $ea->fecha_recepcion
            ? $ea->fecha_recepcion->toDateString()
            : ($ea->created_at?->toDateString() ?? now()->toDateString());

        return FacturaDetalle::query()
            ->whereIn('producto_id', $productoIds)
            ->where(function ($q) {
                $q->whereNull('costo_unitario_timbrado')
                    ->orWhere('costo_unitario_timbrado', '<=', 0.0001);
            })
            ->whereHas('factura', function ($q) use ($ea, $fechaDesde) {
                $q->where('estado', 'timbrada');
                if ($ea->cotizacion_id) {
                    $q->where('cotizacion_id', $ea->cotizacion_id);
                } else {
                    $q->whereDate('fecha_emision', '>=', $fechaDesde);
                }
            });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, FacturaDetalle>  $detalles
     * @return array{lineas:int,folios:array<int,string>}
     */
    private function resumenDesdeDetallesVenta($detalles): array
    {
        $folios = $detalles
            ->map(function (FacturaDetalle $d) {
                $f = $d->factura;
                if (! $f) {
                    return null;
                }
                $serie = trim((string) ($f->serie ?? ''));
                $folio = trim((string) ($f->folio ?? ''));

                return $serie !== '' && $folio !== '' ? ($serie.'/'.$folio) : ($folio !== '' ? $folio : ('#'.$f->id));
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return [
            'lineas' => $detalles->count(),
            'folios' => $folios,
        ];
    }

    /**
     * EA con costos estimados en $0 (p. ej. creada desde cotización sin precio de compra).
     */
    public function eaTieneCostosProvisionales(EntradaAnticipada $ea): bool
    {
        $ea->loadMissing('detalles');

        if ((float) $ea->total <= 0.05) {
            return true;
        }

        $detalles = $ea->detalles;
        if ($detalles->isEmpty()) {
            return false;
        }

        $conCero = $detalles->filter(fn ($d) => (float) $d->precio_unitario_estimado <= 0.0001)->count();

        return $conCero >= max(1, (int) ceil($detalles->count() / 2));
    }

    private function anotarDesfaseTotalesEnEa(EntradaAnticipada $ea, float $totalEa, float $totalCfdi): void
    {
        $nota = 'CFDI vinculado con desfase de totales confirmado (EA $'
            .number_format($totalEa, 2).' → CFDI $'.number_format($totalCfdi, 2)
            .'). Costos de producto actualizados con precios fiscales.';
        $obs = trim((string) ($ea->observaciones ?? ''));
        $ea->update([
            'observaciones' => $obs === '' ? $nota : ($obs."\n".$nota),
        ]);
    }

    private function marcarEaFacturada(EntradaAnticipada $ea, FacturaCompra $fc): void
    {
        $ea->load('detalles');
        $pendiente = $ea->detalles->contains(fn ($d) => (float) $d->cantidad_facturada + 0.001 < (float) $d->cantidad_recibida);

        $ea->update([
            'estado' => $pendiente ? 'parcialmente_facturada' : 'facturada',
            'factura_compra_id' => $fc->id,
            'fecha_facturacion' => now(),
        ]);
    }

    private function crearCuentaPorPagarSiAplica(FacturaCompra $fc, Proveedor $proveedor, string $metodoPago): void
    {
        $diasCredito = (int) ($proveedor->dias_credito ?? 0);
        if ($metodoPago !== 'PPD' || $diasCredito <= 0) {
            return;
        }

        $fechaEmision = $fc->fecha_emision;
        CuentaPorPagar::create([
            'factura_compra_id' => $fc->id,
            'orden_compra_id' => null,
            'proveedor_id' => $proveedor->id,
            'monto_total' => $fc->total,
            'monto_pagado' => 0,
            'monto_pendiente' => $fc->total,
            'fecha_emision' => $fechaEmision,
            'fecha_vencimiento' => $fechaEmision->copy()->addDays($diasCredito),
            'estado' => 'pendiente',
        ]);
    }

    private function guardarPdf(UploadedFile $pdf, FacturaCompra $fc): string
    {
        $path = $pdf->store('compras/pdf/'.$fc->id, 'local');

        return $path ?: '';
    }

    private function normalizarSerie(string $serie): string
    {
        $s = trim($serie);
        if ($s === '') {
            return '';
        }
        if (str_contains($s, '/')) {
            $parts = array_filter(explode('/', $s), fn ($p) => trim((string) $p) !== '');
            if (! empty($parts)) {
                $s = (string) $parts[0];
            }
        }
        $s = str_replace(['/', '\\'], '', trim($s));

        return mb_substr($s, 0, 5);
    }
}
