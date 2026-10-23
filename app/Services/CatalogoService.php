<?php

namespace App\Services;

use App\Models\Categoria;
use App\Models\Subasta;
use Illuminate\Database\Eloquent\Builder;

class CatalogoService
{
    public function consultar(array $filters = []): Builder
    {
        $query = Subasta::query()
            ->select('subasta.*')
            ->fromRaw('catalogo_subastas(?::jsonb) as subasta', [json_encode($filters, JSON_THROW_ON_ERROR)])
            ->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto');
        if ($this->filtraPorRadio($filters)) {
            $query->selectRaw('distancia_km(?::numeric, ?::numeric, subasta.latitud, subasta.longitud) as distancia', [$filters['lat'], $filters['lng']]);
        }

        return $query;
    }

    public function filtraPorRadio(array $filters): bool
    {
        return isset($filters['lat'], $filters['lng'], $filters['radio']);
    }

    public function paginar(array $filters)
    {
        [$column, $direction] = match ($filters['orden'] ?? 'recientes') {
            'finalizan' => ['fecha_fin', 'asc'], 'precio_asc' => ['monto_inicial', 'asc'],
            'precio_desc' => ['monto_inicial', 'desc'],
            'cercanas' => $this->filtraPorRadio($filters) ? ['distancia', 'asc'] : ['fecha_inicio', 'desc'],
            default => ['fecha_inicio', 'desc'],
        };

        return $this->consultar($filters)->orderBy($column, $direction)->orderByDesc('id_subasta')->paginate(12)->withQueryString();
    }

    public function categorias()
    {
        return Categoria::query()->fromRaw('listar_categorias() as categoria')->get();
    }

    public function categoriasPublicacion(?int $actual = null)
    {
        return Categoria::where('habilitada', true)->when($actual, fn ($query) => $query->orWhere('id_categoria', $actual))
            ->orderBy('nombre_categoria')->get();
    }
}
