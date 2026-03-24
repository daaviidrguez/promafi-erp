<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pública (token) para consumir desde promafi.mx/catalogo u otros sitios.
 */
class CatalogoOnlineApiController extends Controller
{
    /**
     * Listado de productos visibles en el catálogo online.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Producto::query()
            ->with(['categoria.parent'])
            ->where('activo', true)
            ->where('catalogo_online_visible', true);

        if ($request->filled('categoria_id')) {
            $query->where('categoria_id', $request->integer('categoria_id'));
        }

        if ($request->filled('q')) {
            $q = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $request->get('q')).'%';
            $query->where(function ($qry) use ($q) {
                $qry->where('codigo', 'like', $q)
                    ->orWhere('nombre', 'like', $q)
                    ->orWhere('descripcion', 'like', $q);
            });
        }

        $perPage = min(max((int) $request->get('per_page', 50), 1), 200);
        $paginator = $query->orderBy('codigo')->paginate($perPage);

        $data = $paginator->getCollection()->map(fn (Producto $p) => $this->serializarProducto($p));

        return response()->json([
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
            'data' => $data,
        ]);
    }

    /**
     * Detalle de un producto del catálogo (por id).
     */
    public function show(Producto $producto): JsonResponse
    {
        if (! $producto->activo || ! $producto->catalogo_online_visible) {
            return response()->json(['message' => 'No encontrado'], 404);
        }

        $producto->loadMissing(['categoria.parent']);

        return response()->json([
            'meta' => [
                'generated_at' => now()->toIso8601String(),
            ],
            'data' => $this->serializarProducto($producto),
        ]);
    }

    private function serializarProducto(Producto $producto): array
    {
        $mostrarPrecio = (bool) $producto->catalogo_online_mostrar_precio;

        return [
            'id' => $producto->id,
            'codigo' => $producto->codigo,
            'nombre' => $producto->nombre,
            'descripcion' => $producto->descripcion,
            'unidad' => $producto->unidad,
            'categoria' => $producto->categoria ? [
                'id' => $producto->categoria->id,
                'nombre' => $producto->categoria->nombre,
                'icono' => $producto->categoria->icono ?? null,
                'color' => $producto->categoria->color ?? null,
            ] : null,
            'categoria_padre' => ($producto->categoria && $producto->categoria->parent) ? [
                'id' => $producto->categoria->parent->id,
                'nombre' => $producto->categoria->parent->nombre,
                'icono' => $producto->categoria->parent->icono ?? null,
                'color' => $producto->categoria->parent->color ?? null,
            ] : null,
            'precio_venta' => $mostrarPrecio ? (float) $producto->precio_con_iva : null,
            'mostrar_precio' => $mostrarPrecio,
            'imagenes' => $producto->imagenes_urls,
        ];
    }
}
