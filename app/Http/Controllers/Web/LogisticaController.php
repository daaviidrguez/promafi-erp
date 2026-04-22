<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\Factura;
use App\Models\FacturaDetalle;
use App\Models\LogisticaEnvio;
use App\Models\LogisticaEnvioItem;
use App\Models\Remision;
use App\Models\RemisionDetalle;
use App\Services\PDFService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LogisticaController extends Controller
{
    public function index(Request $request)
    {
        $query = LogisticaEnvio::query()
            ->with(['cliente', 'factura', 'remision.factura', 'usuario'])
            ->orderByDesc('created_at');

        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('folio', 'like', "%{$s}%")
                    ->orWhereHas('cliente', fn ($c) => $c->where('nombre', 'like', "%{$s}%"));
            });
        }

        $envios = $query->paginate(20);

        $stats = [
            'pendiente' => LogisticaEnvio::query()->where('estado', 'pendiente')->count(),
            'preparado' => LogisticaEnvio::query()->where('estado', 'preparado')->count(),
            'enviado' => LogisticaEnvio::query()->where('estado', 'enviado')->count(),
            'en_ruta' => LogisticaEnvio::query()->where('estado', 'en_ruta')->count(),
            'entrega_parcial' => LogisticaEnvio::query()->where('estado', 'entrega_parcial')->count(),
            'entregado' => LogisticaEnvio::query()->where('estado', 'entregado')->count(),
            'cancelado' => LogisticaEnvio::query()->where('estado', 'cancelado')->count(),
        ];

        return view('logistica.index', compact('envios', 'stats'));
    }

    /**
     * Listado de facturas timbradas y remisiones para elegir documento y abrir el alta ya precargado.
     */
    public function elegirOrigen(Request $request)
    {
        abort_unless(auth()->user()?->can('logistica.crear'), 403);

        $search = $request->get('search');

        $facturas = Factura::query()
            ->where('estado', 'timbrada')
            ->whereNotNull('uuid')
            ->with([
                'cliente:id,nombre,rfc',
                'remisionVinculada:id,factura_id,estado',
                'remisionVinculada.logisticaEnvio',
                'remisionVinculada.logisticaEnvios:id,folio,remision_id,estado',
                'remisionVinculada.logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'logisticaEnvios:id,factura_id,folio,estado',
                'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'detalles:id,factura_id,cantidad',
            ])
            ->when($search, fn ($qq) => $qq->buscar($search))
            ->orderByDesc('fecha_emision')
            ->paginate(25, ['*'], 'fp')
            ->withQueryString();

        $remisiones = Remision::query()
            ->with([
                'cliente:id,nombre',
                'logisticaEnvios:id,folio,remision_id,estado',
                'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'detalles:id,remision_id,cantidad',
            ])
            ->when($search, fn ($qq) => $qq->buscar($search))
            ->orderByDesc('created_at')
            ->paginate(25, ['*'], 'rp')
            ->withQueryString();

        return view('logistica.elegir-origen', compact('facturas', 'remisiones', 'search'));
    }

    /**
     * Selección de varias facturas para encadenar altas de envío (una tras otra), sin cambiar reglas de negocio del alta individual.
     */
    public function elegirOrigenMasivo(Request $request)
    {
        abort_unless(auth()->user()?->can('logistica.crear'), 403);

        if ($request->isMethod('post')) {
            return $this->elegirOrigenMasivoConfirmar($request);
        }

        $search = $request->get('search');

        $facturas = Factura::query()
            ->where('estado', 'timbrada')
            ->whereNotNull('uuid')
            ->with([
                'cliente:id,nombre,rfc',
                'remisionVinculada:id,factura_id,estado',
                'remisionVinculada.logisticaEnvio',
                'remisionVinculada.logisticaEnvios:id,folio,remision_id,estado',
                'remisionVinculada.logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'logisticaEnvios:id,factura_id,folio,estado',
                'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'detalles:id,factura_id,cantidad',
            ])
            ->when($search, fn ($qq) => $qq->buscar($search))
            ->orderByDesc('fecha_emision')
            ->paginate(25, ['*'], 'fp')
            ->withQueryString();

        return view('logistica.elegir-origen-masivo', compact('facturas', 'search'));
    }

    private function elegirOrigenMasivoConfirmar(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'factura_ids' => 'required|array|min:1|max:100',
            'factura_ids.*' => 'integer|exists:facturas,id',
        ]);

        $idsOrdenados = array_values(array_unique(array_map('intval', $validated['factura_ids'])));
        $idsPermitidos = [];

        foreach ($idsOrdenados as $fid) {
            $f = Factura::query()->with([
                'remisionVinculada:id,factura_id,estado',
                'remisionVinculada.logisticaEnvios:id,folio,remision_id,estado',
                'remisionVinculada.logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'logisticaEnvios:id,factura_id,estado',
                'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'detalles:id,factura_id,cantidad',
            ])->find($fid);
            if (! $f || ! $f->estaTimbrada()) {
                return back()->withInput()->with('error', 'La factura indicada no existe o no está timbrada.');
            }
            if (! $f->permiteNuevoEnvioLogistica()) {
                return back()->withInput()->with('error', 'La factura '.$f->folio_completo.' no permite un envío nuevo en este momento (misma validación que en el listado individual).');
            }
            $idsPermitidos[] = $f->id;
        }

        if ($idsPermitidos === []) {
            return back()->with('error', 'No hay facturas válidas para la cola masiva.');
        }

        $request->session()->put('logistica_masivo_factura_ids', $idsPermitidos);

        return redirect()->route('logistica.create', [
            'factura_id' => (int) $idsPermitidos[0],
            'masivo' => 1,
        ])->with('success', 'Cola masiva preparada: '.count($idsPermitidos).' factura(s). Registre el primer envío; al guardar se abrirá el siguiente.');
    }

    public function abandonarColaMasiva(Request $request)
    {
        abort_unless(auth()->user()?->can('logistica.crear'), 403);
        $request->session()->forget('logistica_masivo_factura_ids');

        return redirect()->route('logistica.elegir-origen-masivo')->with('info', 'Cola masiva cancelada.');
    }

    public function create(Request $request)
    {
        abort_unless(auth()->user()?->can('logistica.crear'), 403);

        $logisticaMasivoCola = array_values(array_map('intval', (array) $request->session()->get('logistica_masivo_factura_ids', [])));
        $logisticaMasivoActivo = $request->boolean('masivo') && $logisticaMasivoCola !== [];

        if ($logisticaMasivoActivo) {
            $fidUrl = $request->integer('factura_id');
            if ($fidUrl < 1 || ! in_array($fidUrl, $logisticaMasivoCola, true)) {
                return redirect()->route('logistica.elegir-origen-masivo')
                    ->with('error', 'La cola masiva no incluye ese documento o la sesión expiró. Vuelva a seleccionar las facturas.');
            }
            if ((int) ($logisticaMasivoCola[0] ?? 0) !== $fidUrl) {
                return redirect()->route('logistica.create', [
                    'factura_id' => (int) $logisticaMasivoCola[0],
                    'masivo' => 1,
                ])->with('info', 'Continúe con la siguiente factura de la cola masiva (orden fijo).');
            }
        }

        $motivoPrecargaInvalida = null;
        $precargaPayload = null;

        if ($request->filled('factura_id')) {
            $f = Factura::query()->with([
                'cliente:id,nombre',
                'remisionVinculada:id,factura_id,estado',
                'remisionVinculada.logisticaEnvios:id,folio,remision_id,estado',
                'remisionVinculada.logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'logisticaEnvios:id,factura_id,estado',
                'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'detalles:id,factura_id,cantidad',
            ])->find($request->integer('factura_id'));
            if (! $f || ! $f->estaTimbrada()) {
                $motivoPrecargaInvalida = 'La factura indicada no existe o no está timbrada.';
            } elseif (! $f->permiteNuevoEnvioLogistica()) {
                $motivoPrecargaInvalida = ($f->remisionVinculada && $f->remisionVinculada->estado === 'entregada')
                    ? 'Esta factura proviene de una remisión ya entregada; el envío se gestiona desde esa remisión en logística. No se permite duplicar el registro de envío.'
                    : 'Mientras haya un envío activo sin entrega parcial en destino, continúa en ese registro; el alta de otro envío se habilita al registrar entrega parcial o marcas entregadas y queden partidas pendientes. Si todo está entregado, abre el envío existente.';
            } else {
                $precargaPayload = [
                    'factura' => [
                        'id' => $f->id,
                        'label' => $f->folio_completo.' — '.($f->cliente->nombre ?? $f->nombre_receptor),
                        'cliente_id' => $f->cliente_id,
                    ],
                ];
            }
        } elseif ($request->filled('remision_id')) {
            $r = Remision::query()->with('detalles:id,remision_id,cantidad')->find($request->integer('remision_id'));
            if (! $r) {
                $motivoPrecargaInvalida = 'La remisión indicada no existe.';
            } elseif (! in_array($r->estado, ['enviada', 'entregada'], true)) {
                $motivoPrecargaInvalida = 'Solo se pueden usar remisiones enviadas o entregadas para un envío manual.';
            } elseif (LogisticaEnvio::query()->where('remision_id', $r->id)->exists() && ! $r->tienePartidasPendientesDeEnvioLogistica()) {
                $motivoPrecargaInvalida = 'Esta remisión ya tiene las partidas cubiertas por envíos registrados. Abre un envío existente o revisa cantidades en Logística.';
            } else {
                $precargaPayload = [
                    'remision' => [
                        'id' => $r->id,
                        'label' => $r->folio.' — '.$r->cliente_nombre,
                        'cliente_id' => $r->cliente_id,
                    ],
                ];
            }
        }

        if ($logisticaMasivoActivo && ! empty($motivoPrecargaInvalida)) {
            $request->session()->forget('logistica_masivo_factura_ids');

            return redirect()->route('logistica.elegir-origen-masivo')
                ->with('error', $motivoPrecargaInvalida.' Se canceló la cola masiva; vuelva a seleccionar las facturas.');
        }

        return view('logistica.create', compact(
            'precargaPayload',
            'motivoPrecargaInvalida',
            'logisticaMasivoActivo',
            'logisticaMasivoCola',
        ));
    }

    public function store(Request $request)
    {
        $this->authorizeCrear();

        $validated = $request->validate([
            'origen' => 'required|in:factura,remision',
            'logistica_masivo' => 'nullable|boolean',
            'factura_id' => 'required_if:origen,factura|nullable|exists:facturas,id',
            'remision_id' => 'required_if:origen,remision|nullable|exists:remisiones,id',
            'cliente_id' => 'required|exists:clientes,id',
            'cliente_direccion_entrega_id' => 'nullable|exists:clientes_direcciones_entrega,id',
            'direccion_entrega' => 'nullable|string|max:2000',
            'notas' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.factura_detalle_id' => 'nullable|exists:facturas_detalle,id',
            'items.*.remision_detalle_id' => 'nullable|exists:remisiones_detalle,id',
            'items.*.cantidad' => 'required|numeric|min:0.0001',
        ]);

        if (($validated['origen'] ?? '') === 'factura' && empty($validated['factura_id'])) {
            return back()->withInput()->with('error', 'Selecciona una factura timbrada.');
        }
        if (($validated['origen'] ?? '') === 'remision' && empty($validated['remision_id'])) {
            return back()->withInput()->with('error', 'Selecciona una remisión.');
        }

        $cliente = Cliente::findOrFail((int) $validated['cliente_id']);
        if (! empty($validated['cliente_direccion_entrega_id'])) {
            $dir = $cliente->direccionesEntrega()->whereKey($validated['cliente_direccion_entrega_id'])->first();
            if (! $dir) {
                return back()->withInput()->with('error', 'La dirección de entrega no pertenece al cliente.');
            }
        }

        $facturaId = null;
        $remisionId = null;
        $remisionParaReasign = null;
        $facturaParaReasign = null;

        if ($validated['origen'] === 'factura') {
            $factura = Factura::query()->with([
                'remisionVinculada:id,factura_id,estado',
                'remisionVinculada.logisticaEnvios:id,folio,remision_id,estado',
                'remisionVinculada.logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'logisticaEnvios:id,factura_id,estado',
                'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'detalles:id,factura_id,cantidad',
            ])->findOrFail((int) $validated['factura_id']);
            if (! $factura->estaTimbrada()) {
                return back()->withInput()->with('error', 'Solo facturas timbradas pueden generar envíos de logística.');
            }
            if (! $factura->permiteNuevoEnvioLogistica()) {
                $msg = ($factura->remisionVinculada && $factura->remisionVinculada->estado === 'entregada')
                    ? 'No se puede crear envío desde esta factura: está vinculada a una remisión ya entregada (trazabilidad en logística por remisión).'
                    : 'No se puede crear envío: hay un envío activo que aún no cumple la condición de entrega parcial en destino para permitir otro registro, o no quedan partidas pendientes por entregar.';

                return back()->withInput()->with('error', $msg);
            }
            if ((int) $factura->cliente_id !== (int) $cliente->id) {
                return back()->withInput()->with('error', 'El cliente no coincide con la factura.');
            }
            $facturaId = $factura->id;
            $facturaParaReasign = $factura;

            foreach ($validated['items'] as $idx => $row) {
                if (empty($row['factura_detalle_id'])) {
                    return back()->withInput()->with('error', 'Cada partida debe referenciar una línea de factura.');
                }
                $det = FacturaDetalle::query()->whereKey($row['factura_detalle_id'])->where('factura_id', $factura->id)->first();
                if (! $det) {
                    return back()->withInput()->with('error', 'Partida inválida en la fila '.($idx + 1).'.');
                }
                $maxPermitido = LogisticaEnvio::cantidadMaximaNuevoEnvioFacturaDetalle((int) $det->id);
                if ((float) $row['cantidad'] > $maxPermitido + 1e-6) {
                    return back()->withInput()->with('error', 'Cantidad mayor a lo permitido para esta línea de factura (hueco pendiente + reasignable desde envíos sin entregar en destino): '.\Illuminate\Support\Str::limit($det->descripcion, 60));
                }
            }
        }

        if ($validated['origen'] === 'remision') {
            $remision = Remision::query()->with('detalles:id,remision_id,cantidad')->findOrFail((int) $validated['remision_id']);
            if (! in_array($remision->estado, ['enviada', 'entregada'], true)) {
                return back()->withInput()->with('error', 'La remisión debe estar enviada o entregada.');
            }
            if ((int) $remision->cliente_id !== (int) $cliente->id) {
                return back()->withInput()->with('error', 'El cliente no coincide con la remisión.');
            }
            if (LogisticaEnvio::query()->where('remision_id', $remision->id)->exists() && ! $remision->tienePartidasPendientesDeEnvioLogistica()) {
                return back()->withInput()->with('error', 'Las partidas de esta remisión ya están cubiertas por envíos registrados.');
            }
            $remisionId = $remision->id;
            $remisionParaReasign = $remision;
            if ($remision->factura_id) {
                $facturaId = $remision->factura_id;
            }

            foreach ($validated['items'] as $idx => $row) {
                if (empty($row['remision_detalle_id'])) {
                    return back()->withInput()->with('error', 'Cada partida debe referenciar una línea de la remisión.');
                }
                $det = RemisionDetalle::query()->whereKey($row['remision_detalle_id'])->where('remision_id', $remision->id)->first();
                if (! $det) {
                    return back()->withInput()->with('error', 'Partida inválida en la fila '.($idx + 1).'.');
                }
                $maxPermitido = LogisticaEnvio::cantidadMaximaNuevoEnvioRemisionDetalle((int) $det->id);
                if ((float) $row['cantidad'] > $maxPermitido + 1e-6) {
                    return back()->withInput()->with('error', 'Cantidad mayor a lo permitido para esta línea de remisión (inventario pendiente + reasignable desde envíos sin entregar en destino): '.\Illuminate\Support\Str::limit($det->descripcion, 60));
                }
            }
        }

        $nuevoEnvioId = null;

        try {
            DB::transaction(function () use ($validated, $facturaId, $remisionId, $cliente, &$nuevoEnvioId, $remisionParaReasign, $facturaParaReasign) {
                $folio = LogisticaEnvio::siguienteFolioEnTransaccion();

                $envio = new LogisticaEnvio([
                    'folio' => $folio,
                    'estado' => 'pendiente',
                    'cliente_id' => $cliente->id,
                    'usuario_id' => auth()->id(),
                    'factura_id' => $facturaId,
                    'remision_id' => $remisionId,
                    'cliente_direccion_entrega_id' => $validated['cliente_direccion_entrega_id'] ?? null,
                    'direccion_entrega' => $validated['direccion_entrega'] ?? null,
                    'notas' => $validated['notas'] ?? null,
                ]);
                $envio->save();
                $nuevoEnvioId = $envio->id;

                $envio->registrarHistorial(null, 'pendiente', auth()->id(), 'Envío creado');

                foreach ($validated['items'] as $row) {
                    if ($validated['origen'] === 'factura') {
                        $det = FacturaDetalle::findOrFail((int) $row['factura_detalle_id']);
                        LogisticaEnvioItem::create([
                            'logistica_envio_id' => $envio->id,
                            'factura_detalle_id' => $det->id,
                            'producto_id' => $det->producto_id,
                            'descripcion' => $det->descripcion,
                            'cantidad' => $row['cantidad'],
                        ]);
                    } else {
                        $det = RemisionDetalle::findOrFail((int) $row['remision_detalle_id']);
                        LogisticaEnvioItem::create([
                            'logistica_envio_id' => $envio->id,
                            'remision_detalle_id' => $det->id,
                            'producto_id' => $det->producto_id,
                            'descripcion' => $det->descripcion,
                            'cantidad' => $row['cantidad'],
                        ]);
                    }
                }

                if ($remisionParaReasign) {
                    $this->descontarRemisionItemsNoEntregadosHaciaNuevoEnvio($envio, $remisionParaReasign, $validated['items']);
                }
                if ($facturaParaReasign) {
                    $this->descontarFacturaItemsNoEntregadosHaciaNuevoEnvio($envio, $facturaParaReasign, $validated['items']);
                }
            });
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return $this->respuestaTrasRegistrarEnvio($request, $validated, (int) $nuevoEnvioId);
    }

    /**
     * Tras guardar un envío: si venía de cola masiva de facturas, redirige al siguiente alta o finaliza la cola.
     */
    private function respuestaTrasRegistrarEnvio(Request $request, array $validated, int $nuevoEnvioId): RedirectResponse
    {
        $msgOk = 'Envío de logística registrado correctamente.';

        if (! $request->boolean('logistica_masivo')) {
            return redirect()->route('logistica.show', $nuevoEnvioId)->with('success', $msgOk);
        }

        if (($validated['origen'] ?? '') !== 'factura') {
            $request->session()->forget('logistica_masivo_factura_ids');

            return redirect()->route('logistica.show', $nuevoEnvioId)
                ->with('warning', $msgOk.' La cola masiva se canceló porque el origen no era factura.');
        }

        $cola = $request->session()->get('logistica_masivo_factura_ids', []);
        if (! is_array($cola) || $cola === []) {
            return redirect()->route('logistica.show', $nuevoEnvioId)->with('success', $msgOk);
        }

        $cola = array_values(array_map('intval', $cola));
        $facturaProcesada = (int) ($validated['factura_id'] ?? 0);

        if ((int) ($cola[0] ?? 0) !== $facturaProcesada) {
            $request->session()->put('logistica_masivo_factura_ids', $cola);

            return redirect()->route('logistica.create', [
                'factura_id' => (int) $cola[0],
                'masivo' => 1,
            ])->with('warning', 'Siga el orden de la cola masiva. Pendiente el documento actual.');
        }

        array_shift($cola);
        $cola = array_values(array_map('intval', $cola));

        if ($cola !== []) {
            $request->session()->put('logistica_masivo_factura_ids', $cola);

            return redirect()->route('logistica.create', [
                'factura_id' => (int) $cola[0],
                'masivo' => 1,
            ])->with('success', $msgOk.' Siguiente en cola masiva ('.count($cola).' pendiente(s)).');
        }

        $request->session()->forget('logistica_masivo_factura_ids');

        return redirect()->route('logistica.show', $nuevoEnvioId)->with('success', $msgOk.' Cola masiva finalizada.');
    }

    public function show(LogisticaEnvio $envio)
    {
        $envio->load([
            'cliente.direccionesEntrega',
            'factura.detalles',
            'remision.detalles',
            'direccionEntregaRel',
            'items.producto',
            'items.remisionDetalle.remision',
            'historial.user',
            'usuario',
        ]);

        return view('logistica.show', compact('envio'));
    }

    public function update(Request $request, LogisticaEnvio $envio)
    {
        $this->authorizeEditar();

        $validated = $request->validate([
            'estado' => 'nullable|in:'.implode(',', LogisticaEnvio::ESTADOS),
            'chofer' => 'nullable|string|max:200',
            'recibido_almacen' => 'nullable|string|max:200',
            'lugar_entrega' => 'nullable|string|max:255',
            'entrega_recibido_por' => 'nullable|string|max:200',
            'direccion_entrega' => 'nullable|string|max:2000',
            'cliente_direccion_entrega_id' => 'nullable|exists:clientes_direcciones_entrega,id',
            'notas' => 'nullable|string|max:2000',
            'nota_cambio_estado' => 'nullable|string|max:500',
        ]);

        if ($request->filled('cliente_direccion_entrega_id')) {
            $ok = $envio->cliente->direccionesEntrega()->whereKey($validated['cliente_direccion_entrega_id'])->exists();
            if (! $ok) {
                return back()->with('error', 'La dirección de entrega no pertenece al cliente del envío.');
            }
        }

        $estadoAntes = $envio->estado;

        if (! empty($validated['estado']) && $validated['estado'] !== $envio->estado) {
            if (! $envio->puedeTransicionarA($validated['estado'])) {
                return back()->with('error', 'Transición de estado no permitida.');
            }
            $nota = $validated['nota_cambio_estado'] ?? null;
            $envio->aplicarEstado($validated['estado'], auth()->id(), $nota);
        }

        $envio->refresh();
        $envio->load('items');

        foreach (['chofer', 'recibido_almacen', 'lugar_entrega', 'entrega_recibido_por', 'direccion_entrega', 'cliente_direccion_entrega_id', 'notas'] as $campo) {
            if ($request->exists($campo)) {
                $envio->{$campo} = $validated[$campo] ?? null;
            }
        }
        $envio->save();

        $aplicarChecksPorLinea = $envio->estado === 'entrega_parcial'
            || ($envio->estado === 'entregado' && $estadoAntes === 'entrega_parcial');

        if ($aplicarChecksPorLinea) {
            $marcados = array_map('intval', $request->input('item_entregado_ids', []));
            $idsPermitidos = $envio->items->pluck('id')->all();
            foreach ($marcados as $id) {
                if (! in_array($id, $idsPermitidos, true)) {
                    return back()->with('error', 'Hay partidas inválidas en el formulario.');
                }
            }
            foreach ($envio->items as $item) {
                $item->linea_entregada = in_array((int) $item->id, $marcados, true);
                $item->save();
            }
        } elseif ($envio->estado === 'entregado') {
            $envio->items()->update(['linea_entregada' => true]);
        }

        return back()->with('success', 'Envío actualizado.');
    }

    public function buscarFacturas(Request $request)
    {
        $q = (string) $request->get('q', '');
        $facturas = Factura::query()
            ->where('estado', 'timbrada')
            ->whereNotNull('uuid')
            ->with([
                'cliente:id,nombre,rfc',
                'remisionVinculada:id,factura_id,estado',
                'remisionVinculada.logisticaEnvios:id,folio,remision_id,estado',
                'remisionVinculada.logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'logisticaEnvios:id,factura_id,estado',
                'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
                'detalles:id,factura_id,cantidad',
            ])
            ->when($q !== '', fn ($qq) => $qq->buscar($q))
            ->orderByDesc('fecha_emision')
            ->limit(18)
            ->get();

        return response()->json($facturas->map(function ($f) {
            $permite = $f->permiteNuevoEnvioLogistica();
            $bloqueo = null;
            if (! $permite) {
                $bloqueo = ($f->remisionVinculada && $f->remisionVinculada->estado === 'entregada')
                    ? 'remision_entregada'
                    : 'envio_activo_sin_parcial_o_sin_pendientes';
            }

            return [
                'id' => $f->id,
                'label' => $f->folio_completo.' — '.($f->cliente->nombre ?? $f->nombre_receptor),
                'cliente_id' => $f->cliente_id,
                'permite_envio_logistica' => $permite,
                'bloqueo_envio_logistica' => $bloqueo,
            ];
        }));
    }

    public function buscarRemisiones(Request $request)
    {
        $q = (string) $request->get('q', '');
        $remisiones = Remision::query()
            ->whereIn('estado', ['enviada', 'entregada'])
            ->where(function ($q) {
                $q->whereDoesntHave('logisticaEnvios')
                    ->orWhereRaw('EXISTS (
                        SELECT 1 FROM remisiones_detalle rd
                        WHERE rd.remision_id = remisiones.id
                        AND rd.cantidad > COALESCE((
                            SELECT SUM(lei.cantidad) FROM logistica_envio_items lei
                            INNER JOIN logistica_envios le ON le.id = lei.logistica_envio_id AND le.estado != ?
                            WHERE lei.remision_detalle_id = rd.id AND lei.linea_entregada = 1
                        ), 0) + 0.00001
                    )', ['cancelado']);
            })
            ->when($q !== '', fn ($qq) => $qq->buscar($q))
            ->orderByDesc('created_at')
            ->limit(18)
            ->get(['id', 'folio', 'cliente_id', 'cliente_nombre', 'estado']);

        return response()->json($remisiones->map(fn ($r) => [
            'id' => $r->id,
            'label' => $r->folio.' — '.$r->cliente_nombre.' ('.$r->estado.')',
            'cliente_id' => $r->cliente_id,
        ]));
    }

    public function lineasFactura(Factura $factura)
    {
        if (! $factura->estaTimbrada()) {
            abort(404);
        }
        $factura->loadMissing([
            'detalles',
            'remisionVinculada:id,factura_id,estado',
            'remisionVinculada.logisticaEnvios:id,folio,remision_id,estado',
            'remisionVinculada.logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
            'logisticaEnvios:id,factura_id,estado',
            'logisticaEnvios.items:id,logistica_envio_id,linea_entregada',
        ]);
        if (! $factura->permiteNuevoEnvioLogistica()) {
            abort(403, 'Esta factura no admite un envío nuevo en este momento (remisión entregada vía remisión, o envío activo sin entrega parcial en destino cuando corresponde).');
        }
        $lineas = [];
        foreach ($factura->detalles as $d) {
            $enviado = LogisticaEnvio::cantidadEnviadaFacturaDetalle($d->id);
            $entregadoDestino = LogisticaEnvio::cantidadEntregadaEnDestinoFacturaDetalle($d->id);
            $pendEntrega = LogisticaEnvio::cantidadPendienteEntregaFacturaDetalle($d->id);
            $maxNuevo = LogisticaEnvio::cantidadMaximaNuevoEnvioFacturaDetalle($d->id);
            $lineas[] = [
                'factura_detalle_id' => $d->id,
                'descripcion' => $d->descripcion,
                'cantidad_facturada' => (float) $d->cantidad,
                'cantidad_enviada' => $enviado,
                'cantidad_entregada_destino' => $entregadoDestino,
                'cantidad_pendiente_entrega' => $pendEntrega,
                'cantidad_pendiente' => $maxNuevo,
                'producto_id' => $d->producto_id,
                'clave' => $d->no_identificacion,
            ];
        }

        return response()->json(['lineas' => $lineas]);
    }

    public function lineasRemision(Remision $remision)
    {
        if (! in_array($remision->estado, ['enviada', 'entregada'], true)) {
            abort(404);
        }
        $remision->loadMissing('detalles');
        $lineas = [];
        foreach ($remision->detalles as $d) {
            $enviado = LogisticaEnvio::cantidadEnviadaRemisionDetalle($d->id);
            $entregadoDestino = LogisticaEnvio::cantidadEntregadaEnDestinoRemisionDetalle($d->id);
            $pendEntrega = LogisticaEnvio::cantidadPendienteEntregaRemisionDetalle($d->id);
            $maxNuevo = LogisticaEnvio::cantidadMaximaNuevoEnvioRemisionDetalle($d->id);
            $lineas[] = [
                'remision_detalle_id' => $d->id,
                'descripcion' => $d->descripcion,
                'cantidad_remision' => (float) $d->cantidad,
                'cantidad_enviada' => $enviado,
                'cantidad_entregada_destino' => $entregadoDestino,
                'cantidad_pendiente_entrega' => $pendEntrega,
                'cantidad_pendiente' => $maxNuevo,
                'producto_id' => $d->producto_id,
                'codigo' => $d->codigo,
            ];
        }

        return response()->json(['lineas' => $lineas]);
    }

    public function direccionesCliente(Cliente $cliente)
    {
        $dirs = $cliente->direccionesEntrega()
            ->where('activo', true)
            ->orderBy('id')
            ->get(['id', 'sucursal_almacen', 'direccion_completa']);

        return response()->json($dirs);
    }

    public function verPdf(LogisticaEnvio $envio)
    {
        $path = app(PDFService::class)->generarLogisticaEnvioPdf($envio);

        return response()->file(storage_path('app/'.$path));
    }

    public function descargarPdf(LogisticaEnvio $envio)
    {
        $path = app(PDFService::class)->generarLogisticaEnvioPdf($envio);
        $filename = 'Logistica_'.preg_replace('/[^A-Za-z0-9_-]/', '_', $envio->folio).'.pdf';

        return response()->download(storage_path('app/'.$path), $filename);
    }

    /**
     * Al crear un envío nuevo con remisión, descuenta cantidades de partidas aún sin entregar en
     * envíos anteriores (evita duplicar totales físicos y libera el cupo para el nuevo viaje).
     */
    /**
     * Al crear un envío nuevo con factura, descuenta cantidades de partidas aún sin entregar en
     * envíos anteriores de la misma factura (misma lógica que remisión).
     */
    private function descontarFacturaItemsNoEntregadosHaciaNuevoEnvio(LogisticaEnvio $envioNuevo, Factura $factura, array $itemRows): void
    {
        foreach ($itemRows as $row) {
            $qty = (float) ($row['cantidad'] ?? 0);
            if ($qty <= 1e-9) {
                continue;
            }
            $detId = (int) $row['factura_detalle_id'];
            $disponibleEnOtros = (float) LogisticaEnvioItem::query()
                ->where('factura_detalle_id', $detId)
                ->whereHas('envio', function ($q) use ($factura, $envioNuevo) {
                    $q->where('factura_id', $factura->id)
                        ->where('estado', '!=', 'cancelado')
                        ->where('id', '!=', $envioNuevo->id);
                })
                ->where('linea_entregada', false)
                ->sum('cantidad');
            $aDescontar = min($qty, $disponibleEnOtros);
            if ($aDescontar <= 1e-9) {
                continue;
            }
            $remaining = $aDescontar;
            $candidatos = LogisticaEnvioItem::query()
                ->where('factura_detalle_id', $detId)
                ->whereHas('envio', function ($q) use ($factura, $envioNuevo) {
                    $q->where('factura_id', $factura->id)
                        ->where('estado', '!=', 'cancelado')
                        ->where('id', '!=', $envioNuevo->id);
                })
                ->where('linea_entregada', false)
                ->orderBy('id')
                ->get();
            foreach ($candidatos as $oldItem) {
                if ($remaining <= 1e-9) {
                    break;
                }
                $take = min((float) $oldItem->cantidad, $remaining);
                $oldItem->cantidad = round((float) $oldItem->cantidad - $take, 4);
                $remaining -= $take;
                if ($oldItem->cantidad <= 1e-9) {
                    $oldItem->delete();
                } else {
                    $oldItem->save();
                }
            }
        }
    }

    private function descontarRemisionItemsNoEntregadosHaciaNuevoEnvio(LogisticaEnvio $envioNuevo, Remision $remision, array $itemRows): void
    {
        foreach ($itemRows as $row) {
            $qty = (float) ($row['cantidad'] ?? 0);
            if ($qty <= 1e-9) {
                continue;
            }
            $detId = (int) $row['remision_detalle_id'];
            $disponibleEnOtros = (float) LogisticaEnvioItem::query()
                ->where('remision_detalle_id', $detId)
                ->whereHas('envio', function ($q) use ($remision, $envioNuevo) {
                    $q->where('remision_id', $remision->id)
                        ->where('estado', '!=', 'cancelado')
                        ->where('id', '!=', $envioNuevo->id);
                })
                ->where('linea_entregada', false)
                ->sum('cantidad');
            $aDescontar = min($qty, $disponibleEnOtros);
            if ($aDescontar <= 1e-9) {
                continue;
            }
            $remaining = $aDescontar;
            $candidatos = LogisticaEnvioItem::query()
                ->where('remision_detalle_id', $detId)
                ->whereHas('envio', function ($q) use ($remision, $envioNuevo) {
                    $q->where('remision_id', $remision->id)
                        ->where('estado', '!=', 'cancelado')
                        ->where('id', '!=', $envioNuevo->id);
                })
                ->where('linea_entregada', false)
                ->orderBy('id')
                ->get();
            foreach ($candidatos as $oldItem) {
                if ($remaining <= 1e-9) {
                    break;
                }
                $take = min((float) $oldItem->cantidad, $remaining);
                $oldItem->cantidad = round((float) $oldItem->cantidad - $take, 4);
                $remaining -= $take;
                if ($oldItem->cantidad <= 1e-9) {
                    $oldItem->delete();
                } else {
                    $oldItem->save();
                }
            }
        }
    }

    private function authorizeCrear(): void
    {
        abort_unless(auth()->user()?->can('logistica.crear'), 403);
    }

    private function authorizeEditar(): void
    {
        abort_unless(auth()->user()?->can('logistica.editar'), 403);
    }
}
