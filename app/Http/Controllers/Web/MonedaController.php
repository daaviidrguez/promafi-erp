<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Moneda;
use Illuminate\Http\Request;

class MonedaController extends Controller
{
    public function index()
    {
        $items = Moneda::orderBy('orden')->orderBy('clave')->paginate(50);
        return view('catalogos-sat.monedas.index', compact('items'));
    }

    public function create()
    {
        return view('catalogos-sat.monedas.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:monedas,clave',
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        Moneda::create($validated);
        return redirect()->route('catalogos-sat.monedas.index')
            ->with('success', 'Moneda creada.');
    }

    public function edit(Moneda $moneda)
    {
        $item = $moneda;
        return view('catalogos-sat.monedas.edit', compact('item'));
    }

    public function update(Request $request, Moneda $moneda)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:monedas,clave,' . $moneda->id,
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        $moneda->update($validated);
        return redirect()->route('catalogos-sat.monedas.index')
            ->with('success', 'Moneda actualizada.');
    }

    public function destroy(Moneda $moneda)
    {
        $moneda->delete();
        return redirect()->route('catalogos-sat.monedas.index')
            ->with('success', 'Moneda eliminada.');
    }
}
