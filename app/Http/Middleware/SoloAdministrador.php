<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SoloAdministrador
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->esAdministrador()) {
            if ($request->expectsJson()) {
                abort(403, 'Solo el administrador puede consultar los reportes.');
            }

            return redirect()->route('inicio')->withErrors(['acceso' => 'Los reportes están disponibles solo para administradores.']);
        }

        return $next($request);
    }
}
