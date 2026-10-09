<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;

class ImagenService
{
    use EjecutaFuncionesSql;

    public function guardar(int $actor, ?int $subasta, string $ruta, int $tamano): void
    {
        $this->ejecutar('select * from guardar_imagen(?,?,?,?)', [$actor, $subasta, $ruta, $tamano], 'imagenes');
    }

    public function eliminar(int $actor, int $id): void
    {
        $this->ejecutar('select eliminar_imagen(?,?)', [$actor, $id], 'imagenes');
    }
}
