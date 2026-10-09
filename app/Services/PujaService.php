<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;

class PujaService
{
    use EjecutaFuncionesSql;

    public function registrar(int $actor, int $id, string $monto): void
    {
        $this->ejecutar('select * from registrar_puja(?,?,?::numeric)', [$actor, $id, $monto], 'monto');
    }
}
