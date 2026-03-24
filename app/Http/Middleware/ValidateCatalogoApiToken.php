<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateCatalogoApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('catalogo.api_token');
        if ($expected === null || $expected === '') {
            return response()->json([
                'message' => 'Catálogo API no configurado. Define CATALOGO_API_TOKEN en el servidor.',
            ], 503);
        }

        $provided = $request->bearerToken()
            ?? $request->header('X-Catalog-Token')
            ?? $request->query('token');

        if (! is_string($provided) || $provided === '' || ! hash_equals((string) $expected, $provided)) {
            return response()->json(['message' => 'No autorizado'], 401);
        }

        return $next($request);
    }
}
