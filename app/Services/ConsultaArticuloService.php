<?php

namespace App\Services;

use App\Models\Subasta;
use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Support\Facades\DB;

class ConsultaArticuloService
{
    use EjecutaFuncionesSql;

    public function publicas(Subasta $subasta)
    {
        return DB::table('consulta_articulo as c')->join('usuario as autor', 'autor.id_usuario', '=', 'c.id_usuario')
            ->leftJoin('usuario as vendedor', 'vendedor.id_usuario', '=', 'c.id_responde')
            ->where('c.id_subasta', $subasta->id_subasta)->where('c.pregunta_oculta', false)
            ->whereRaw("exists(select 1 from subasta s join usuario u on u.id_usuario=s.id_usuario where s.id_subasta=c.id_subasta and s.estado_revision='Aceptada' and u.activo)")
            ->select(['c.id_consulta', 'c.pregunta', 'c.fecha_pregunta', 'autor.nombre as autor', 'c.fecha_respuesta', 'c.respuesta_oculta', 'vendedor.nombre as vendedor'])
            ->selectRaw('case when c.respuesta_oculta then null else c.respuesta end as respuesta')
            ->orderBy('c.fecha_pregunta')->orderBy('c.id_consulta')->paginate(15, ['*'], 'preguntas')->withQueryString()->fragment('preguntas-articulo');
    }

    public function preguntar(int $actor, int $subasta, string $texto, string $clave): void
    {
        $this->ejecutar('select preguntar_articulo(?,?,?,?::uuid)', [$actor, $subasta, $texto, $clave], 'pregunta');
    }

    public function responder(int $actor, int $consulta, string $texto): void
    {
        $this->ejecutar('select responder_articulo(?,?,?)', [$actor, $consulta, $texto], 'respuesta');
    }

    public function reportar(int $actor, int $consulta, string $tipo, string $motivo): void
    {
        $this->ejecutar('select reportar_consulta(?,?,?,?)', [$actor, $consulta, $tipo, $motivo], 'motivo');
    }

    public function reportes(string $estado)
    {
        return DB::table('reporte_consulta as r')->join('consulta_articulo as c', 'c.id_consulta', '=', 'r.id_consulta')
            ->join('subasta as s', 's.id_subasta', '=', 'c.id_subasta')->join('usuario as autor', 'autor.id_usuario', '=', 'r.id_usuario')
            ->leftJoin('usuario as moderador', 'moderador.id_usuario', '=', 'r.id_moderador')
            ->when($estado === 'pendientes', fn ($q) => $q->whereNull('r.accion'), fn ($q) => $q->whereNotNull('r.accion'))
            ->select(['r.*', 'c.pregunta', 'c.respuesta', 's.titulo', 's.id_subasta', 'autor.nombre as autor', 'moderador.nombre as moderador'])
            ->orderBy('r.fecha_reporte')->orderBy('r.id_reporte')->paginate(15)->withQueryString();
    }

    public function moderar(int $actor, int $reporte, string $accion, string $motivo): void
    {
        $this->ejecutar('select moderar_consulta(?,?,?,?)', [$actor, $reporte, $accion, $motivo], 'motivo_resolucion');
    }
}
