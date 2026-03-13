<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRoutePermission
{
    /** Mapa: primer segmento del nombre de ruta => clave de permiso (null = no exigir). */
    protected array $routeToPermission = [
        'dashboard' => 'dashboard.ver',
        'tablero' => 'dashboard.ver',
        'reportes' => 'reportes.ver',
        'perfil' => null,
        'empresa' => 'configuracion.editar',
        'configuracion' => 'configuracion.editar',
        'cotizaciones' => 'cotizaciones.ver',
        'facturas' => 'facturas.ver',
        'listas-precios' => 'listas_precios.ver',
        'clientes' => 'clientes.ver',
        'catalogos-sat' => 'catalogos_sat.ver',
        'complementos' => 'complementos.ver',
        'remisiones' => 'remisiones.ver',
        'devoluciones' => 'devoluciones.ver',
        'notas-credito' => 'notas_credito.ver',
        'productos' => 'productos.ver',
        'inventario' => 'inventario.ver',
        'categorias' => 'categorias.ver',
        'sugerencias' => 'sugerencias.ver',
        'ordenes-compra' => 'ordenes_compra.ver',
        'compras' => 'compras.ver',
        'cotizaciones-compra' => 'cotizaciones_compra.ver',
        'proveedores' => 'proveedores.ver',
        'cuentas-por-pagar' => 'cuentas_por_pagar.ver',
        'estado-cuenta' => 'estado_cuenta.ver',
        'cuentas-cobrar' => 'cuentas_cobrar.ver',
        'usuarios' => 'usuarios.ver',
        'roles' => 'roles.ver',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $routeName = $request->route()?->getName();
        if (!$routeName) {
            return $next($request);
        }

        $segment = explode('.', $routeName)[0] ?? null;
        if (!$segment) {
            return $next($request);
        }

        $permission = $this->routeToPermission[$segment] ?? null;
        if ($permission === null) {
            return $next($request);
        }

        if (!$user->hasPermission($permission)) {
            // Vendedor sin dashboard/reportes: redirigir a Facturación (cotizaciones)
            if ($user->isVendedor() && in_array($permission, ['dashboard.ver', 'reportes.ver'], true)) {
                return redirect()->route('cotizaciones.index');
            }
            abort(403, 'No tienes permiso para acceder a esta sección.');
        }

        return $next($request);
    }
}
