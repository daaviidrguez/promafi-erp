<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Models\FacturaCancelacionAdministrativa;
use App\Services\CancelacionAdministrativaFacturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class CancelacionAdministrativaController extends Controller
{
    /**
     * Historial de cancelaciones administrativas (operaciones realizadas).
     */
    public function index(Request $request): View
    {
        $query = FacturaCancelacionAdministrativa::query()
            ->soloCancelaciones()
            ->with(['factura.cliente', 'user']);

        if ($request->filled('fecha_desde')) {
            $query->whereDate('created_at', '>=', $request->date('fecha_desde')->format('Y-m-d'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('created_at', '<=', $request->date('fecha_hasta')->format('Y-m-d'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->get('user_id'));
        }

        if ($request->filled('q')) {
            $q = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->get('q'))).'%';
            $query->where(function ($qry) use ($q) {
                $qry->where('motivo', 'like', $q)
                    ->orWhereHas('factura', function ($f) use ($q) {
                        $f->where('uuid', 'like', $q)
                            ->orWhere('serie', 'like', $q)
                            ->orWhere('folio', 'like', $q)
                            ->orWhere('nombre_receptor', 'like', $q)
                            ->orWhere('rfc_receptor', 'like', $q)
                            ->orWhereHas('cliente', function ($c) use ($q) {
                                $c->where('nombre', 'like', $q)->orWhere('rfc', 'like', $q);
                            });
                    });
            });
        }

        $estadoFiltro = $request->get('estado');
        if (in_array($estadoFiltro, ['vigente', 'revertida'], true)) {
            if ($estadoFiltro === 'vigente') {
                $query->whereNotExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('factura_cancelaciones_administrativas as rev')
                        ->whereColumn('rev.factura_id', 'factura_cancelaciones_administrativas.factura_id')
                        ->where('rev.tipo', FacturaCancelacionAdministrativa::TIPO_REVERSION)
                        ->whereColumn('rev.id', '>', 'factura_cancelaciones_administrativas.id');
                });
            } else {
                $query->whereExists(function ($sub) {
                    $sub->selectRaw('1')
                        ->from('factura_cancelaciones_administrativas as rev')
                        ->whereColumn('rev.factura_id', 'factura_cancelaciones_administrativas.factura_id')
                        ->where('rev.tipo', FacturaCancelacionAdministrativa::TIPO_REVERSION)
                        ->whereColumn('rev.id', '>', 'factura_cancelaciones_administrativas.id');
                });
            }
        }

        $cancelaciones = $query
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $this->anotarEstadoOperacion($cancelaciones->getCollection());

        $inicioMes = Carbon::now()->startOfMonth();
        $finMes = Carbon::now()->endOfMonth();

        $cancelacionesMes = FacturaCancelacionAdministrativa::query()
            ->soloCancelaciones()
            ->whereBetween('created_at', [$inicioMes, $finMes])
            ->count();

        $vigentes = FacturaCancelacionAdministrativa::query()
            ->soloCancelaciones()
            ->whereNotExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('factura_cancelaciones_administrativas as rev')
                    ->whereColumn('rev.factura_id', 'factura_cancelaciones_administrativas.factura_id')
                    ->where('rev.tipo', FacturaCancelacionAdministrativa::TIPO_REVERSION)
                    ->whereColumn('rev.id', '>', 'factura_cancelaciones_administrativas.id');
            })
            ->count();

        $revertidasMes = FacturaCancelacionAdministrativa::query()
            ->where('tipo', FacturaCancelacionAdministrativa::TIPO_REVERSION)
            ->whereBetween('created_at', [$inicioMes, $finMes])
            ->count();

        $montoMes = (float) FacturaCancelacionAdministrativa::query()
            ->soloCancelaciones()
            ->whereBetween('created_at', [$inicioMes, $finMes])
            ->get(['detalle'])
            ->sum(fn (FacturaCancelacionAdministrativa $c) => (float) data_get($c->detalle, 'total', 0));

        $estadisticas = [
            'cancelaciones_mes' => $cancelacionesMes,
            'vigentes' => $vigentes,
            'revertidas_mes' => $revertidasMes,
            'monto_mes' => $montoMes,
        ];

        $usuariosFiltro = FacturaCancelacionAdministrativa::query()
            ->soloCancelaciones()
            ->whereNotNull('user_id')
            ->with('user:id,name')
            ->select('user_id')
            ->distinct()
            ->get()
            ->pluck('user')
            ->filter()
            ->sortBy('name')
            ->values();

        return view('cancelaciones-administrativas.index', compact(
            'cancelaciones',
            'estadisticas',
            'usuariosFiltro'
        ));
    }

    /**
     * Workspace para registrar una nueva cancelación administrativa.
     */
    public function create(Request $request, CancelacionAdministrativaFacturaService $service): View
    {
        $query = Factura::query()
            ->with(['cliente', 'cuentaPorCobrar'])
            ->where('estado', 'timbrada');

        if ($request->filled('fecha_desde')) {
            $query->whereDate('fecha_emision', '>=', $request->date('fecha_desde')->format('Y-m-d'));
        }
        if ($request->filled('fecha_hasta')) {
            $query->whereDate('fecha_emision', '<=', $request->date('fecha_hasta')->format('Y-m-d'));
        }

        if ($request->filled('q')) {
            $q = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim((string) $request->get('q'))).'%';
            $query->where(function ($qry) use ($q) {
                $qry->where('uuid', 'like', $q)
                    ->orWhere('serie', 'like', $q)
                    ->orWhere('folio', 'like', $q)
                    ->orWhere('nombre_receptor', 'like', $q)
                    ->orWhere('rfc_receptor', 'like', $q)
                    ->orWhereHas('cliente', function ($c) use ($q) {
                        $c->where('nombre', 'like', $q)->orWhere('rfc', 'like', $q);
                    });
            });
        }

        $facturas = $query
            ->orderByDesc('folio')
            ->orderByDesc('serie')
            ->paginate(25)
            ->withQueryString();

        $facturas->getCollection()->transform(function (Factura $f) use ($service) {
            $check = $service->puedeEjecutar($f);
            $f->setAttribute('puede_cancelar_admin', $check['ok']);
            $f->setAttribute('motivo_no_cancelar_admin', $check['mensaje']);

            return $f;
        });

        $facturaSeleccionada = null;
        $checkSeleccionado = null;
        if ($request->filled('factura_id')) {
            $facturaSeleccionada = Factura::with(['cliente', 'cuentaPorCobrar', 'detalles.producto'])
                ->find($request->integer('factura_id'));
            if ($facturaSeleccionada) {
                $checkSeleccionado = $service->puedeEjecutar($facturaSeleccionada);
            }
        }

        return view('cancelaciones-administrativas.create', compact(
            'facturas',
            'facturaSeleccionada',
            'checkSeleccionado'
        ));
    }

    /**
     * Expediente de una cancelación administrativa.
     */
    public function show(
        FacturaCancelacionAdministrativa $cancelacion,
        CancelacionAdministrativaFacturaService $service
    ): View|RedirectResponse {
        if ($cancelacion->esReversion()) {
            $origen = FacturaCancelacionAdministrativa::query()
                ->soloCancelaciones()
                ->where('factura_id', $cancelacion->factura_id)
                ->where('id', '<', $cancelacion->id)
                ->orderByDesc('id')
                ->first();

            if ($origen) {
                return redirect()->route('cancelaciones-administrativas.show', $origen);
            }
        }

        $cancelacion->load(['factura.cliente', 'factura.cuentaPorCobrar', 'user']);

        $timeline = FacturaCancelacionAdministrativa::query()
            ->with('user')
            ->where('factura_id', $cancelacion->factura_id)
            ->orderBy('id')
            ->get();

        $reversion = $cancelacion->reversionPosterior();
        $estado = $cancelacion->estadoOperacion();

        $puedeReactivar = false;
        $motivoNoReactivar = null;
        if ($estado === 'vigente' && $cancelacion->factura) {
            $check = $service->puedeReactivar($cancelacion->factura);
            $puedeReactivar = $check['ok'];
            $motivoNoReactivar = $check['mensaje'];
        }

        $detalle = is_array($cancelacion->detalle) ? $cancelacion->detalle : [];

        return view('cancelaciones-administrativas.show', compact(
            'cancelacion',
            'timeline',
            'reversion',
            'estado',
            'puedeReactivar',
            'motivoNoReactivar',
            'detalle'
        ));
    }

    public function store(Request $request, CancelacionAdministrativaFacturaService $service): RedirectResponse
    {
        $request->validate([
            'factura_id' => 'required|integer|exists:facturas,id',
            'motivo' => 'required|string|min:10|max:2000',
        ]);

        $factura = Factura::findOrFail($request->integer('factura_id'));

        try {
            $service->ejecutar($factura, $request->input('motivo'), $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('cancelaciones-administrativas.create', array_filter([
                    'factura_id' => $factura->id,
                    'q' => $request->get('q'),
                    'fecha_desde' => $request->get('fecha_desde'),
                    'fecha_hasta' => $request->get('fecha_hasta'),
                ]))
                ->withInput()
                ->with('error', 'No se pudo cancelar: '.$e->getMessage());
        }

        $registro = FacturaCancelacionAdministrativa::query()
            ->soloCancelaciones()
            ->where('factura_id', $factura->id)
            ->orderByDesc('id')
            ->first();

        return redirect()
            ->route('cancelaciones-administrativas.show', $registro)
            ->with('success', 'Factura cancelada administrativamente en el ERP. Revise el expediente, auditoría y saldos del cliente.');
    }

    /**
     * @deprecated Preferir store(); se mantiene por compatibilidad de formularios antiguos.
     */
    public function ejecutar(Request $request, Factura $factura, CancelacionAdministrativaFacturaService $service): RedirectResponse
    {
        $request->merge(['factura_id' => $factura->id]);

        return $this->store($request, $service);
    }

    public function revertir(Request $request, Factura $factura, CancelacionAdministrativaFacturaService $service): RedirectResponse
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:2000',
        ]);

        $cancelacionOrigen = FacturaCancelacionAdministrativa::query()
            ->soloCancelaciones()
            ->where('factura_id', $factura->id)
            ->orderByDesc('id')
            ->first();

        try {
            $service->revertir($factura, $request->input('motivo'), $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            if ($cancelacionOrigen) {
                return redirect()
                    ->route('cancelaciones-administrativas.show', $cancelacionOrigen)
                    ->with('error', 'No se pudo reactivar: '.$e->getMessage());
            }

            return redirect()
                ->route('facturas.show', $factura->id)
                ->with('error', 'No se pudo reactivar: '.$e->getMessage());
        }

        if ($cancelacionOrigen) {
            return redirect()
                ->route('cancelaciones-administrativas.show', $cancelacionOrigen)
                ->with('success', 'Cancelación administrativa revertida. La factura volvió a timbrada en el ERP; el CFDI nunca se canceló ante el SAT.');
        }

        return redirect()
            ->route('facturas.show', $factura->id)
            ->with('success', 'Cancelación administrativa revertida. La factura volvió a timbrada en el ERP; el CFDI nunca se canceló ante el SAT.');
    }

    /**
     * Evita N+1 al calcular vigente/revertida en el listado.
     *
     * @param  \Illuminate\Support\Collection<int, FacturaCancelacionAdministrativa>  $items
     */
    protected function anotarEstadoOperacion($items): void
    {
        if ($items->isEmpty()) {
            return;
        }

        $facturaIds = $items->pluck('factura_id')->unique()->values();
        $reversiones = FacturaCancelacionAdministrativa::query()
            ->whereIn('factura_id', $facturaIds)
            ->where('tipo', FacturaCancelacionAdministrativa::TIPO_REVERSION)
            ->orderBy('id')
            ->get()
            ->groupBy('factura_id');

        foreach ($items as $item) {
            $rev = ($reversiones->get($item->factura_id) ?? collect())
                ->first(fn (FacturaCancelacionAdministrativa $r) => $r->id > $item->id);
            $item->setAttribute('estado_operacion_attr', $rev ? 'revertida' : 'vigente');
        }
    }
}
