<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CatalogoTruper;
use App\Services\CatalogoTruperImportService;
use Illuminate\Http\Request;

class CatalogoTruperController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $items = CatalogoTruper::query()
            ->when($search, fn ($q) => $q->buscar($search))
            ->orderBy('codigo')
            ->paginate(15);

        if ($request->boolean('imported') && ! session()->has('success')) {
            session()->flash('success', sprintf(
                'Importación completada: %s creados, %s actualizados, %s omitidos.',
                number_format((int) $request->get('c', 0)),
                number_format((int) $request->get('a', 0)),
                number_format((int) $request->get('o', 0))
            ));
        }

        return view('catalogo-truper.index', compact('items', 'search'));
    }

    /**
     * Importa un lote de hasta 500 filas (AJAX, progreso en frontend).
     */
    public function importarLote(Request $request, CatalogoTruperImportService $service)
    {
        if (! auth()->user()->can('catalogo_truper.importar') && ! auth()->user()->isAdmin()) {
            abort(403, 'No tienes permiso para importar el catálogo Truper.');
        }

        $validated = $request->validate([
            'items' => 'required|array|min:1|max:500',
            'items.*.codigo' => 'nullable',
            'items.*.clave' => 'nullable',
            'items.*.descripcion' => 'nullable|string',
            'items.*.unidad' => 'nullable|string|max:20',
            'items.*.costo' => 'nullable|numeric',
            'items.*.venta' => 'nullable|numeric',
            'items.*.codigo_sat' => 'nullable',
            'items.*.peso_kg' => 'nullable|numeric',
            'items.*.volumen_cm3' => 'nullable|numeric',
        ]);

        $result = $service->upsertFilas($validated['items']);

        return response()->json([
            'ok' => true,
            'creados' => $result['creados'],
            'actualizados' => $result['actualizados'],
            'omitidos' => $result['omitidos'],
            'procesados' => count($validated['items']),
        ]);
    }
}
