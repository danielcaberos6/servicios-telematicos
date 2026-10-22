<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class GestionUsuarioService
{
    use EjecutaFuncionesSql;

    public function cambiar(int $actor, int $usuario, string $operacion, bool $valor): void
    {
        $this->ejecutar('select gestionar_cuenta(?,?,?,?::boolean)', [$actor, $usuario, $operacion, $valor ? 'true' : 'false'], 'cuenta');
    }

    public function historial(int $actor, int $usuario): array
    {
        return $this->ejecutar('select * from historial_cuenta(?,?)', [$actor, $usuario], 'cuenta');
    }

    public function listar(int $actor, array $filtros): LengthAwarePaginator
    {
        $resultado = $this->ejecutar('select listar_usuarios_gestion(?, ?::jsonb) as datos', [$actor, $this->json($filtros)], 'usuarios')[0];
        $datos = json_decode($resultado->datos, true, 512, JSON_THROW_ON_ERROR);

        return new LengthAwarePaginator($datos['detalle'], $datos['total'], 15, $filtros['page'] ?? 1, [
            'path' => route('admin.usuarios.index'), 'query' => Arr::except($filtros, ['page']),
        ]);
    }
}
