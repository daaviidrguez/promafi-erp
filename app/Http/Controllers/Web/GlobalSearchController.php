<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Factura;
use App\Models\Cotizacion;


class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim($request->get('q'));

        if (!$query || strlen($query) < 2) {
            return response()->json([
                'facturas' => [],
                'cotizaciones' => [],
            ]);
        }

        try {

            $facturas = Factura::with('cliente')
                ->buscar($query)
                ->orderBy('fecha_emision', 'desc')
                ->limit(8)
                ->get()
                ->map(function ($f) {
                    return [
                        'label'  => 'Factura ' . $f->folio_completo . ' - ' . $f->nombre_receptor,
                        'url'    => route('facturas.show', $f->id),
                        'estado' => $f->estado,
                        'tipo'   => 'factura'
                    ];
                });

            $cotizaciones = Cotizacion::with('usuario')
                ->buscar($query)
                ->orderBy('fecha', 'desc')
                ->limit(8)
                ->get()
                ->map(function ($c) {
                    return [
                        'label'  => 'Cotización ' . $c->folio . ' - ' . $c->cliente_nombre,
                        'url'    => route('cotizaciones.show', $c->id),
                        'estado' => $c->estado,
                        'tipo'   => 'cotizacion'
                    ];
                });

            return response()->json([
                'facturas' => $facturas,
                'cotizaciones' => $cotizaciones,
            ]);

        } catch (\Throwable $e) {

            return response()->json([
                'facturas' => [],
                'cotizaciones' => [],
                'error' => $e->getMessage()
            ], 500);
        }
    }
}