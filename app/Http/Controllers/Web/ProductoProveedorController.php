<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use App\Models\ProductoProveedor;
use Illuminate\Http\Request;

class ProductoProveedorController extends Controller
{
    /**
     * Guarda (crea/actualiza) el código de proveedor para un producto.
     * Funciona desde el modal del show de productos.
     */
    public function save(Request $request, Producto $producto)
    {
        $validated = $request->validate([
            'producto_proveedor_id' => 'nullable|integer|exists:producto_proveedores,id',
            'proveedor_id' => 'required|integer|exists:proveedores,id',
            'codigo' => 'required|string|max:100',
        ]);

        $proveedorId = (int) $validated['proveedor_id'];

        // Si se manda un ID, actualizamos ese registro siempre que pertenezca al producto.
        $productoProveedorId = $validated['producto_proveedor_id'] ?? null;
        if ($productoProveedorId) {
            $registro = ProductoProveedor::where('id', (int) $productoProveedorId)
                ->where('producto_id', $producto->id)
                ->firstOrFail();

            $registro->update([
                'proveedor_id' => $proveedorId,
                'codigo' => $validated['codigo'],
            ]);
        } else {
            // Si no hay ID (modo "Nuevo"), hacemos upsert por (producto_id, proveedor_id)
            // para evitar duplicados.
            ProductoProveedor::updateOrCreate(
                [
                    'producto_id' => $producto->id,
                    'proveedor_id' => $proveedorId,
                ],
                [
                    'codigo' => $validated['codigo'],
                ]
            );
        }

        return redirect()
            ->route('productos.show', $producto->id)
            ->with('success', 'Código de proveedor guardado correctamente');
    }

    /**
     * Elimina el código de proveedor relacionado a un producto.
     */
    public function destroy(Producto $producto, ProductoProveedor $productoProveedor)
    {
        if ((int) $productoProveedor->producto_id !== (int) $producto->id) {
            abort(404);
        }

        $productoProveedor->delete();

        return redirect()
            ->route('productos.show', $producto->id)
            ->with('success', 'Código de proveedor eliminado correctamente');
    }
}

