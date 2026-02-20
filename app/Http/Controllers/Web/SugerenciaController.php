<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Sugerencia;
use Illuminate\Http\Request;

class SugerenciaController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $sugerencias = Sugerencia::query()
            ->when($search, fn ($q) => $q->buscar($search))
            ->orderBy('descripcion')
            ->paginate(15);
        return view('sugerencias.index', compact('sugerencias', 'search'));
    }

    public function create()
    {
        return view('sugerencias.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'codigo' => 'nullable|string|max:50',
            'descripcion' => 'required|string',
            'unidad' => 'nullable|string|max:10',
            'precio_unitario' => 'required|numeric|min:0',
        ]);
        $validated['unidad'] = $validated['unidad'] ?? 'PZA';
        Sugerencia::create($validated);
        return redirect()->route('sugerencias.index')->with('success', 'Sugerencia creada exitosamente');
    }

    public function show(Sugerencia $sugerencia)
    {
        return view('sugerencias.show', compact('sugerencia'));
    }

    public function edit(Sugerencia $sugerencia)
    {
        return view('sugerencias.edit', compact('sugerencia'));
    }

    public function update(Request $request, Sugerencia $sugerencia)
    {
        $validated = $request->validate([
            'codigo' => 'nullable|string|max:50',
            'descripcion' => 'required|string',
            'unidad' => 'nullable|string|max:10',
            'precio_unitario' => 'required|numeric|min:0',
        ]);
        $validated['unidad'] = $validated['unidad'] ?? 'PZA';
        $sugerencia->update($validated);
        return redirect()->route('sugerencias.show', $sugerencia->id)->with('success', 'Sugerencia actualizada');
    }

    public function destroy(Sugerencia $sugerencia)
    {
        $sugerencia->delete();
        return redirect()->route('sugerencias.index')->with('success', 'Sugerencia eliminada');
    }

    /**
     * Búsqueda para autocompletado en cotizaciones (productos manuales).
     * Mínimo 3 caracteres. Devuelve JSON.
     */
    public function buscar(Request $request)
    {
        $q = $request->get('q', '');
        if (strlen(trim($q)) < 3) {
            return response()->json([]);
        }
        $items = Sugerencia::buscar(trim($q))
            ->orderBy('descripcion')
            ->limit(15)
            ->get(['id', 'codigo', 'descripcion', 'unidad', 'precio_unitario']);
        return response()->json($items);
    }
}
