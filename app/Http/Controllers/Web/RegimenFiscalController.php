<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\RegimenFiscal;
use Illuminate\Http\Request;

class RegimenFiscalController extends Controller
{
    public function index()
    {
        $items = RegimenFiscal::orderBy('orden')->orderBy('clave')->paginate(50);
        return view('catalogos-sat.regimenes-fiscales.index', compact('items'));
    }

    public function create()
    {
        return view('catalogos-sat.regimenes-fiscales.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:regimenes_fiscales,clave',
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        RegimenFiscal::create($validated);
        return redirect()->route('catalogos-sat.regimenes-fiscales.index')
            ->with('success', 'Régimen fiscal creado.');
    }

    public function edit(RegimenFiscal $regimenes_fiscale)
    {
        $item = $regimenes_fiscale;
        return view('catalogos-sat.regimenes-fiscales.edit', compact('item'));
    }

    public function update(Request $request, RegimenFiscal $regimenes_fiscale)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:regimenes_fiscales,clave,' . $regimenes_fiscale->id,
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        $regimenes_fiscale->update($validated);
        return redirect()->route('catalogos-sat.regimenes-fiscales.index')
            ->with('success', 'Régimen fiscal actualizado.');
    }

    public function destroy(RegimenFiscal $regimenes_fiscale)
    {
        $regimenes_fiscale->delete();
        return redirect()->route('catalogos-sat.regimenes-fiscales.index')
            ->with('success', 'Régimen fiscal eliminado.');
    }
}
