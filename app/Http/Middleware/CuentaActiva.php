<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CuentaActiva
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && ! $request->user()->fresh()?->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $mensaje = 'Tu cuenta está desactivada. Contacta con un administrador.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje], 403);
            }

            return redirect()->route('iniciar-sesion')->withErrors(['correo' => $mensaje]);
        }

        return $next($request);
    }
}
