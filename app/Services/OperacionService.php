<?php

namespace App\Services;

use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Support\Facades\DB;

class OperacionService
{
    use EjecutaFuncionesSql;

    public function ejecutarAccion(string $accion, array $parametros): array
    {
        $sql = [
            'parte' => 'select * from exigir_parte_operacion(?,?)',
            'crear' => 'select crear_operacion_cierre(?) as id',
            'confirmar' => 'select confirmar_entrega(?,?)',
            'incidencia' => 'select abrir_incidencia(?,?,?)',
            'asignar' => 'select asignar_incidencia(?,?)',
            'resolver' => 'select resolver_incidencia(?,?,?,?)',
            'proponer' => 'select proponer_encuentro(?,?,?,?::timestamptz)',
            'responder' => 'select responder_encuentro(?,?,?::boolean,?)',
            'mensaje' => 'select enviar_mensaje_operacion(?,?,?::uuid,?)',
            'leer' => 'select leer_mensajes_operacion(?,?,?)',
            'chatAdmin' => 'select exigir_chat_incidencia(?,?) as id',
        ];

        return $this->ejecutar($sql[$accion], $parametros, 'operacion');
    }

    public function consulta()
    {
        return DB::table('operacion_subasta as o')->join('subasta as s', 's.id_subasta', '=', 'o.id_subasta')
            ->join('usuario as c', 'c.id_usuario', '=', 'o.id_comprador')->join('usuario as v', 'v.id_usuario', '=', 'o.id_vendedor')
            ->select('o.*', 's.titulo', 'c.nombre as comprador', 'v.nombre as vendedor');
    }

    public function estado(int $actor, int $id): array
    {
        $op = $this->ejecutarAccion('parte', [$actor, $id])[0];
        $datos = ['estado' => $op->estado, 'con_fondos' => $op->con_fondos,
            'comprador_confirmo' => $op->fecha_confirmacion_comprador, 'vendedor_confirmo' => $op->fecha_confirmacion_vendedor,
            'evento' => DB::table('evento_operacion')->where('id_operacion', $id)->max('id_evento'),
            'resenas_publicadas' => DB::table('resenas_publicadas')->where('id_operacion', $id)->exists()];
        $datos['firma'] = hash('sha256', json_encode($datos, JSON_THROW_ON_ERROR));

        return $datos;
    }

    public function mensajes(int $id)
    {
        return DB::table('mensaje_operacion as m')->join('usuario as u', 'u.id_usuario', '=', 'm.id_autor')
            ->where('m.id_operacion', $id)->select('m.*', 'u.nombre as autor')->orderByDesc('id_mensaje')
            ->paginate(15, ['*'], 'mensajes')->withQueryString();
    }
}
