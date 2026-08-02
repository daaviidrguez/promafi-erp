<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UsuarioMetaComercialController extends Controller
{
    public function update(Request $request, User $usuario)
    {
        abort_unless(
            $request->user()?->isAdmin() || $request->user()?->hasPermission('usuarios.editar'),
            403
        );

        if (! $usuario->puedeTenerMetaComercial()) {
            return redirect()->route('usuarios.show', $usuario)
                ->with('error', 'Solo admin y vendedor pueden tener meta comercial.');
        }

        $validated = $request->validate([
            'meta_ventas_mensual' => 'required|numeric|min:0.01|max:999999999999.99',
        ], [
            'meta_ventas_mensual.required' => 'La meta mensual es obligatoria.',
            'meta_ventas_mensual.min' => 'La meta mensual debe ser mayor a cero.',
        ]);

        $usuario->update([
            'meta_ventas_mensual' => $validated['meta_ventas_mensual'],
        ]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('usuarios.show', $usuario)
            ->with('success', 'Meta comercial actualizada correctamente');
    }
}
