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
                abort(403, 'Solo los administradores pueden acceder a esta sección.');
            }

            return redirect()->route('inicio')->withErrors(['acceso' => 'Esta sección está disponible solo para administradores.']);
        }

        return $next($request);
    }
}
