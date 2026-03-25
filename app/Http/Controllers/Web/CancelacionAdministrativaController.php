<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Factura;
use App\Services\CancelacionAdministrativaFacturaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CancelacionAdministrativaController extends Controller
{
    public function index(Request $request, CancelacionAdministrativaFacturaService $service): View
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

        return view('cancelaciones-administrativas.index', compact('facturas'));
    }

    public function ejecutar(Request $request, Factura $factura, CancelacionAdministrativaFacturaService $service): RedirectResponse
    {
        $request->validate([
            'motivo' => 'required|string|min:10|max:2000',
        ]);

        try {
            $service->ejecutar($factura, $request->input('motivo'), $request);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            return redirect()
                ->route('cancelaciones-administrativas.index', $request->only(['fecha_desde', 'fecha_hasta', 'q', 'page']))
                ->with('error', 'No se pudo cancelar: '.$e->getMessage());
        }

        return redirect()
            ->route('facturas.show', $factura->id)
            ->with('success', 'Factura cancelada administrativamente en el ERP. Revise auditoría y saldos del cliente.');
    }
}
