<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class PerfilController extends Controller
{
    /**
     * Mostrar el formulario de perfil
     */
    public function edit()
    {
        return view('perfil.edit');
    }

    /**
     * Actualizar datos personales (nombre, email, avatar)
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        $validated = $request->validate([
            'name'   => ['required', 'string', 'max:255'],
            'email'  => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'avatar' => ['nullable', 'image', 'max:2048'], // max 2MB
        ], [
            'name.required'   => 'El nombre es obligatorio.',
            'email.required'  => 'El correo es obligatorio.',
            'email.email'     => 'El correo no tiene un formato válido.',
            'email.unique'    => 'Este correo ya está en uso por otra cuenta.',
            'avatar.image'    => 'El archivo debe ser una imagen.',
            'avatar.max'      => 'La imagen no debe superar los 2MB.',
        ]);

        // Subir avatar si se envió uno
        if ($request->hasFile('avatar')) {
            // Eliminar avatar anterior si existe
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $validated['avatar'] = $request->file('avatar')->store('avatars', 'public');
        }

        $user->update($validated);

        return redirect()->route('perfil.edit')
                         ->with('success', '✓ Perfil actualizado correctamente.');
    }

    /**
     * Cambiar contraseña
     */
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', Password::min(8)
                                        ->letters()
                                        ->numbers()],
        ], [
            'current_password.required'      => 'Ingresa tu contraseña actual.',
            'current_password.current_password' => 'La contraseña actual es incorrecta.',
            'password.required'              => 'La nueva contraseña es obligatoria.',
            'password.confirmed'             => 'Las contraseñas no coinciden.',
            'password.min'                   => 'La contraseña debe tener al menos 8 caracteres.',
        ]);

        Auth::user()->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('perfil.edit')
                         ->with('success', '✓ Contraseña actualizada correctamente.');
    }
}