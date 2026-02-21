<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UnidadMedidaSat;
use Illuminate\Http\Request;

class UnidadMedidaSatController extends Controller
{
    public function index()
    {
        $items = UnidadMedidaSat::orderBy('orden')->orderBy('clave')->paginate(50);
        return view('catalogos-sat.unidades-medida.index', compact('items'));
    }

    public function create()
    {
        return view('catalogos-sat.unidades-medida.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:unidades_medida_sat,clave',
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        UnidadMedidaSat::create($validated);
        return redirect()->route('catalogos-sat.unidades-medida.index')
            ->with('success', 'Unidad de medida creada.');
    }

    public function edit(UnidadMedidaSat $unidades_medida)
    {
        $item = $unidades_medida;
        return view('catalogos-sat.unidades-medida.edit', compact('item'));
    }

    public function update(Request $request, UnidadMedidaSat $unidades_medida)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:unidades_medida_sat,clave,' . $unidades_medida->id,
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        $unidades_medida->update($validated);
        return redirect()->route('catalogos-sat.unidades-medida.index')
            ->with('success', 'Unidad de medida actualizada.');
    }

    public function destroy(UnidadMedidaSat $unidades_medida)
    {
        $unidades_medida->delete();
        return redirect()->route('catalogos-sat.unidades-medida.index')
            ->with('success', 'Unidad de medida eliminada.');
    }
}
