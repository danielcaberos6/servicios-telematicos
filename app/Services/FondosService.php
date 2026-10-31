<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Support\Facades\DB;

class FondosService
{
    use EjecutaFuncionesSql;

    public function saldo(int $actor, ?int $titular = null): object
    {
        return $this->ejecutar('select * from consultar_billetera(?,?)', [$actor, $titular ?? $actor], 'monto')[0];
    }

    public function solicitar(int $actor, string $id, string $monto): object
    {
        return $this->ejecutar('select * from solicitar_recarga(?,?::uuid,?::numeric)', [$actor, $id, $monto], 'monto')[0];
    }

    public function resolver(int $actor, string $id, string $resultado): object
    {
        return $this->ejecutar('select * from resolver_recarga(?,?::uuid,?)', [$actor, $id, $resultado], 'resultado')[0];
    }

    public function movimientos(int $actor, ?int $titular = null)
    {
        $this->saldo($actor, $titular);

        return DB::table('movimiento_fondos')->where('id_usuario', $titular ?? $actor)
            ->orderByDesc('fecha_movimiento')->orderByDesc('id_movimiento')->paginate(15, ['*'], 'movimientos')->withQueryString();
    }

    public function recargas(int $actor, ?int $titular = null)
    {
        $this->saldo($actor, $titular);

        return DB::table('recarga')->where('id_usuario', $titular ?? $actor)
            ->orderByDesc('fecha_creacion')->orderBy('id_recarga')->paginate(15, ['*'], 'recargas')->withQueryString();
    }
}
