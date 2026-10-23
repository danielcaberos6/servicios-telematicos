<?php

namespace App\Services;

use App\Models\Subasta;
use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class RevisionSubastaService
{
    use EjecutaFuncionesSql;

    /**
     * Lista solicitudes de subastas para el panel de administración con filtros y paginación.
     */
    public function listar(array $filtros = []): LengthAwarePaginator
    {
        $estado = $filtros['estado'] ?? 'En revisión';
        $busqueda = trim($filtros['q'] ?? '');

        return Subasta::query()
            ->with(['usuario', 'categoria', 'imagenes'])
            ->when($estado !== 'todos', function ($query) use ($estado) {
                $query->where('estado_revision', $estado);
            })
            ->when($busqueda !== '', function ($query) use ($busqueda) {
                $query->where(function ($q) use ($busqueda) {
                    $q->whereRaw('lower(titulo) like ?', ['%'.mb_strtolower($busqueda).'%'])
                        ->orWhereRaw('lower(coalesce(descripcion, \'\')) like ?', ['%'.mb_strtolower($busqueda).'%']);
                });
            })
            ->orderBy('fecha_inicio')
            ->orderBy('id_subasta')
            ->paginate(15)
            ->withQueryString();
    }

    /**
     * Resuelve una solicitud de subasta (Aceptada o Rechazada) invocando la función SQL.
     */
    public function resolver(int $actor, int $idSubasta, string $decision, ?string $motivo = null): void
    {
        $this->ejecutar('select resolver_revision_subasta(?,?,?,?)', [$actor, $idSubasta, $decision, $motivo], 'decision');
    }
}
