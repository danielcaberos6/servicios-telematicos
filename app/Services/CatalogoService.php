<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Subasta;
use Illuminate\Database\Eloquent\Builder;

class CatalogoService
{
    public function consultar(array $filters = []): Builder
    {
        return Subasta::query()
            ->select('subasta.*')
            ->fromRaw('catalogo_subastas(?::jsonb) as subasta', [json_encode($filters, JSON_THROW_ON_ERROR)])
            ->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto');
    }

    public function paginar(array $filters)
    {
        [$column, $direction] = match ($filters['orden'] ?? 'recientes') {
            'finalizan' => ['fecha_fin', 'asc'], 'precio_asc' => ['monto_inicial', 'asc'],
            'precio_desc' => ['monto_inicial', 'desc'], default => ['fecha_inicio', 'desc'],
        };

        return $this->consultar($filters)->orderBy($column, $direction)->orderByDesc('id_subasta')->paginate(12)->withQueryString();
    }

    public function categorias()
    {
        return Categoria::query()->fromRaw('listar_categorias() as categoria')->get();
    }
}
