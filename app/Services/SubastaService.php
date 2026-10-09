<?php

namespace App\Services;

use App\Models\Subasta;
use App\Services\Concerns\EjecutaFuncionesSql;

class SubastaService
{
    use EjecutaFuncionesSql;

    public function crear(int $actor, array $data): Subasta
    {
        $row = $this->ejecutar('select * from crear_subasta(?,?::jsonb)', [$actor, $this->json($data)])[0];

        return (new Subasta)->newFromBuilder((array) $row);
    }

    public function actualizar(int $actor, int $id, array $data): void
    {
        $this->ejecutar('select * from actualizar_subasta(?,?,?::jsonb)', [$actor, $id, $this->json($data)]);
    }

    public function eliminar(int $actor, int $id): array
    {
        $row = $this->ejecutar('select array_to_json(eliminar_subasta(?,?)) as rutas', [$actor, $id])[0];

        return json_decode($row->rutas, true, 512, JSON_THROW_ON_ERROR);
    }

    public function misSubastas(int $actor)
    {
        return Subasta::query()->select('subasta.*')->fromRaw('listar_mis_subastas(?) as subasta', [$actor])
            ->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto')->orderByDesc('fecha_inicio')->paginate(12);
    }

    public function ranking(int $id, int $actor): array
    {
        return $this->ejecutar('select * from ranking_subasta(?,?)', [$id, $actor]);
    }
}
