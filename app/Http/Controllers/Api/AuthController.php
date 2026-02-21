<?php

namespace App\Http\Controllers\Api;

// UBICACIÓN: app/Http/Controllers/Api/AuthController.php
// 
// CREAR CARPETA: app/Http/Controllers/Api/ (si no existe)
// Luego pegar este archivo ahí.
//
// Este controlador maneja:
// - Login (generar token)
// - Logout (revocar token)
// - Registro de usuarios
// - Ver perfil del usuario autenticado

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Login - Autenticar usuario y generar token
     * 
     * POST /api/auth/login
     * Body: { "email": "admin@promafi.mx", "password": "12345678" }
     */
    public function login(Request $request)
    {
        // Validar datos
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Buscar usuario
        $user = User::where('email', $request->email)->first();

        // Verificar contraseña
        if (!$user || !Hash::check($request->password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Las credenciales son incorrectas.'],
            ]);
        }

        // Verificar si está activo
        if (!$user->activo) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario inactivo. Contacta al administrador.',
            ], 403);
        }

        // Generar token de acceso
        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login exitoso',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role_name,
                ],
                'token' => $token,
            ],
        ]);
    }

    /**
     * Logout - Revocar token actual
     * 
     * POST /api/auth/logout
     * Headers: Authorization: Bearer {token}
     */
    public function logout(Request $request)
    {
        // Revocar el token actual
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sesión cerrada exitosamente',
        ]);
    }

    /**
     * Registro - Crear nuevo usuario (solo admin)
     * 
     * POST /api/auth/register
     * Body: { "name": "Juan", "email": "juan@example.com", "password": "12345678", "role": "vendedor" }
     */
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:admin,vendedor,contador,usuario',
        ]);

        $role = \App\Models\Role::where('name', $request->role)->firstOrFail();
        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id,
            'activo' => true,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role_name,
                ],
            ],
        ], 201);
    }

    /**
     * Obtener usuario autenticado
     * 
     * GET /api/auth/me
     * Headers: Authorization: Bearer {token}
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role_name,
                ],
            ],
        ]);
    }

    /**
     * Verificar token
     * 
     * GET /api/auth/verify
     * Headers: Authorization: Bearer {token}
     */
    public function verify(Request $request)
    {
        return response()->json([
            'success' => true,
            'message' => 'Token válido',
            'data' => [
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role_name,
                ],
            ],
        ]);
    }
}