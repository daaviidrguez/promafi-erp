<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductoRevisionPrecioController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(auth()->user()?->can('productos.ver'), 403);

        $compraId = (int) $request->query('compra_id', 0);
        if ($compraId < 1) {
            return redirect()->route('compras.index')->with('error', 'Indique la compra a revisar (compra_id).');
        }

        $payload = session('revision_precio_post_compra');
        if (! is_array($payload) || (int) ($payload['factura_compra_id'] ?? 0) !== $compraId) {
            return redirect()->route('compras.show', $compraId)
                ->with('error', 'No hay datos de revisión de precios para esta compra. Puede que la sesión haya expirado o ya se haya aplicado.');
        }

        $items = $payload['items'] ?? [];
        if ($items === []) {
            return redirect()->route('compras.show', $compraId)->with('info', 'No quedan partidas pendientes de revisión.');
        }

        return view('productos.revision-precios', [
            'compraId' => $compraId,
            'items' => $items,
        ]);
    }

    public function aplicar(Request $request)
    {
        abort_unless(auth()->user()?->can('productos.editar'), 403);

        $validated = $request->validate([
            'factura_compra_id' => 'required|integer|min:1',
            'actualizar_ultimo_costo' => 'sometimes|boolean',
            'filas' => 'required|array|min:1',
            'filas.*.producto_id' => 'required|integer|exists:productos,id',
            'filas.*.precio_final' => 'required|numeric|min:0|max:999999999',
            'filas.*.aplicar' => 'nullable',
        ]);

        $compraId = (int) $validated['factura_compra_id'];
        $payload = session('revision_precio_post_compra');

        if (! is_array($payload) || (int) ($payload['factura_compra_id'] ?? 0) !== $compraId) {
            return redirect()->route('compras.index')->with('error', 'La sesión de revisión no coincide con esta compra.');
        }

        $itemsSesion = collect($payload['items'] ?? [])->keyBy('producto_id');
        $actualizarUltimo = $request->boolean('actualizar_ultimo_costo', true);

        $aplicados = 0;
        $aplicadosIds = [];

        DB::beginTransaction();
        try {
            foreach ($validated['filas'] as $fila) {
                if (! filter_var($fila['aplicar'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $pid = (int) $fila['producto_id'];
                $meta = $itemsSesion->get($pid);
                if (! $meta) {
                    continue;
                }

                $precioFinal = round((float) $fila['precio_final'], 2);
                $nuevoCosto = (float) ($meta['nuevo_costo'] ?? 0);

                $attrs = [
                    'precio_venta' => $precioFinal,
                    'requiere_revision_precio' => false,
                ];

                if ($actualizarUltimo) {
                    $attrs['ultimo_costo'] = round($nuevoCosto, 4);
                }

                Producto::query()->whereKey($pid)->update($attrs);
                $aplicadosIds[] = $pid;
                $aplicados++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'No se pudo aplicar: '.$e->getMessage());
        }

        if ($aplicados === 0) {
            return back()->with('error', 'Marque al menos una fila con «Aplicar» para guardar cambios.');
        }

        $restantes = array_values(array_filter(
            $payload['items'] ?? [],
            fn ($row) => ! in_array((int) ($row['producto_id'] ?? 0), $aplicadosIds, true)
        ));

        if ($restantes === []) {
            session()->forget('revision_precio_post_compra');
            $msg = "Se actualizó el precio de venta en {$aplicados} producto(s).";

            return redirect()->route('compras.show', $compraId)->with('success', $msg);
        }

        $nuevoPayload = $payload;
        $nuevoPayload['items'] = $restantes;
        $nuevoPayload['count'] = count($restantes);
        session(['revision_precio_post_compra' => $nuevoPayload]);

        $msg = "Se actualizaron {$aplicados} producto(s). Quedan {$nuevoPayload['count']} pendientes de revisar.";

        return redirect()
            ->route('productos.revision-precios', ['compra_id' => $compraId])
            ->with('success', $msg);
    }
}
