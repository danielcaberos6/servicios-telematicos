<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SoloUsuario
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->esAdministrador()) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                return redirect()->route('admin.usuarios.index');
            }

            abort(403, 'Esta función está disponible solo para usuarios, no para administradores.');
        }

        return $next($request);
    }
}
