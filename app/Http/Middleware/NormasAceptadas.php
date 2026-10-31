<?php

namespace App\Http\Middleware;

use App\Services\NormativaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormasAceptadas
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app(NormativaService::class)->aceptada($request->user()->id_usuario)) {
            $mensaje = 'Lee y acepta las normas vigentes antes de publicar, ofertar o recargar.';
            if ($request->expectsJson()) {
                return response()->json(['message' => $mensaje, 'normas' => route('normas.show')], 403);
            }
            $subasta = $request->route('subasta');
            $volver = $subasta ? route('subastas.show', $subasta, false) : route('subastas.create', [], false);
            if ($request->routeIs('subastas.edit', 'subastas.update')) {
                $volver = route('subastas.edit', $subasta, false);
            }
            if ($request->routeIs('billetera.*')) {
                $volver = route('billetera.index', [], false);
            }

            return redirect()->route('normas.show', ['volver' => $volver])->with('status', $mensaje);
        }

        return $next($request);
    }
}
