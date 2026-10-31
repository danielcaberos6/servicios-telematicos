<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReputacionService
{
    use EjecutaFuncionesSql;

    public function valorar(int $actor, int $operacion, int $estrellas, ?string $comentario): void
    {
        $this->ejecutar('select valorar_operacion(?,?,?::numeric,?)', [$actor, $operacion, $estrellas, $comentario], 'valoracion');
    }

    public function perfil(int $usuario): ?object
    {
        return DB::selectOne('select * from perfil_publico(?)', [$usuario]);
    }

    public function resumen(int $usuario): array
    {
        return DB::select('select * from reputacion_publica(?)', [$usuario]);
    }

    public function resenas(int $usuario, bool $historicas = false)
    {
        return DB::table('resenas_publicadas as r')->join('usuario as u', 'u.id_usuario', '=', 'r.id_usuario_resenador')
            ->select('r.*', 'u.nombre as autor')->where('r.id_usuario_resenado', $usuario)
            ->when($historicas, fn ($q) => $q->whereNull('r.id_operacion'), fn ($q) => $q->whereNotNull('r.id_operacion'))
            ->orderByDesc('r.fecha_creacion')->orderByDesc('r.id_resena')->paginate(15, ['*'], $historicas ? 'historicas' : 'resenas')->withQueryString();
    }

    public function valorarDisponible(int $actor, int $operacion): object
    {
        $op = $this->ejecutar('select * from exigir_parte_operacion(?,?)', [$actor, $operacion])[0];
        // Solo se consulta la reseña propia en crudo. La de la contraparte se lee desde la vista pública.
        $propia = DB::table('resenas')->where('id_operacion', $operacion)->where('id_usuario_resenador', $actor)->first();
        $publicada = DB::table('resenas_publicadas')->where('id_operacion', $operacion)->exists();
        $plazo = $op->fecha_resolucion ? Carbon::parse($op->fecha_resolucion)->timezone('America/La_Paz')->addDays(14) : null;

        return (object) ['propia' => $propia, 'publicada' => $publicada, 'plazo' => $plazo, 'puede' => $op->estado === 'Completada' && ! $propia && $plazo?->isFuture()];
    }

    public function reportar(int $actor, int $resena, string $categoria, string $detalle): void
    {
        $this->ejecutar('select reportar_resena(?,?,?,?)', [$actor, $resena, $categoria, $detalle], 'reporte');
    }

    public function moderar(int $actor, int $reporte, string $accion, string $motivo): void
    {
        $this->ejecutar('select moderar_resena(?,?,?,?)', [$actor, $reporte, $accion, $motivo], 'reporte');
    }
}
