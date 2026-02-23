<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Proveedor;
use Illuminate\Http\Request;

class ProveedorController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $proveedores = Proveedor::query()
            ->when($search, fn ($q) => $q->buscar($search))
            ->orderBy('nombre')
            ->paginate(15);
        return view('proveedores.index', compact('proveedores', 'search'));
    }

    public function create()
    {
        return view('proveedores.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:20|unique:proveedores,codigo',
            'rfc' => 'nullable|string|max:13',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:20',
            'dias_credito' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        $validated['activo'] = $request->boolean('activo', true);
        if (!empty($validated['rfc'])) {
            $validated['rfc'] = strtoupper(preg_replace('/\s/', '', $validated['rfc']));
        }
        Proveedor::create($validated);
        return redirect()->route('proveedores.index')->with('success', 'Proveedor creado exitosamente');
    }

    public function show(Proveedor $proveedor)
    {
        $proveedor->load([
            'ordenesCompra' => fn ($q) => $q->latest()->limit(10),
            'cuentasPorPagar',
        ]);
        $estadisticas = [
            'total_ordenes' => $proveedor->ordenesCompra()->count(),
            'ordenes_borrador' => $proveedor->ordenesCompra()->where('estado', 'borrador')->count(),
            'ordenes_aceptadas' => $proveedor->ordenesCompra()->where('estado', 'aceptada')->count(),
            'ordenes_recibidas' => $proveedor->ordenesCompra()->where('estado', 'recibida')->count(),
            'cuentas_pendientes' => $proveedor->cuentasPorPagar()->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->count(),
            'monto_pendiente' => (float) $proveedor->cuentasPorPagar()->whereIn('estado', ['pendiente', 'parcial', 'vencida'])->sum('monto_pendiente'),
        ];
        return view('proveedores.show', compact('proveedor', 'estadisticas'));
    }

    public function edit(Proveedor $proveedor)
    {
        return view('proveedores.edit', compact('proveedor'));
    }

    public function update(Request $request, Proveedor $proveedor)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:20|unique:proveedores,codigo,' . $proveedor->id,
            'rfc' => 'nullable|string|max:13',
            'email' => 'nullable|email',
            'telefono' => 'nullable|string|max:20',
            'dias_credito' => 'nullable|integer|min:0',
            'activo' => 'boolean',
        ]);
        if (!empty($validated['rfc'])) {
            $validated['rfc'] = strtoupper(preg_replace('/\s/', '', $validated['rfc']));
        }
        $proveedor->update($validated);
        return redirect()->route('proveedores.show', $proveedor->id)->with('success', 'Proveedor actualizado');
    }

    public function destroy(Proveedor $proveedor)
    {
        $proveedor->delete();
        return redirect()->route('proveedores.index')->with('success', 'Proveedor eliminado');
    }
}
