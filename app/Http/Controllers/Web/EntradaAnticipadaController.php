<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use App\Models\EntradaAnticipada;
use App\Models\EntradaAnticipadaDetalle;
use App\Models\OrdenCompra;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use App\Models\Proveedor;
use App\Services\EntradaAnticipadaService;
use App\Services\PDFService;
use Illuminate\Http\Request;

class EntradaAnticipadaController extends Controller
{
    public function index(Request $request)
    {
        $query = EntradaAnticipada::with(['proveedor', 'ordenCompra', 'facturaCompra']);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('folio', 'like', "%{$s}%")
                    ->orWhereHas('proveedor', fn ($qp) => $qp->where('nombre', 'like', "%{$s}%")->orWhere('rfc', 'like', "%{$s}%"))
                    ->orWhereHas('ordenCompra', fn ($qo) => $qo->where('folio', 'like', "%{$s}%"));
            });
        }

        $entradas = $query->orderBy('created_at', 'desc')->paginate(20);
        $estadisticas = [
            'borrador' => EntradaAnticipada::where('estado', 'borrador')->count(),
            'confirmada' => EntradaAnticipada::where('estado', 'confirmada')->count(),
            'facturada' => EntradaAnticipada::where('estado', 'facturada')->count(),
            'cancelada' => EntradaAnticipada::where('estado', 'cancelada')->count(),
        ];

        return view('entradas-anticipadas.index', compact('entradas', 'estadisticas'));
    }

    public function create(Request $request)
    {
        $empresa = Empresa::principal();
        if (! $empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura la empresa primero');
        }

        $folio = EntradaAnticipada::generarFolio();
        $ordenCompra = null;
        $lineasPrecargadas = [];

        if ($request->filled('orden_compra_id')) {
            $ordenCompra = OrdenCompra::with(['detalles.producto', 'proveedor'])->findOrFail($request->integer('orden_compra_id'));
            if (! $ordenCompra->puedeCrearEntradaAnticipada()) {
                return redirect()->route('ordenes-compra.show', $ordenCompra->id)
                    ->with('error', 'Esta orden no admite crear entrada anticipada.');
            }
            foreach ($ordenCompra->detalles as $d) {
                if (! $d->producto_id) {
                    continue;
                }
                $recibida = $ordenCompra->cantidadRecibidaEnEntradas($d->id);
                $pendiente = (float) $d->cantidad - $recibida;
                if ($pendiente <= 0) {
                    continue;
                }
                $lineasPrecargadas[] = [
                    'orden_compra_detalle_id' => $d->id,
                    'producto_id' => $d->producto_id,
                    'codigo' => $d->producto?->codigo,
                    'codigo_proveedor' => $d->codigo_proveedor,
                    'descripcion' => $d->descripcion,
                    'cantidad_ordenada' => (float) $d->cantidad,
                    'cantidad_recibida' => $pendiente,
                    'pendiente' => $pendiente,
                    'precio_unitario_estimado' => (float) $d->precio_unitario,
                    'descuento_porcentaje' => (float) ($d->descuento_porcentaje ?? 0),
                    'tasa_iva' => EntradaAnticipadaDetalle::resolverTasaIva($d->producto, $d->tasa_iva),
                ];
            }
        }

        $proveedorPrecargado = $ordenCompra?->proveedor?->only(['id', 'codigo', 'nombre', 'rfc']);

        return view('entradas-anticipadas.create', compact(
            'empresa',
            'folio',
            'ordenCompra',
            'lineasPrecargadas',
            'proveedorPrecargado'
        ));
    }

    public function store(Request $request, EntradaAnticipadaService $service)
    {
        $validated = $request->validate([
            'proveedor_id' => 'required|exists:proveedores,id',
            'orden_compra_id' => 'nullable|exists:ordenes_compra,id',
            'fecha_recepcion' => 'required|date',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.orden_compra_detalle_id' => 'nullable|exists:ordenes_compra_detalle,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad_recibida' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario_estimado' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.codigo_proveedor' => 'nullable|string|max:100',
            'confirmar' => 'nullable|boolean',
        ]);

        try {
            if (! empty($validated['orden_compra_id'])) {
                $orden = OrdenCompra::findOrFail($validated['orden_compra_id']);
                $ea = $service->crearDesdeOrden($orden, $validated['productos'], [
                    'fecha_recepcion' => $validated['fecha_recepcion'],
                    'observaciones' => $validated['observaciones'] ?? null,
                ]);
            } else {
                $ea = $service->crearDirecta((int) $validated['proveedor_id'], $validated['productos'], [
                    'fecha_recepcion' => $validated['fecha_recepcion'],
                    'observaciones' => $validated['observaciones'] ?? null,
                ]);
            }

            if ($request->boolean('confirmar')) {
                $ea = $service->confirmar($ea);
                $msg = 'Entrada anticipada confirmada. Mercancía registrada en inventario.';
            } else {
                $msg = 'Entrada anticipada guardada en borrador.';
            }

            return redirect()->route('entradas-anticipadas.show', $ea->id)->with('success', $msg);
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(EntradaAnticipada $entradaAnticipada)
    {
        if (in_array($entradaAnticipada->estado, ['borrador', 'confirmada', 'parcialmente_facturada'], true)) {
            app(EntradaAnticipadaService::class)->normalizarImportesDetalle($entradaAnticipada);
            $entradaAnticipada->refresh();
        }

        $entradaAnticipada->load([
            'proveedor',
            'ordenCompra',
            'detalles.producto',
            'facturaCompra',
            'usuario',
        ]);

        return view('entradas-anticipadas.show', ['entrada' => $entradaAnticipada]);
    }

    public function edit(EntradaAnticipada $entradaAnticipada)
    {
        if (! $entradaAnticipada->puedeEditarse()) {
            return redirect()->route('entradas-anticipadas.show', $entradaAnticipada->id)
                ->with('error', 'Solo se puede editar una entrada en borrador.');
        }

        $entradaAnticipada->load(['detalles.producto', 'proveedor', 'ordenCompra']);

        return view('entradas-anticipadas.edit', ['entrada' => $entradaAnticipada]);
    }

    public function update(Request $request, EntradaAnticipada $entradaAnticipada, EntradaAnticipadaService $service)
    {
        $validated = $request->validate([
            'fecha_recepcion' => 'required|date',
            'observaciones' => 'nullable|string',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'required|exists:productos,id',
            'productos.*.orden_compra_detalle_id' => 'nullable|exists:ordenes_compra_detalle,id',
            'productos.*.descripcion' => 'required|string',
            'productos.*.cantidad_recibida' => 'required|numeric|min:0.01',
            'productos.*.precio_unitario_estimado' => 'required|numeric|min:0',
            'productos.*.descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'productos.*.tasa_iva' => 'nullable|numeric',
            'productos.*.codigo_proveedor' => 'nullable|string|max:100',
        ]);

        try {
            $ea = $service->actualizarBorrador($entradaAnticipada, $validated['productos'], [
                'fecha_recepcion' => $validated['fecha_recepcion'],
                'observaciones' => $validated['observaciones'] ?? null,
            ]);

            return redirect()->route('entradas-anticipadas.show', $ea->id)
                ->with('success', 'Entrada anticipada actualizada.');
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function confirmar(EntradaAnticipada $entradaAnticipada, EntradaAnticipadaService $service)
    {
        try {
            $ea = $service->confirmar($entradaAnticipada);

            return redirect()->route('entradas-anticipadas.show', $ea->id)
                ->with('success', 'Mercancía recibida. Inventario actualizado. Puede registrar la factura cuando la reciba del proveedor.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function cancelar(EntradaAnticipada $entradaAnticipada, EntradaAnticipadaService $service)
    {
        try {
            $service->cancelar($entradaAnticipada);

            return redirect()->route('entradas-anticipadas.show', $entradaAnticipada->id)
                ->with('success', 'Entrada anticipada cancelada.');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function facturar(EntradaAnticipada $entradaAnticipada)
    {
        if (! $entradaAnticipada->puedeFacturarse()) {
            return redirect()->route('entradas-anticipadas.show', $entradaAnticipada->id)
                ->with('error', 'Esta entrada no admite facturación.');
        }

        $entrada = $this->prepararEaParaFacturar($entradaAnticipada);

        return view('entradas-anticipadas.facturar', ['entrada' => $entrada]);
    }

    public function verPDF(EntradaAnticipada $entradaAnticipada)
    {
        try {
            $entradaAnticipada->load(['detalles.producto', 'proveedor', 'ordenCompra', 'empresa']);
            app(EntradaAnticipadaService::class)->normalizarImportesDetalle($entradaAnticipada);
            $entradaAnticipada->refresh()->load(['detalles.producto', 'proveedor', 'ordenCompra', 'empresa']);

            $pdfPath = app(PDFService::class)->generarEntradaAnticipadaPDF($entradaAnticipada);

            return response()->file(storage_path('app/'.$pdfPath));
        } catch (\Exception $e) {
            return back()->with('error', 'Error al generar PDF: '.$e->getMessage());
        }
    }

    public function descargarPDF(EntradaAnticipada $entradaAnticipada)
    {
        try {
            $entradaAnticipada->load(['detalles.producto', 'proveedor', 'ordenCompra', 'empresa']);
            app(EntradaAnticipadaService::class)->normalizarImportesDetalle($entradaAnticipada);
            $entradaAnticipada->refresh()->load(['detalles.producto', 'proveedor', 'ordenCompra', 'empresa']);

            $pdfPath = app(PDFService::class)->generarEntradaAnticipadaPDF($entradaAnticipada);
            $nombreArchivo = 'EntradaAnticipada_'.preg_replace('/[^a-zA-Z0-9._-]+/', '_', $entradaAnticipada->folio).'.pdf';

            return response()->download(storage_path('app/'.$pdfPath), $nombreArchivo);
        } catch (\Exception $e) {
            return back()->with('error', 'Error al descargar PDF: '.$e->getMessage());
        }
    }

    public function buscarProveedores(Request $request)
    {
        $q = $request->get('q', '');
        $proveedores = Proveedor::where('activo', true)
            ->where(function ($query) use ($q) {
                $query->where('nombre', 'like', "%{$q}%")
                    ->orWhere('rfc', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            })
            ->limit(15)
            ->get(['id', 'codigo', 'nombre', 'rfc']);

        return response()->json($proveedores->map(fn ($p) => [
            'id' => $p->id,
            'etiqueta' => $p->etiqueta_con_codigo,
            'nombre' => $p->nombre,
            'rfc' => $p->rfc,
        ]));
    }

    public function buscarProductos(Request $request)
    {
        $q = $request->get('q', '');
        $proveedorId = $request->integer('proveedor_id');

        $query = Producto::where('activo', true)
            ->where(function ($qb) use ($q) {
                $qb->where('nombre', 'like', "%{$q}%")
                    ->orWhere('codigo', 'like', "%{$q}%");
            });

        $productos = $query->limit(15)->get(['id', 'codigo', 'nombre', 'costo', 'costo_promedio', 'tasa_iva', 'tipo_factor', 'aplica_iva', 'controla_inventario']);

        return response()->json($productos->map(function ($p) use ($proveedorId) {
            $codigoProv = null;
            if ($proveedorId > 0) {
                $codigoProv = ProductoProveedor::where('producto_id', $p->id)
                    ->where('proveedor_id', $proveedorId)
                    ->value('codigo');
            }

            return [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'descripcion' => $p->nombre,
                'precio' => (float) ($p->costo ?? 0),
                'tasa_iva' => EntradaAnticipadaDetalle::resolverTasaIva($p, null),
                'codigo_proveedor' => $codigoProv,
                'controla_inventario' => (bool) $p->controla_inventario,
            ];
        }));
    }

    private function prepararEaParaFacturar(EntradaAnticipada $entradaAnticipada): EntradaAnticipada
    {
        $entradaAnticipada->loadMissing('detalles.producto');
        app(EntradaAnticipadaService::class)->normalizarImportesDetalle($entradaAnticipada);

        return $entradaAnticipada->fresh(['detalles.producto', 'proveedor', 'ordenCompra']);
    }
}
