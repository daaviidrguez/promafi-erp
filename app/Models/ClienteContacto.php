<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\ClienteContacto;
use Illuminate\Http\Request;

class ClienteContactoController extends Controller
{
    public function create(Cliente $cliente)
    {
        return view('cliente_contactos.create', compact('cliente'));
    }

    public function store(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:15',
            'celular' => 'nullable|string|max:15',
            'puesto' => 'nullable|string|max:255',
            'departamento' => 'nullable|string|max:255',
            'principal' => 'boolean',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
        ]);

        if (!empty($validated['principal'])) {
            $cliente->contactos()->update(['principal' => false]);
        }

        $cliente->contactos()->create($validated);

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'Contacto agregado correctamente');
    }

    public function edit(Cliente $cliente, ClienteContacto $contacto)
    {
        return view('cliente_contactos.edit', compact('cliente', 'contacto'));
    }

    public function update(Request $request, Cliente $cliente, ClienteContacto $contacto)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:15',
            'celular' => 'nullable|string|max:15',
            'puesto' => 'nullable|string|max:255',
            'departamento' => 'nullable|string|max:255',
            'principal' => 'boolean',
            'activo' => 'boolean',
            'notas' => 'nullable|string',
        ]);

        if (!empty($validated['principal'])) {
            $cliente->contactos()->update(['principal' => false]);
        }

        $contacto->update($validated);

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'Contacto actualizado correctamente');
    }

    public function destroy(Cliente $cliente, ClienteContacto $contacto)
    {
        $contacto->delete();

        return redirect()
            ->route('clientes.show', $cliente)
            ->with('success', 'Contacto eliminado correctamente');
    }
}