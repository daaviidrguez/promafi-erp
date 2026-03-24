<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/CategoriaProductoController.php

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;

class CategoriaProductoController extends Controller
{
    public function index()
    {
        $categorias = CategoriaProducto::with('parent')
            ->orderBy('orden')
            ->orderBy('nombre')
            ->paginate(20);

        $categoriasPadre = CategoriaProducto::activas()
            ->raiz()
            ->orderBy('nombre')
            ->get();

        return view('categorias.index', compact('categorias', 'categoriasPadre'));
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
        $this->normalizarColorCategoria($validated);

        try {
            CategoriaProducto::create($validated);
        } catch (QueryException $e) {
            return $this->respuestaErrorBdCategorias($request, $e);
        }

        return redirect()->route('categorias.index')
            ->with('success', 'Categoría creada exitosamente');
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

        $this->normalizarColorCategoria($validated);

        try {
            $categoria->update($validated);
        } catch (QueryException $e) {
            return $this->respuestaErrorBdCategorias($request, $e);
        }

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

    /**
     * La columna color es NOT NULL en BD; los formularios pueden enviar vacío → null.
     */
    private function normalizarColorCategoria(array &$validated): void
    {
        $color = $validated['color'] ?? null;
        if ($color === null || trim((string) $color) === '') {
            $validated['color'] = '#0B3C5D';
        }
    }

    private function respuestaErrorBdCategorias(Request $request, QueryException $e)
    {
        $msg = $e->getMessage();
        $alerta = 'No se pudo guardar la categoría. Revisa los datos e inténtalo de nuevo.';

        if (str_contains($msg, 'color') && str_contains($msg, 'cannot be null')) {
            $alerta = 'El color es obligatorio en el sistema. Indica un color en formato hexadecimal (por ejemplo #0B3C5D) o deja el campo vacío para usar el color predeterminado.';
        } elseif (str_contains($msg, 'Duplicate entry') || str_contains($msg, '1062')) {
            $alerta = 'Ya existe una categoría con ese código u otro dato único. Usa un código distinto.';
        } elseif (str_contains($msg, 'foreign key') || str_contains($msg, '1452')) {
            $alerta = 'La categoría padre seleccionada no es válida o ya no existe.';
        }

        return redirect()->route('categorias.index')
            ->withInput()
            ->with('error', $alerta);
    }
}