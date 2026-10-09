<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Services\Concerns\EjecutaFuncionesSql;

class NotificacionService
{
    use EjecutaFuncionesSql;

    public function listar(int $actor, array $filters)
    {
        return Notificacion::query()->fromRaw('listar_notificaciones(?,?,?::boolean) as notificacion', [
            $actor, $filters['q'] ?? '', ($filters['solo_no_leidas'] ?? false) ? 'true' : 'false',
        ])->orderByDesc('fecha_notificacion')->orderByDesc('id_notificacion')->paginate(15)->withQueryString();
    }

    public function leer(int $actor, int $id): void
    {
        $this->ejecutar('select marcar_notificacion(?,?)', [$actor, $id]);
    }

    public function leerTodas(int $actor): void
    {
        $this->ejecutar('select leer_todas_notificaciones(?)', [$actor]);
    }

    public function eliminar(int $actor, int $id): void
    {
        $this->ejecutar('select eliminar_notificacion(?,?)', [$actor, $id]);
    }
}
