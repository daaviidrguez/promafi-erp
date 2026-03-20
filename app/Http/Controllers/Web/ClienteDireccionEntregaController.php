<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteDireccionEntrega;
use Illuminate\Http\Request;

class ClienteDireccionEntregaController extends Controller
{
    public function index(Cliente $cliente)
    {
        $direcciones = $cliente->direccionesEntrega()
            ->where('activo', true)
            ->orderBy('id')
            ->get(['id', 'sucursal_almacen', 'direccion_completa', 'activo']);

        return response()->json([
            'success' => true,
            'direcciones' => $direcciones,
        ]);
    }

    public function store(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'sucursal_almacen' => 'required|string|max:255',
            'direccion_completa' => 'required|string|max:2000',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo', true);

        $dir = $cliente->direccionesEntrega()->create($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'id' => $dir->id,
            ]);
        }

        return redirect()->route('clientes.show', $cliente)->with('success', 'Dirección de entrega agregada correctamente');
    }

    public function update(Request $request, Cliente $cliente, ClienteDireccionEntrega $direccionEntrega)
    {
        // Seguridad: la dirección debe pertenecer al cliente actual
        if ((int) $direccionEntrega->cliente_id !== (int) $cliente->id) {
            abort(403);
        }

        $validated = $request->validate([
            'sucursal_almacen' => 'required|string|max:255',
            'direccion_completa' => 'required|string|max:2000',
            'activo' => 'boolean',
        ]);

        $validated['activo'] = $request->boolean('activo', true);

        $direccionEntrega->update($validated);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'id' => $direccionEntrega->id,
            ]);
        }

        return redirect()->route('clientes.show', $cliente)->with('success', 'Dirección de entrega actualizada correctamente');
    }

    public function destroy(Request $request, Cliente $cliente, ClienteDireccionEntrega $direccionEntrega)
    {
        // Seguridad: la dirección debe pertenecer al cliente actual
        if ((int) $direccionEntrega->cliente_id !== (int) $cliente->id) {
            abort(403);
        }

        $direccionEntrega->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('clientes.show', $cliente)->with('success', 'Dirección de entrega eliminada correctamente');
    }
}

