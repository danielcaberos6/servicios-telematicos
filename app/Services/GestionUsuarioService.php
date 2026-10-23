<?php

namespace App\Services;

use App\Models\Puja;
use App\Models\Subasta;
use App\Models\User;
use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

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

    public function actividad(int $actor, int $usuario): array
    {
        $this->ejecutar('select exigir_administrador(?)', [$actor], 'cuenta');

        if (User::findOrFail($usuario)->esAdministrador()) {
            return ['cuentaAdministradora' => true, 'acciones' => $this->accionesAdministrativas($usuario)];
        }

        return [
            'cuentaAdministradora' => false,
            'subastas' => Subasta::where('id_usuario', $usuario)->withCount('pujas')
                ->orderByDesc('fecha_inicio')->orderByDesc('id_subasta')
                ->paginate(15, ['*'], 'publicaciones')->withQueryString()->fragment('subastas-usuario'),
            'pujas' => Puja::where('id_usuario', $usuario)->with('subasta')
                ->orderByDesc('fecha_puja')->orderByDesc('id_puja')
                ->paginate(15, ['*'], 'ofertas')->withQueryString()->fragment('pujas-usuario'),
        ];
    }

    private function accionesAdministrativas(int $usuario): LengthAwarePaginator
    {
        $cuentas = DB::table('decision_usuario as d')->join('usuario as u', 'u.id_usuario', '=', 'd.id_usuario')
            ->where('d.id_actor', $usuario)
            ->selectRaw("'cuenta' as tipo, d.id_decision as registro, d.id_usuario as destino, u.nombre as titulo,
                case when d.operacion = 'estado' then
                    case when d.valor_nuevo then 'Cuenta activada' else 'Cuenta desactivada' end
                else case when d.valor_nuevo then 'Rol de administrador asignado' else 'Rol de administrador retirado' end end as resultado,
                d.fecha, null::text as motivo");
        $publicaciones = DB::table('decision_subasta as d')->join('subasta as s', 's.id_subasta', '=', 'd.id_subasta')
            ->where('d.id_revisor', $usuario)->whereNotNull('d.fecha_decision')
            ->selectRaw("'publicacion' as tipo, d.id_decision as registro, d.id_subasta as destino, s.titulo,
                d.estado as resultado, d.fecha_decision as fecha, d.motivo");
        $identidades = DB::table('verificacion_identidad as v')->join('usuario as u', 'u.id_usuario', '=', 'v.id_usuario')
            ->where('v.id_revisor', $usuario)->whereNotNull('v.fecha_decision')
            ->selectRaw("'identidad' as tipo, v.id_verificacion as registro, v.id_verificacion as destino, u.nombre as titulo,
                v.estado as resultado, v.fecha_decision as fecha, v.motivo");

        return DB::query()->fromSub($cuentas->unionAll($publicaciones)->unionAll($identidades), 'actividad')
            ->orderByDesc('fecha')->orderBy('tipo')->orderByDesc('registro')
            ->paginate(15, ['*'], 'acciones')->withQueryString()->fragment('acciones-administrador');
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
