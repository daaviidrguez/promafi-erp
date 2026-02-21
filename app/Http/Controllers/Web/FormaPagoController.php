<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\FormaPago;
use Illuminate\Http\Request;

class FormaPagoController extends Controller
{
    public function index()
    {
        $items = FormaPago::orderBy('orden')->orderBy('clave')->paginate(50);
        return view('catalogos-sat.formas-pago.index', compact('items'));
    }

    public function create()
    {
        return view('catalogos-sat.formas-pago.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:5|unique:formas_pago,clave',
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        FormaPago::create($validated);
        return redirect()->route('catalogos-sat.formas-pago.index')
            ->with('success', 'Forma de pago creada.');
    }

    public function edit(FormaPago $formas_pago)
    {
        $item = $formas_pago;
        return view('catalogos-sat.formas-pago.edit', compact('item'));
    }

    public function update(Request $request, FormaPago $formas_pago)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:5|unique:formas_pago,clave,' . $formas_pago->id,
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        $formas_pago->update($validated);
        return redirect()->route('catalogos-sat.formas-pago.index')
            ->with('success', 'Forma de pago actualizada.');
    }

    public function destroy(FormaPago $formas_pago)
    {
        $formas_pago->delete();
        return redirect()->route('catalogos-sat.formas-pago.index')
            ->with('success', 'Forma de pago eliminada.');
    }
}
