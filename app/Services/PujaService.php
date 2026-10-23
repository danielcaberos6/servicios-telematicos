<?php

namespace App\Services;

use App\Models\Subasta;
use App\Models\User;
use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class PujaService
{
    use EjecutaFuncionesSql;

    public function historial(Subasta $subasta, ?User $usuario): LengthAwarePaginator
    {
        abort_unless($usuario && ($usuario->id_usuario === $subasta->id_usuario
            || $subasta->pujas()->where('id_usuario', $usuario->id_usuario)->exists()), 403);

        // Proyectar solo los datos del historial: nunca contactos de los postores.
        return $subasta->pujas()->select(['id_puja', 'id_subasta', 'id_usuario', 'monto', 'fecha_puja'])
            ->orderByDesc('monto')->orderByDesc('id_puja')
            ->paginate(15, ['*'], 'ofertas')->withQueryString()->fragment('historial-ofertas');
    }

    public function registrar(int $actor, int $id, string $monto): void
    {
        $this->ejecutar('select * from registrar_puja(?,?,?::numeric)', [$actor, $id, $monto], 'monto');
    }
}
