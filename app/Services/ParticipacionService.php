<?php

namespace App\Services;

use App\Models\Subasta;
use App\Services\Concerns\EjecutaFuncionesSql;

class ParticipacionService
{
    use EjecutaFuncionesSql;

    public const APARTADOS = ['' => 'Todas', 'ganando' => 'Voy ganando', 'superadas' => 'Me superaron', 'ganadas' => 'Ganadas', 'no_ganadas' => 'No ganadas', 'canceladas' => 'Canceladas'];

    public const RESULTADOS = ['ganando' => 'Vas ganando', 'superadas' => 'Te superaron', 'ganadas' => 'Ganada', 'no_ganadas' => 'No ganada', 'canceladas' => 'Cancelada'];

    public function listar(int $actor, ?string $apartado = null)
    {
        return Subasta::query()->select('subasta.*', 'participacion.oferta_propia', 'participacion.oferta_actual', 'participacion.resultado')
            ->fromRaw('listar_participaciones(?) as participacion join subasta on subasta.id_subasta=participacion.id_subasta', [$actor])
            ->leftJoin('operacion_subasta as entrega', fn ($join) => $join->on('entrega.id_subasta', '=', 'subasta.id_subasta')->where('entrega.id_comprador', $actor))
            ->addSelect('entrega.id_operacion', 'entrega.estado as estado_entrega')
            ->when($apartado, fn ($q) => $q->where('participacion.resultado', $apartado))
            ->with('imagenes')->orderByDesc('fecha_fin')->orderByDesc('subasta.id_subasta')->paginate(15)->withQueryString();
    }

    public function favoritos(int $actor)
    {
        return Subasta::query()->fromRaw('listar_favoritos(?) as subasta', [$actor])
            ->with('imagenes')->orderByDesc('fecha_inicio')->orderByDesc('id_subasta')->paginate(15)->withQueryString();
    }

    public function guardar(int $actor, int $subasta): void
    {
        $this->ejecutar('select guardar_favorito(?,?)', [$actor, $subasta]);
    }

    public function retirar(int $actor, int $subasta): void
    {
        $this->ejecutar('select retirar_favorito(?,?)', [$actor, $subasta]);
    }
}
