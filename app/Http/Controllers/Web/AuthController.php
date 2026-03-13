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
     * Procesar login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();
            $defaultUrl = Auth::user()->isVendedor() ? route('cotizaciones.index') : route('dashboard');
            return redirect()->intended($defaultUrl);
        }

        return back()->withErrors([
            'email' => 'Las credenciales son incorrectas.',
        ])->onlyInput('email');
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
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