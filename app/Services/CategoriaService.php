<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;

class CategoriaService
{
    use EjecutaFuncionesSql;

    public function guardar(int $actor, ?int $id, array $datos): void
    {
        $this->ejecutar('select guardar_categoria(?,?,?::jsonb)', [$actor, $id, $this->json($datos)], 'categoria');
    }

    public function habilitar(int $actor, int $id, bool $valor): void
    {
        $this->ejecutar('select habilitar_categoria(?,?,?::boolean)', [$actor, $id, $valor ? 'true' : 'false'], 'categoria');
    }
}
