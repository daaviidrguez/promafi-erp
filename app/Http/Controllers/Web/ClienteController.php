<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/ClienteController.php

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use App\Models\RegimenFiscal;
use App\Models\UsoCfdi;
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
        $regimenes = RegimenFiscal::activos()->get();
        $usosCfdi = UsoCfdi::activos()->get();
        return view('clientes.create', compact('regimenes', 'usosCfdi'));
    }

    /**
     * Guardar nuevo cliente
     */
    public function store(Request $request)
    {
        $rfcSize = $request->input('tipo_persona') === 'moral' ? 12 : 13;
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'tipo_persona' => 'required|in:fisica,moral',
            'rfc' => [
                'required',
                'string',
                'size:' . $rfcSize,
                'unique:clientes,rfc',
            ],
            'regimen_fiscal' => 'nullable|string|exists:regimenes_fiscales,clave',
            'uso_cfdi_default' => 'required|string|exists:usos_cfdi,clave',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:15',
            'celular' => 'nullable|string|max:15',
            'contacto_nombre' => 'nullable|string|max:255',
            'contacto_puesto' => 'nullable|string|max:100',
            'calle' => 'nullable|string|max:255',
            'numero_exterior' => 'nullable|string|max:10',
            'numero_interior' => 'nullable|string|max:10',
            'colonia' => 'nullable|string|max:100',
            'ciudad' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:100',
            'codigo_postal' => 'nullable|string|max:5',
            'pais' => 'nullable|string|max:3',
            'dias_credito' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
            'descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'notas' => 'nullable|string|max:2000',
        ]);

        $validated['rfc'] = cleanRFC($validated['rfc']);
        $validated['activo'] = $request->boolean('activo', true);

        $cliente = Cliente::create($validated);

        return redirect()->route('clientes.show', $cliente->id)
            ->with('success', 'Cliente creado exitosamente');
    }

    /**
     * Mostrar detalle de cliente
     */
    public function show(Cliente $cliente)
    {
        $regimenEtiqueta = $cliente->regimen_fiscal
            ? (optional(RegimenFiscal::where('clave', $cliente->regimen_fiscal)->first())->etiqueta ?? $cliente->regimen_fiscal)
            : null;
        $usoCfdiEtiqueta = $cliente->uso_cfdi_default
            ? (optional(UsoCfdi::where('clave', $cliente->uso_cfdi_default)->first())->etiqueta ?? $cliente->uso_cfdi_default)
            : null;
        $cliente->load([
            'facturas' => function($q) {
                $q->latest()->limit(10);
            },
            'contactos' => function($q) {
                $q->orderByDesc('principal');
            }
        ]);

        return view('clientes.show', compact('cliente', 'regimenEtiqueta', 'usoCfdiEtiqueta'));
    }

    /**
     * Mostrar formulario de editar
     */
    public function edit(Cliente $cliente)
    {
        $regimenes = RegimenFiscal::activos()->get();
        $usosCfdi = UsoCfdi::activos()->get();
        return view('clientes.edit', compact('cliente', 'regimenes', 'usosCfdi'));
    }

    /**
     * Actualizar cliente
     */
    public function update(Request $request, Cliente $cliente)
    {
        $rfcSize = $request->input('tipo_persona') === 'moral' ? 12 : 13;
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'nombre_comercial' => 'nullable|string|max:255',
            'tipo_persona' => 'required|in:fisica,moral',
            'rfc' => [
                'required',
                'string',
                'size:' . $rfcSize,
                'unique:clientes,rfc,' . $cliente->id,
            ],
            'regimen_fiscal' => 'nullable|string|exists:regimenes_fiscales,clave',
            'uso_cfdi_default' => 'required|string|exists:usos_cfdi,clave',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:15',
            'celular' => 'nullable|string|max:15',
            'contacto_nombre' => 'nullable|string|max:255',
            'contacto_puesto' => 'nullable|string|max:100',
            'calle' => 'nullable|string|max:255',
            'numero_exterior' => 'nullable|string|max:10',
            'numero_interior' => 'nullable|string|max:10',
            'colonia' => 'nullable|string|max:100',
            'ciudad' => 'nullable|string|max:100',
            'estado' => 'nullable|string|max:100',
            'codigo_postal' => 'nullable|string|max:5',
            'pais' => 'nullable|string|max:3',
            'dias_credito' => 'nullable|integer|min:0',
            'limite_credito' => 'nullable|numeric|min:0',
            'descuento_porcentaje' => 'nullable|numeric|min:0|max:100',
            'notas' => 'nullable|string|max:2000',
            'activo' => 'boolean',
        ]);

        $validated['rfc'] = cleanRFC($validated['rfc']);
        $validated['activo'] = $request->boolean('activo', true);

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