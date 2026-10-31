<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class ReporteService
{
    use EjecutaFuncionesSql;

    public function consultar(string $tipo, int $actor, array $filtros): array
    {
        $sql = match ($tipo) {
            'subastas' => 'select reporte_subastas(?,?::jsonb) as datos',
            'usuarios' => 'select reporte_usuarios(?,?::jsonb) as datos',
            'ventas' => 'select reporte_ventas(?,?::jsonb) as datos',
            'movimientos' => 'select reporte_movimientos(?,?::jsonb) as datos',
            default => throw new \InvalidArgumentException('Reporte desconocido.'),
        };
        $datos = json_decode($this->ejecutar($sql, [$actor, $this->json($filtros)], 'reportes')[0]->datos, true, 512, JSON_THROW_ON_ERROR);
        $datos['tabla'] = new LengthAwarePaginator($datos['detalle'], $datos['total'], 15, $filtros['page'] ?? 1, [
            'path' => route('reportes.'.$tipo), 'query' => Arr::except($filtros, ['page']),
        ]);

        return $datos;
    }

    public function ciudades(int $actor): array
    {
        return $this->ejecutar('select * from ciudades_reporte(?)', [$actor]);
    }

    public function movimiento(int $actor, int $id): array
    {
        return json_decode($this->ejecutar('select detalle_movimiento_fondos(?,?) as datos', [$actor, $id], 'reportes')[0]->datos, true, 512, JSON_THROW_ON_ERROR);
    }
}
