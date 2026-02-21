<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\MetodoPago;
use Illuminate\Http\Request;

class MetodoPagoController extends Controller
{
    public function index()
    {
        $items = MetodoPago::orderBy('orden')->orderBy('clave')->paginate(50);
        return view('catalogos-sat.metodos-pago.index', compact('items'));
    }

    public function create()
    {
        return view('catalogos-sat.metodos-pago.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:5|unique:metodos_pago,clave',
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        MetodoPago::create($validated);
        return redirect()->route('catalogos-sat.metodos-pago.index')
            ->with('success', 'Método de pago creado.');
    }

    public function edit(MetodoPago $metodos_pago)
    {
        $item = $metodos_pago;
        return view('catalogos-sat.metodos-pago.edit', compact('item'));
    }

    public function update(Request $request, MetodoPago $metodos_pago)
    {
        $validated = $request->validate([
            'clave' => 'required|string|max:5|unique:metodos_pago,clave,' . $metodos_pago->id,
            'descripcion' => 'required|string|max:255',
            'orden' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo');
        $metodos_pago->update($validated);
        return redirect()->route('catalogos-sat.metodos-pago.index')
            ->with('success', 'Método de pago actualizado.');
    }

    public function destroy(MetodoPago $metodos_pago)
    {
        $metodos_pago->delete();
        return redirect()->route('catalogos-sat.metodos-pago.index')
            ->with('success', 'Método de pago eliminado.');
    }
}
