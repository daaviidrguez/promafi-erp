<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CategoriaProductoController.php

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use Illuminate\Http\Request;

class CategoriaProductoController extends Controller
{
    public function index()
    {
        $categorias = CategoriaProducto::with('parent')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(20);

        return view('categorias.index', compact('categorias'));
    }

    public function create()
    {
        $categorias = CategoriaProducto::activas()
            ->raiz()
            ->orderBy('nombre')
            ->get();

        return view('categorias.create', compact('categorias'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50|unique:categorias_productos,codigo',
            'descripcion' => 'nullable|string',
            'parent_id' => 'nullable|exists:categorias_productos,id',
            'color' => 'nullable|string|max:20',
            'icono' => 'nullable|string|max:10',
            'orden' => 'nullable|integer',
        ]);

        $validated['activo'] = true;

        CategoriaProducto::create($validated);

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría creada exitosamente');
    }

    public function edit(CategoriaProducto $categoria)
    {
        $categorias = CategoriaProducto::where('id', '!=', $categoria->id)
            ->raiz()
            ->orderBy('nombre')
            ->get();

        return view('categorias.edit', compact('categoria', 'categorias'));
    }

    public function update(Request $request, CategoriaProducto $categoria)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|max:50|unique:categorias_productos,codigo,' . $categoria->id,
            'descripcion' => 'nullable|string',
            'parent_id' => 'nullable|exists:categorias_productos,id',
            'color' => 'nullable|string|max:20',
            'icono' => 'nullable|string|max:10',
            'orden' => 'nullable|integer',
            'activo' => 'boolean',
        ]);

        $categoria->update($validated);

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría actualizada exitosamente');
    }

    public function destroy(CategoriaProducto $categoria)
    {
        if ($categoria->productos()->exists()) {
            return back()->with('error', 'No puedes eliminar una categoría con productos asociados');
        }

        if ($categoria->children()->exists()) {
            return back()->with('error', 'No puedes eliminar una categoría que tiene subcategorías');
        }

        $categoria->delete();

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría eliminada exitosamente');
    }
}