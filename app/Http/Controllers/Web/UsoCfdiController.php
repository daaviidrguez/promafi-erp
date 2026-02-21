<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UsoCfdi;
use Illuminate\Http\Request;

class UsoCfdiController extends Controller
{
    public function index()
    {
        $items = UsoCfdi::orderBy('orden')->orderBy('clave')->paginate(50);
        return view('catalogos-sat.usos-cfdi.index', compact('items'));
    }

    public function create()
    {
        return view('catalogos-sat.usos-cfdi.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:usos_cfdi,clave',
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        UsoCfdi::create($validated);
        return redirect()->route('catalogos-sat.usos-cfdi.index')
            ->with('success', 'Uso de CFDI creado.');
    }

    public function edit(UsoCfdi $usos_cfdi)
    {
        $item = $usos_cfdi;
        return view('catalogos-sat.usos-cfdi.edit', compact('item'));
    }

    public function update(Request $request, UsoCfdi $usos_cfdi)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:10|unique:usos_cfdi,clave,' . $usos_cfdi->id,
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        $usos_cfdi->update($validated);
        return redirect()->route('catalogos-sat.usos-cfdi.index')
            ->with('success', 'Uso de CFDI actualizado.');
    }

    public function destroy(UsoCfdi $usos_cfdi)
    {
        $usos_cfdi->delete();
        return redirect()->route('catalogos-sat.usos-cfdi.index')
            ->with('success', 'Uso de CFDI eliminado.');
    }
}
