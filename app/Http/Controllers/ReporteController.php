<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteRequest;
use App\Services\CatalogoService;
use App\Services\ReporteService;

class ReporteController extends Controller
{
    public function subastas(ReporteRequest $request, ReporteService $reportes, CatalogoService $catalogo)
    {
        $filtros = $request->validated();
        $datos = $reportes->consultar('subastas', $request->user()->id_usuario, $filtros);

        return $request->expectsJson() ? response()->json($datos) : view('reportes.subastas', [
            'datos' => $datos, 'filtros' => $filtros, 'categorias' => $catalogo->categorias(), 'tipo' => 'subastas',
        ]);
    }

    public function usuarios(ReporteRequest $request, ReporteService $reportes)
    {
        $filtros = $request->validated();
        $datos = $reportes->consultar('usuarios', $request->user()->id_usuario, $filtros);

        return $request->expectsJson() ? response()->json($datos) : view('reportes.usuarios', [
            'datos' => $datos, 'filtros' => $filtros, 'ciudades' => $reportes->ciudades($request->user()->id_usuario), 'tipo' => 'usuarios',
        ]);
    }
}
