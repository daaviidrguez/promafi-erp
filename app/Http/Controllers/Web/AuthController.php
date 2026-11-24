<?php

namespace App\Http\Controllers\Web;

// UBICACIÓN: app/Http/Controllers/Web/AuthController.php
//
// CREAR CARPETA: app/Http/Controllers/Web/
// Este controlador maneja las vistas (no API)

use App\Http\Controllers\Controller;
use App\Models\Empresa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    /**
     * Mostrar formulario de login
     */
    public function showLogin()
    {
        if (Auth::check()) {
            return $this->redirectAfterLogin();
        }

        $empresa = Empresa::principal();
        return view('login', compact('empresa'));
    }

    /**
     * Procesar login (sesión única: invalida otras sesiones del mismo usuario).
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (! Auth::attempt($credentials)) {
            return back()->withErrors([
                'email' => 'Las credenciales son incorrectas.',
            ])->onlyInput('email');
        }

        $user = Auth::user();

        if (! $user->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'Usuario inactivo. Contacta al administrador.',
            ])->onlyInput('email');
        }

        $sessionAntes = $request->session()->getId();
        $teniaOtraSesion = DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $sessionAntes)
            ->exists();

        if (! $teniaOtraSesion && $user->current_session_id && $user->current_session_id !== $sessionAntes) {
            $teniaOtraSesion = true;
        }

        $request->session()->regenerate();
        $nuevaSesionId = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $user->id)
            ->where('id', '!=', $nuevaSesionId)
            ->delete();

        $user->forceFill(['current_session_id' => $nuevaSesionId])->save();

        $defaultUrl = $user->isVendedor() ? route('cotizaciones.index') : route('dashboard');
        $redirect = redirect()->intended($defaultUrl);

        if ($teniaOtraSesion) {
            $redirect->with(
                'warning',
                'Iniciaste sesión en este dispositivo. Se cerró la sesión que estaba abierta en otro lugar.'
            );
        }

        return $redirect;
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        $user = Auth::user();
        if ($user) {
            $user->forceFill(['current_session_id' => null])->save();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Redirección según rol. Vendedor va a Facturación (cotizaciones), resto al dashboard.
     */
    protected function redirectAfterLogin()
    {
        $user = Auth::user();
        if ($user && $user->isVendedor()) {
            return redirect()->route('cotizaciones.index');
        }
        return redirect()->route('dashboard');
    }
}
