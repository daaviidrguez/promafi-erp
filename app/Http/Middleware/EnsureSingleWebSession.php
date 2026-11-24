<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una sola sesión web activa por usuario.
 * Si el usuario inició sesión en otro dispositivo, cierra esta y redirige al login con aviso.
 */
class EnsureSingleWebSession
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $user = Auth::user();
        $sessionId = $request->session()->getId();

        if ($user->current_session_id && $user->current_session_id !== $sessionId) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with(
                    'warning',
                    'Tu sesión se cerró porque se inició sesión con tu usuario en otro dispositivo.'
                );
        }

        return $next($request);
    }
}
