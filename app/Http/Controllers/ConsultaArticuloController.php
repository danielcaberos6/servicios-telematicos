<?php

namespace App\Http\Controllers;

use App\Models\Subasta;
use App\Services\ConsultaArticuloService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConsultaArticuloController extends Controller
{
    public function preguntar(Request $request, Subasta $subasta, ConsultaArticuloService $servicio)
    {
        $datos = $request->validate(['pregunta' => ['required', 'string', 'max:1000'], 'clave_envio' => ['required', 'uuid']]);
        $servicio->preguntar($request->user()->id_usuario, $subasta->id_subasta, $datos['pregunta'], $datos['clave_envio']);

        return redirect()->to(route('subastas.show', $subasta).'#preguntas-articulo')->with('status', 'Tu pregunta fue enviada. Avisamos al vendedor.');
    }

    public function responder(Request $request, int $consulta, ConsultaArticuloService $servicio)
    {
        $datos = $request->validate(['respuesta' => ['required', 'string', 'max:2000']]);
        $servicio->responder($request->user()->id_usuario, $consulta, $datos['respuesta']);

        return $this->volver($consulta, 'Tu respuesta ya está publicada.');
    }

    public function reportar(Request $request, int $consulta, ConsultaArticuloService $servicio)
    {
        $datos = $request->validate(['tipo' => ['required', Rule::in(['pregunta', 'respuesta'])], 'motivo' => ['required', 'string', 'max:500']]);
        $servicio->reportar($request->user()->id_usuario, $consulta, $datos['tipo'], $datos['motivo']);

        return $this->volver($consulta, 'Tu reporte quedó registrado para revisión.');
    }

    public function index(Request $request, ConsultaArticuloService $servicio)
    {
        $datos = $request->validate(['estado' => ['nullable', Rule::in(['pendientes', 'resueltos'])], 'page' => ['sometimes', 'integer', 'min:1', 'max:100000']]);
        $estado = $datos['estado'] ?? 'pendientes';

        return view('admin.consultas.index', ['reportes' => $servicio->reportes($estado), 'estado' => $estado]);
    }

    public function moderar(Request $request, int $reporte, ConsultaArticuloService $servicio)
    {
        $datos = $request->validate(['accion' => ['required', Rule::in(['ocultar', 'desestimar'])], 'motivo_resolucion' => ['required', 'string', 'max:500']]);
        $servicio->moderar($request->user()->id_usuario, $reporte, $datos['accion'], $datos['motivo_resolucion']);

        return redirect()->route('admin.consultas.index')->with('status', 'Decisión de moderación registrada.');
    }

    private function volver(int $consulta, string $mensaje)
    {
        $id = DB::table('consulta_articulo')->where('id_consulta', $consulta)->value('id_subasta');

        return redirect()->to(route('subastas.show', $id).'#preguntas-articulo')->with('status', $mensaje);
    }
}
