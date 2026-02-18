<?php

// UBICACIÓN: routes/api.php
//
// Este archivo define las rutas de tu API.
// Todas las rutas aquí automáticamente tienen prefijo /api
//
// Ejemplo: Route::get('/clientes') → http://localhost:8000/api/clientes

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Aquí registramos todas las rutas de nuestra API REST.
| Todas estas rutas automáticamente tienen el prefijo /api
|
*/

// ============================================================================
// RUTAS PÚBLICAS (no requieren autenticación)
// ============================================================================

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/register', [AuthController::class, 'register']);
});

// ============================================================================
// RUTAS PROTEGIDAS (requieren token de autenticación)
// ============================================================================

Route::middleware('auth:sanctum')->group(function () {
    
    // ───── AUTH ─────
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/verify', [AuthController::class, 'verify']);
    });

    // ───── EMPRESA ───── (próximamente)
    // Route::apiResource('empresa', EmpresaController::class);

    // ───── CLIENTES ───── (próximamente)
    // Route::apiResource('clientes', ClienteController::class);

    // ───── PRODUCTOS ───── (próximamente)
    // Route::apiResource('productos', ProductoController::class);

    // ───── FACTURAS ───── (próximamente)
    // Route::apiResource('facturas', FacturaController::class);
    // Route::post('facturas/{id}/timbrar', [FacturaController::class, 'timbrar']);

    // ───── CUENTAS POR COBRAR ───── (próximamente)
    // Route::apiResource('cuentas-cobrar', CuentaPorCobrarController::class);

    // ───── COMPLEMENTOS DE PAGO ───── (próximamente)
    // Route::apiResource('complementos-pago', ComplementoPagoController::class);
});

// Ruta de prueba
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'API funcionando correctamente',
        'version' => '1.0.0',
        'timestamp' => now()->toDateTimeString(),
    ]);
});