<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Remision;
use App\Models\RemisionDetalle;
use App\Models\Cliente;
use App\Models\Producto;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RemisionController extends Controller
{
    public function index(Request $request)
    {
        $query = Remision::with(['cliente', 'usuario']);
        if ($request->filled('estado')) {
            $query->where('estado', $request->estado);
        }
        if ($request->filled('search')) {
            $query->buscar($request->search);
        }
        $remisiones = $query->orderBy('created_at', 'desc')->paginate(20);
        $estadisticas = [
            'borrador' => Remision::where('estado', 'borrador')->count(),
            'enviada' => Remision::where('estado', 'enviada')->count(),
            'entregada' => Remision::where('estado', 'entregada')->count(),
            'cancelada' => Remision::where('estado', 'cancelada')->count(),
        ];
        return view('remisiones.index', compact('remisiones', 'estadisticas'));
    }

    public function create()
    {
        $empresa = Empresa::principal();
        if (!$empresa) {
            return redirect()->route('dashboard')->with('error', 'Configura la empresa primero');
        }
        $folio = Remision::generarFolio();
        return view('remisiones.create', compact('folio'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'fecha' => 'required|date',
            'direccion_entrega' => 'nullable|string|max:2000',
            'observaciones' => 'nullable|string|max:2000',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string|max:500',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.unidad' => 'nullable|string|max:10',
        ]);

        DB::beginTransaction();
        try {
            $cliente = Cliente::findOrFail($validated['cliente_id']);
            $empresa = Empresa::principal();

            $remision = new Remision();
            $remision->folio = Remision::generarFolio();
            $remision->estado = 'borrador';
            $remision->cliente_id = $cliente->id;
            $remision->empresa_id = $empresa?->id;
            $remision->cliente_nombre = $cliente->nombre;
            $remision->cliente_rfc = $cliente->rfc;
            $remision->fecha = $validated['fecha'];
            $remision->direccion_entrega = $validated['direccion_entrega'] ?? null;
            $remision->observaciones = $validated['observaciones'] ?? null;
            $remision->usuario_id = auth()->id();
            $remision->save();

            foreach ($validated['productos'] as $index => $item) {
                $producto = !empty($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $unidad = $item['unidad'] ?? ($producto?->unidad ?? 'PZA');
                RemisionDetalle::create([
                    'remision_id' => $remision->id,
                    'producto_id' => $producto?->id,
                    'codigo' => $producto?->codigo,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'unidad' => $unidad,
                    'orden' => $index,
                ]);
            }
            DB::commit();
            return redirect()->route('remisiones.show', $remision->id)->with('success', 'Remisión creada correctamente');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function show(Remision $remision)
    {
        $remision->load(['cliente', 'detalles.producto', 'factura', 'usuario']);
        return view('remisiones.show', compact('remision'));
    }

    public function edit(Remision $remision)
    {
        if (!$remision->puedeEditarse()) {
            return redirect()->route('remisiones.show', $remision->id)->with('error', 'Solo se pueden editar remisiones en borrador');
        }
        $remision->load('detalles.producto');
        return view('remisiones.edit', compact('remision'));
    }

    public function update(Request $request, Remision $remision)
    {
        if (!$remision->puedeEditarse()) {
            return redirect()->route('remisiones.show', $remision->id)->with('error', 'Solo se pueden editar remisiones en borrador');
        }

        $validated = $request->validate([
            'fecha' => 'required|date',
            'direccion_entrega' => 'nullable|string|max:2000',
            'observaciones' => 'nullable|string|max:2000',
            'productos' => 'required|array|min:1',
            'productos.*.producto_id' => 'nullable|exists:productos,id',
            'productos.*.descripcion' => 'required|string|max:500',
            'productos.*.cantidad' => 'required|numeric|min:0.01',
            'productos.*.unidad' => 'nullable|string|max:10',
        ]);

        DB::beginTransaction();
        try {
            $remision->fecha = $validated['fecha'];
            $remision->direccion_entrega = $validated['direccion_entrega'] ?? null;
            $remision->observaciones = $validated['observaciones'] ?? null;
            $remision->save();

            $remision->detalles()->delete();
            foreach ($validated['productos'] as $index => $item) {
                $producto = !empty($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $unidad = $item['unidad'] ?? ($producto?->unidad ?? 'PZA');
                RemisionDetalle::create([
                    'remision_id' => $remision->id,
                    'producto_id' => $producto?->id,
                    'codigo' => $producto?->codigo,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'unidad' => $unidad,
                    'orden' => $index,
                ]);
            }
            DB::commit();
            return redirect()->route('remisiones.show', $remision->id)->with('success', 'Remisión actualizada');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function destroy(Remision $remision)
    {
        if (!$remision->puedeEditarse()) {
            return redirect()->route('remisiones.index')->with('error', 'Solo se pueden eliminar remisiones en borrador');
        }
        $remision->delete();
        return redirect()->route('remisiones.index')->with('success', 'Remisión eliminada');
    }

    public function enviar(Remision $remision)
    {
        if (!$remision->puedeEnviarse()) {
            return back()->with('error', 'Solo se puede enviar una remisión en borrador');
        }
        $remision->update(['estado' => 'enviada']);
        return back()->with('success', 'Remisión marcada como enviada');
    }

    public function entregar(Remision $remision)
    {
        if (!$remision->puedeEntregarse()) {
            return back()->with('error', 'Solo se puede marcar como entregada una remisión enviada');
        }
        $remision->update(['estado' => 'entregada', 'fecha_entrega' => now()]);
        return back()->with('success', 'Remisión marcada como entregada');
    }

    public function cancelar(Remision $remision)
    {
        if (!$remision->puedeCancelarse()) {
            return back()->with('error', 'No se puede cancelar esta remisión');
        }
        $remision->update(['estado' => 'cancelada']);
        return back()->with('success', 'Remisión cancelada');
    }

    public function buscarClientes(Request $request)
    {
        $search = $request->get('q', '');
        $clientes = Cliente::activos()
            ->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('rfc', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'nombre', 'rfc', 'codigo']);
        return response()->json($clientes);
    }

    public function buscarProductos(Request $request)
    {
        $search = $request->get('q', '');
        $productos = Producto::where('activo', true)
            ->where(function ($q) use ($search) {
                $q->where('nombre', 'like', "%{$search}%")
                    ->orWhere('codigo', 'like', "%{$search}%");
            })
            ->limit(10)
            ->get(['id', 'codigo', 'nombre', 'unidad']);
        return response()->json($productos->map(fn ($p) => [
            'id' => $p->id,
            'codigo' => $p->codigo,
            'nombre' => $p->nombre,
            'unidad' => $p->unidad ?? 'PZA',
        ]));
    }
}
