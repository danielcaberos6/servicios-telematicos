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

    public function misSubastas(int $actor, ?string $apartado = null)
    {
        return Subasta::query()->select('subasta.*')->fromRaw('listar_mis_subastas(?) as subasta', [$actor])
            ->when($apartado === 'revision', fn ($query) => $query->where('estado_revision', 'En revisión'))
            ->when($apartado === 'rechazadas', fn ($query) => $query->where('estado_revision', 'Rechazada'))
            ->when($apartado === 'publicadas', fn ($query) => $query->where('estado_revision', 'Aceptada')->where('estado_subasta', 'Activa')->where('fecha_fin', '>', now()))
            ->when($apartado === 'finalizadas', fn ($query) => $query->where('estado_revision', 'Aceptada')->where(function ($query) {
                $query->where('estado_subasta', 'Finalizada')->orWhere(fn ($query) => $query->where('estado_subasta', 'Activa')->where('fecha_fin', '<=', now()));
            }))
            ->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto')->orderByDesc('fecha_inicio')->paginate(12)->withQueryString();
    }

    public function ranking(int $id, int $actor): array
    {
        return $this->ejecutar('select * from ranking_subasta(?,?)', [$id, $actor]);
    }

    public function resolverRevision(int $actor, int $id, string $decision, ?string $motivo = null): void
    {
        $this->ejecutar('select resolver_revision_subasta(?,?,?,?)', [$actor, $id, $decision, $motivo], 'decision');
    }

    public function solicitudesRevision(array $filtros = [])
    {
        return app(RevisionSubastaService::class)->listar($filtros);
    }
}
