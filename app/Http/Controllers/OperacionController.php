<?php

namespace App\Http\Controllers;

use App\Services\OperacionService;
use App\Services\ReputacionService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OperacionController extends Controller
{
    public function index(Request $r, OperacionService $s)
    {
        $datos = $r->validate(['estado' => ['nullable', Rule::in(['Pendiente', 'En revisión', 'Completada', 'Reembolsada'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        $actor = $r->user()->id_usuario;
        $operaciones = $s->consulta()->where(fn ($q) => $q->where('o.id_comprador', $actor)->orWhere('o.id_vendedor', $actor))
            ->when($datos['estado'] ?? null, fn ($q, $estado) => $q->where('o.estado', $estado))
            ->selectRaw('(select count(*) from mensaje_operacion m where m.id_operacion=o.id_operacion and m.id_autor<>? and m.id_mensaje>case when o.id_comprador=? then o.leido_comprador else o.leido_vendedor end) as sin_leer', [$actor, $actor])
            ->orderByDesc('o.id_operacion')->paginate(15)->withQueryString();

        return response()->view('operaciones.index', compact('operaciones'))->header('Cache-Control', 'no-store, private');
    }

    public function abrir(Request $r, int $subasta, OperacionService $s)
    {
        $actor = $r->user()->id_usuario;
        $id = DB::transaction(function () use ($subasta, $actor, $s) {
            $articulo = DB::table('subasta')->where('id_subasta', $subasta)->lockForUpdate()->first();
            abort_unless($articulo && $articulo->estado_subasta === 'Finalizada' && $articulo->estado_revision === 'Aceptada', 404);
            $ganador = DB::table('puja')->where('id_subasta', $subasta)->orderByDesc('monto')->orderBy('id_puja')->first();
            abort_unless($ganador && in_array($actor, [$articulo->id_usuario, $ganador->id_usuario]), 404);

            return $s->ejecutarAccion('crear', [$subasta])[0]->id;
        });

        return redirect()->route('operaciones.show', $id);
    }

    public function show(Request $r, int $operacion, OperacionService $s)
    {
        $s->ejecutarAccion('parte', [$r->user()->id_usuario, $operacion]);
        $r->validate(['mensajes' => ['sometimes', 'integer', 'min:1'], 'acuerdos' => ['sometimes', 'integer', 'min:1'], 'eventos' => ['sometimes', 'integer', 'min:1']]);
        $op = $s->consulta()->where('o.id_operacion', $operacion)->first();
        $mensajes = $s->mensajes($operacion);
        $acuerdos = DB::table('acuerdo_operacion')->where('id_operacion', $operacion)->orderByDesc('id_acuerdo')->paginate(15, ['*'], 'acuerdos')->withQueryString();
        $eventos = DB::table('evento_operacion')->where('id_operacion', $operacion)->orderByDesc('id_evento')->paginate(15, ['*'], 'eventos')->withQueryString();
        $casos = DB::table('incidencia_operacion')->where('id_operacion', $operacion)->orderByDesc('id_incidencia')->get();

        return response()->view('operaciones.show', ['op' => $op, 'mensajes' => $mensajes, 'acuerdos' => $acuerdos, 'eventos' => $eventos, 'casos' => $casos, 'referencia' => (string) Str::uuid(), 'valoracion' => app(ReputacionService::class)->valorarDisponible($r->user()->id_usuario, $operacion)])->header('Cache-Control', 'no-store, private');
    }

    public function mensajes(Request $r, int $operacion, OperacionService $s)
    {
        $s->ejecutarAccion('parte', [$r->user()->id_usuario, $operacion]);
        $r->validate(['mensajes' => ['sometimes', 'integer', 'min:1']]);

        return response()->json($s->mensajes($operacion))->header('Cache-Control', 'no-store, private');
    }

    public function accion(Request $r, int $operacion, string $accion, OperacionService $s)
    {
        $s->ejecutarAccion('parte', [$r->user()->id_usuario, $operacion]);
        $params = [$r->user()->id_usuario, $operacion];
        switch ($accion) {
            case 'confirmar': break;
            case 'incidencia':
                $d = $r->validate(['motivo' => ['required', 'string', 'min:5', 'max:2000']]);
                $params[] = $d['motivo'];
                break;
            case 'proponer':
                $d = $r->validate(['lugar' => ['required', 'string', 'min:5', 'max:250'], 'fecha' => ['required', 'date_format:Y-m-d\TH:i']]);
                $params[] = $d['lugar'];
                $params[] = Carbon::createFromFormat('Y-m-d\TH:i', $d['fecha'], 'America/La_Paz')->toIso8601String();
                break;
            case 'mensaje':
                $d = $r->validate(['referencia' => ['required', 'uuid'], 'texto' => ['required', 'string', 'max:2000']]);
                $params[] = $d['referencia'];
                $params[] = $d['texto'];
                break;
            case 'leer':
                $d = $r->validate(['hasta' => ['required', 'integer', 'min:0']]);
                $params[] = $d['hasta'];
                break;
            case 'responder':
                $d = $r->validate(['acuerdo' => ['required', 'integer'], 'aceptar' => ['required', 'boolean'], 'motivo' => ['nullable', 'string', 'max:1000']]);
                abort_unless(DB::table('acuerdo_operacion')->where('id_acuerdo', $d['acuerdo'])->where('id_operacion', $operacion)->exists(), 404);
                $params = [$r->user()->id_usuario, $d['acuerdo'], $d['aceptar'] ? 'true' : 'false', $d['motivo'] ?? ''];
                break;
            default: abort(404);
        }
        $s->ejecutarAccion($accion, $params);
        if ($r->expectsJson()) {
            return response()->json(['ok' => true])->header('Cache-Control', 'no-store, private');
        }

        return back()->with('status', 'Cambio registrado.');
    }

    public function incidencias(Request $r)
    {
        $r->validate(['page' => ['sometimes', 'integer', 'min:1']]);
        $casos = DB::table('incidencia_operacion as i')->join('operacion_subasta as o', 'o.id_operacion', '=', 'i.id_operacion')->join('subasta as s', 's.id_subasta', '=', 'o.id_subasta')
            ->select('i.*', 's.titulo')->orderByRaw("case when i.estado='Resuelta' then 1 else 0 end")->orderBy('i.id_incidencia')->paginate(15);

        return response()->view('operaciones.incidencias', compact('casos'))->header('Cache-Control', 'no-store, private');
    }

    public function caso(Request $r, int $incidencia, OperacionService $s)
    {
        $caso = DB::table('incidencia_operacion')->where('id_incidencia', $incidencia)->first();
        abort_unless($caso, 404);
        $op = $s->consulta()->where('o.id_operacion', $caso->id_operacion)->first();

        return response()->view('operaciones.caso', compact('caso', 'op'))->header('Cache-Control', 'no-store, private');
    }

    public function gestionarCaso(Request $r, int $incidencia, OperacionService $s)
    {
        $d = $r->validate(['accion' => ['required', Rule::in(['asignar', 'resolver'])]]);
        if ($d['accion'] === 'asignar') {
            $s->ejecutarAccion('asignar', [$r->user()->id_usuario, $incidencia]);
        } else {
            $d = $r->validate(['decision' => ['required', Rule::in(['Liquidar', 'Devolver'])], 'motivo' => ['required', 'string', 'min:5', 'max:2000']]);
            $s->ejecutarAccion('resolver', [$r->user()->id_usuario, $incidencia, $d['decision'], $d['motivo']]);
        }

        return back()->with('status', 'Incidencia actualizada.');
    }

    public function chatCaso(Request $r, int $incidencia, OperacionService $s)
    {
        $r->validate(['mensajes' => ['sometimes', 'integer', 'min:1']]);
        $id = $s->ejecutarAccion('chatAdmin', [$r->user()->id_usuario, $incidencia])[0]->id;
        $mensajes = $s->mensajes($id);

        return response()->view('operaciones.chat-caso', compact('mensajes', 'incidencia'))->header('Cache-Control', 'no-store, private');
    }
}
