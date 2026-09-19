<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function mostrar(): View
    {
        return view('auth.login');
    }

    public function entrar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], ['email' => 'correo', 'password' => 'contrasena']);

        if (! Auth::attempt($datos + ['activo' => true], $request->boolean('recordar'))) {
            throw ValidationException::withMessages([
                'email' => 'Esas credenciales no coinciden con ningun usuario activo.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('tablero', 'gerencia'));
    }

    public function salir(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
