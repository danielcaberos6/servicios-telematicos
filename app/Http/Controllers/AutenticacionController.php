<?php

namespace App\Http\Controllers;

use App\Http\Requests\InicioSesionRequest;
use App\Http\Requests\RegistroRequest;
use App\Services\UsuarioService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AutenticacionController extends Controller
{
    public function __construct(
        private UsuarioService $usuarioService,
    ) {}

    public function registrar(RegistroRequest $request)
    {
        $data = $request->validated();
        $user = $this->usuarioService->registrar($data);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'))->with('status', 'Tu cuenta está lista. ¡Bienvenido a SubastaYA!');
    }

    public function iniciarSesion(InicioSesionRequest $request)
    {
        $data = $request->validated();
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

        return redirect()->intended(route('inicio'));
    }

    public function cerrarSesion(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('inicio')->with('status', 'Cerraste sesión correctamente.');
    }
}
