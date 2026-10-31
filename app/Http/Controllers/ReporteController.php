<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReporteRequest;
use App\Services\CatalogoService;
use App\Services\ReporteService;
use Illuminate\Http\Request;

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

    public function ventas(ReporteRequest $request, ReporteService $reportes)
    {
        return $this->economico($request, $reportes, 'ventas');
    }

    public function movimientos(ReporteRequest $request, ReporteService $reportes)
    {
        return $this->economico($request, $reportes, 'movimientos');
    }

    private function economico(ReporteRequest $request, ReporteService $reportes, string $tipo)
    {
        $filtros = $request->validated();
        $datos = $reportes->consultar($tipo, $request->user()->id_usuario, $filtros);

        return ($request->expectsJson() ? response()->json($datos) : response()->view('reportes.'.$tipo, compact('datos', 'filtros', 'tipo')))
            ->header('Cache-Control', 'no-store, private');
    }

    public function movimiento(Request $request, int $movimiento, ReporteService $reportes)
    {
        $datos = $reportes->movimiento($request->user()->id_usuario, $movimiento);

        return ($request->expectsJson() ? response()->json($datos) : response()->view('reportes.movimiento', compact('datos')))
            ->header('Cache-Control','no-store, private');
    }
}
