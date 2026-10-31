<?php

namespace App\Http\Controllers;

use App\Models\Subasta;
use App\Services\ReputacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ReputacionController extends Controller
{
    public function show(Request $r, int $usuario, ReputacionService $s)
    {
        $r->validate(['resenas' => ['sometimes', 'integer', 'min:1'], 'historicas' => ['sometimes', 'integer', 'min:1'], 'publicaciones' => ['sometimes', 'integer', 'min:1']]);
        $perfil = $s->perfil($usuario);
        if (! $perfil) {
            return response()->view('reputacion.no-disponible', [], 404)->header('Cache-Control', 'no-store');
        }
        $resumen = $s->resumen($usuario);
        $resenas = $s->resenas($usuario)->fragment('valoraciones');
        $historicas = $s->resenas($usuario, true)->fragment('historicas');
        // No se serializa el modelo de usuario, sus documentos ni datos de contacto.
        $subastas = Subasta::where('id_usuario', $usuario)->where('estado_revision', 'Aceptada')
            ->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto')
            ->orderByDesc('id_subasta')->paginate(12, ['*'], 'publicaciones')->withQueryString()->fragment('publicaciones');

        return response()->view('reputacion.show', compact('perfil', 'resumen', 'resenas', 'historicas', 'subastas'))->header('Cache-Control', 'no-store, private');
    }

    public function valorar(Request $r, int $operacion, ReputacionService $s)
    {
        $d = $r->validate(['estrellas' => ['required', 'integer', 'between:1,5'], 'comentario' => ['nullable', 'string', 'max:1000']]);
        $s->valorar($r->user()->id_usuario, $operacion, (int) $d['estrellas'], $d['comentario'] ?? null);

        return back()->with('status', 'Valoración guardada. Se publica cuando ambos valoren o termine el plazo de 14 días.');
    }

    public function reportar(Request $r, int $resena, ReputacionService $s)
    {
        $d = $r->validate(['categoria' => ['required', Rule::in(['Insultos', 'Acoso', 'Datos personales'])], 'detalle' => ['required', 'string', 'min:5', 'max:1000']]);
        $s->reportar($r->user()->id_usuario, $resena, $d['categoria'], $d['detalle']);

        return back()->with('status', 'Reporte registrado para revisión administrativa.');
    }

    public function index(Request $r)
    {
        $d = $r->validate(['estado' => ['nullable', Rule::in(['pendientes', 'resueltos'])], 'page' => ['sometimes', 'integer', 'min:1']]);
        // El contenido original solo se consulta al revisar un reporte publicado.
        $reportes = DB::table('reporte_resena as p')->join('resenas as r', 'r.id_resena', '=', 'p.id_resena')
            ->join('usuario as u', 'u.id_usuario', '=', 'r.id_usuario_resenado')
            ->select('p.*', 'r.comentario', 'r.calificacion', 'r.comentario_oculto', 'r.id_usuario_resenado', 'u.nombre as titular')
            ->when(($d['estado'] ?? 'pendientes') === 'pendientes', fn ($q) => $q->whereNull('p.accion'), fn ($q) => $q->whereNotNull('p.accion'))
            ->orderBy('p.id_reporte')->paginate(15)->withQueryString();

        return response()->view('reputacion.moderacion', compact('reportes'))->header('Cache-Control', 'no-store, private');
    }

    public function moderar(Request $r, int $reporte, ReputacionService $s)
    {
        $d = $r->validate(['accion' => ['required', Rule::in(['Ocultar comentario', 'Sin acción'])], 'motivo' => ['required', 'string', 'min:5', 'max:2000']]);
        $s->moderar($r->user()->id_usuario, $reporte, $d['accion'], $d['motivo']);

        return back()->with('status', 'Decisión registrada. La puntuación y la operación se conservan.');
    }
}
