<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CategoriaProducto;
use App\Models\Producto;
use Illuminate\Http\Request;

class CatalogoOnlineController extends Controller
{
    public function index(Request $request)
    {
        $query = Producto::query()->with(['categoria.parent']);

        if ($request->filled('q')) {
            $term = '%'.str_replace(['%', '_'], ['\\%', '\\_'], trim($request->get('q'))).'%';
            $query->where(function ($q) use ($term) {
                $q->where('codigo', 'like', $term)
                    ->orWhere('nombre', 'like', $term)
                    ->orWhere('descripcion', 'like', $term);
            });
        }

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->integer('categoria_id'));
        }

        if ($request->filled('precio_min')) {
            $query->where('precio_venta', '>=', (float) $request->get('precio_min'));
        }
        if ($request->filled('precio_max')) {
            $query->where('precio_venta', '<=', (float) $request->get('precio_max'));
        }

        $productos = $query->orderBy('nombre')->paginate(24)->withQueryString();
        $categorias = CategoriaProducto::with('parent')->activas()->orderBy('nombre')->get();

        $hayFiltros = $request->anyFilled(['q', 'categoria_id', 'precio_min', 'precio_max']);

        $baseCatalogoApi = rtrim((string) config('catalogo.public_base_url', config('app.url')), '/');
        $urlApiListado = $baseCatalogoApi.'/api/v1/catalogo/productos';

        return view('catalogo-online.index', compact('productos', 'categorias', 'hayFiltros', 'urlApiListado'));
    }

    public function updateProducto(Request $request, Producto $producto)
    {
        $request->validate([
            'catalogo_online_visible' => 'nullable|boolean',
            'catalogo_online_mostrar_precio' => 'nullable|boolean',
        ]);

        $producto->catalogo_online_visible = $request->boolean('catalogo_online_visible');
        $producto->catalogo_online_mostrar_precio = $request->boolean('catalogo_online_mostrar_precio');
        $producto->save();

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'producto' => [
                    'id' => $producto->id,
                    'catalogo_online_visible' => $producto->catalogo_online_visible,
                    'catalogo_online_mostrar_precio' => $producto->catalogo_online_mostrar_precio,
                ],
            ]);
        }

        return redirect()
            ->route('catalogo-online.index', $request->only(['q', 'categoria_id', 'precio_min', 'precio_max', 'page']))
            ->with('success', 'Configuración del catálogo actualizada para '.$producto->codigo);
    }
}
