<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Factura;
use App\Models\Cotizacion;
use App\Models\Producto;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Models\Remision;
use App\Models\FacturaCompra;

class GlobalSearchController extends Controller
{
    public function search(Request $request)
    {
        $query = trim($request->get('q'));
        $user = $request->user();

        if (!$query || strlen($query) < 2) {
            return response()->json($this->emptyResponse());
        }

        try {
            $data = [];

            // Productos (quien puede ver productos)
            if ($user->can('productos.ver')) {
                $data['productos'] = Producto::buscar($query)
                    ->orderBy('nombre')
                    ->limit(6)
                    ->get()
                    ->map(fn ($p) => [
                        'label'  => ($p->codigo ? $p->codigo . ' - ' : '') . $p->nombre,
                        'url'    => route('productos.show', $p->id),
                        'tipo'   => 'producto',
                    ]);
            }

            // Clientes
            if ($user->can('clientes.ver')) {
                $data['clientes'] = Cliente::buscar($query)
                    ->orderBy('nombre')
                    ->limit(6)
                    ->get()
                    ->map(fn ($c) => [
                        'label'  => ($c->codigo ? $c->codigo . ' - ' : '') . $c->nombre,
                        'url'    => route('clientes.show', $c->id),
                        'tipo'   => 'cliente',
                    ]);
            }

            // Proveedores
            if ($user->can('proveedores.ver')) {
                $data['proveedores'] = Proveedor::buscar($query)
                    ->orderBy('nombre')
                    ->limit(6)
                    ->get()
                    ->map(fn ($p) => [
                        'label'  => ($p->codigo ? $p->codigo . ' - ' : '') . $p->nombre,
                        'url'    => route('proveedores.show', $p->id),
                        'tipo'   => 'proveedor',
                    ]);
            }

            // Facturas
            if ($user->can('facturas.ver')) {
                $data['facturas'] = Factura::with('cliente')
                    ->buscar($query)
                    ->orderBy('fecha_emision', 'desc')
                    ->limit(6)
                    ->get()
                    ->map(fn ($f) => [
                        'label'  => 'Factura ' . $f->folio_completo . ' - ' . $f->nombre_receptor,
                        'url'    => route('facturas.show', $f->id),
                        'estado' => $f->estado ?? null,
                        'tipo'   => 'factura',
                    ]);
            }

            // Cotizaciones
            if ($user->can('cotizaciones.ver')) {
                $data['cotizaciones'] = Cotizacion::paraUsuarioActual()
                    ->with('usuario')
                    ->buscar($query)
                    ->orderBy('fecha', 'desc')
                    ->limit(6)
                    ->get()
                    ->map(fn ($c) => [
                        'label'  => 'Cotización ' . $c->folio . ' - ' . $c->cliente_nombre,
                        'url'    => route('cotizaciones.show', $c->id),
                        'estado' => $c->estado ?? null,
                        'tipo'   => 'cotizacion',
                    ]);
            }

            // Remisiones
            if ($user->can('remisiones.ver')) {
                $data['remisiones'] = Remision::buscar($query)
                    ->orderBy('fecha', 'desc')
                    ->limit(6)
                    ->get()
                    ->map(fn ($r) => [
                        'label'  => 'Remisión ' . ($r->folio ?? $r->id) . ' - ' . ($r->cliente_nombre ?? '-'),
                        'url'    => route('remisiones.show', $r->id),
                        'estado' => $r->estado ?? null,
                        'tipo'   => 'remision',
                    ]);
            }

            // Compras (facturas de compra)
            if ($user->can('ordenes_compra.ver')) {
                $data['compras'] = FacturaCompra::with('proveedor')
                    ->buscar($query)
                    ->orderBy('fecha_emision', 'desc')
                    ->limit(6)
                    ->get()
                    ->map(fn ($c) => [
                        'label'  => 'Compra ' . $c->folio_completo . ' - ' . ($c->nombre_emisor ?? $c->proveedor?->nombre ?? '-'),
                        'url'    => route('compras.show', $c->id),
                        'estado' => $c->estado ?? null,
                        'tipo'   => 'compra',
                    ]);
            }

            return response()->json($data);
        } catch (\Throwable $e) {
            return response()->json(array_merge($this->emptyResponse(), ['error' => $e->getMessage()]), 500);
        }
    }

    private function emptyResponse(): array
    {
        return [
            'productos'    => [],
            'clientes'     => [],
            'proveedores'  => [],
            'facturas'     => [],
            'cotizaciones' => [],
            'remisiones'   => [],
            'compras'      => [],
        ];
    }
}
