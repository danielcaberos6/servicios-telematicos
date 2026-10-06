<?php

namespace App\Http\Controllers;

use App\Services\OperacionesService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['correo' => mb_strtolower(trim((string) $request->input('correo')))]);
        $data = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'correo' => ['required', 'email', 'max:254', 'unique:usuario,correo'],
            'password' => ['required', 'string', 'min:8', 'max:72', 'confirmed'],
        ]);
        $user = app(OperacionesService::class)->registrarUsuario($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('home'))->with('status', 'Tu cuenta está lista. ¡Bienvenido a SubastaYA!');
    }

    public function login(Request $request)
    {
        $request->merge(['correo' => mb_strtolower(trim((string) $request->input('correo')))]);
        $data = $request->validate(['correo' => ['required', 'email'], 'password' => ['required', 'string']]);
        $key = 'login:'.hash('sha256', $data['correo'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['correo' => 'Demasiados intentos. Vuelve a intentar en '.RateLimiter::availableIn($key).' segundos.']);
        }
        if (! Auth::attempt($data, $request->boolean('remember'))) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['correo' => 'El correo o la contraseña no son correctos.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Cerraste sesión correctamente.');
    }
}
