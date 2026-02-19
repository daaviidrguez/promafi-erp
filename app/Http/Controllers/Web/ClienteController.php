<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/ClienteController.php

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\Request;

class ClienteController extends Controller
{
    /**
     * Mostrar lista de clientes
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        
        $clientes = Cliente::query()
            ->when($search, function($query) use ($search) {
                $query->buscar($search);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('clientes.index', compact('clientes', 'search'));
    }

    /**
     * Mostrar formulario de crear cliente
     */
    public function create()
    {
        return view('clientes.create');
    }

    /**
     * Guardar nuevo cliente
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'required|string|size:13|unique:clientes,rfc',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:15',
            'regimen_fiscal' => 'nullable|string|max:3',
            'uso_cfdi_default' => 'required|string|max:3',
            'dias_credito' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
        ]);

        $validated['rfc'] = cleanRFC($validated['rfc']);
        $validated['activo'] = true;

        $cliente = Cliente::create($validated);

        return redirect()->route('clientes.show', $cliente->id)
            ->with('success', 'Cliente creado exitosamente');
    }

    /**
     * Mostrar detalle de cliente
     */
    public function show(Cliente $cliente)
    {
        $cliente->load([
            'facturas' => function($q) {
                $q->latest()->limit(10);
            },
            'contactos' => function($q) {
                $q->orderByDesc('principal');
            }
        ]);

        return view('clientes.show', compact('cliente'));
    }

    /**
     * Mostrar formulario de editar
     */
    public function edit(Cliente $cliente)
    {
        return view('clientes.edit', compact('cliente'));
    }

    /**
     * Actualizar cliente
     */
    public function update(Request $request, Cliente $cliente)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'rfc' => 'required|string|size:13|unique:clientes,rfc,' . $cliente->id,
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:15',
            'regimen_fiscal' => 'nullable|string|max:3',
            'uso_cfdi_default' => 'required|string|max:3',
            'dias_credito' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
            'activo' => 'boolean',
        ]);

        $validated['rfc'] = cleanRFC($validated['rfc']);

        $cliente->update($validated);

        return redirect()->route('clientes.show', $cliente->id)
            ->with('success', 'Cliente actualizado exitosamente');
    }

    /**
     * Eliminar cliente (soft delete)
     */
    public function destroy(Cliente $cliente)
    {
        $cliente->delete();

        return redirect()->route('clientes.index')
            ->with('success', 'Cliente eliminado exitosamente');
    }
}