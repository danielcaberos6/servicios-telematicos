<?php

namespace App\Services;

use App\Models\Notificacion;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Puente entre los controladores HTTP y las operaciones de Funciones.sql. */
class OperacionesService
{
    private function ejecutar(string $sql, array $parameters, string $field = 'subasta'): array
    {
        try {
            // Un savepoint permite recuperar la transacción externa si PostgreSQL rechaza la operación.
            return DB::transaction(fn () => DB::select($sql, $parameters));
        } catch (QueryException $e) {
            $state = $e->errorInfo[0] ?? $e->getCode();
            if ($state === '42501') {
                abort(403);
            }
            if ($state === 'P0002') {
                abort(404);
            }
            if ($state === 'P0001') {
                // Solo el mensaje de negocio, sin SQL, parámetros ni detalles de conexión.
                $message = preg_replace('/^.*?ERROR:\s*/s', '', $e->errorInfo[2] ?? 'Operación no permitida.');
                throw ValidationException::withMessages([$field => trim(explode("\n", $message)[0])]);
            }
            if ($state === '23505' && $field === 'correo') {
                throw ValidationException::withMessages(['correo' => 'Este correo ya está registrado.']);
            }
            throw $e;
        }
    }

    private function json(array $data): string
    {
        return json_encode($data, JSON_THROW_ON_ERROR);
    }

    public function registrarUsuario(array $data): User
    {
        $row = $this->ejecutar('select * from crear_usuario(?::jsonb)', [$this->json([
            'nombre' => $data['nombre'], 'correo' => $data['correo'], 'contrasena' => Hash::make($data['password']),
        ])], 'correo')[0];

        return (new User)->newFromBuilder((array) $row);
    }

    public function actualizarUsuario(int $actor, array $data): User
    {
        if (! empty($data['password'])) {
            $data['contrasena'] = Hash::make($data['password']);
        }
        unset($data['password']);
        $row = $this->ejecutar('select * from actualizar_usuario(?,?::jsonb)', [$actor, $this->json($data)], 'correo')[0];

        return (new User)->newFromBuilder((array) $row);
    }

    public function crearSubasta(int $actor, array $data): Subasta
    {
        $row = $this->ejecutar('select * from crear_subasta(?,?::jsonb)', [$actor, $this->json($data)])[0];

        return (new Subasta)->newFromBuilder((array) $row);
    }

    public function actualizarSubasta(int $actor, int $id, array $data): void
    {
        $this->ejecutar('select * from actualizar_subasta(?,?,?::jsonb)', [$actor, $id, $this->json($data)]);
    }

    public function eliminarSubasta(int $actor, int $id): array
    {
        $row = $this->ejecutar('select array_to_json(eliminar_subasta(?,?)) as rutas', [$actor, $id])[0];

        return json_decode($row->rutas, true, 512, JSON_THROW_ON_ERROR);
    }

    public function misSubastas(int $actor)
    {
        return Subasta::query()->select('subasta.*')->fromRaw('listar_mis_subastas(?) as subasta', [$actor])
            ->with(['imagenes', 'categoria'])->withCount('pujas')->withMax('pujas', 'monto')->orderByDesc('fecha_inicio')->paginate(12);
    }

    public function pujar(int $actor, int $id, string $monto): void
    {
        $this->ejecutar('select * from registrar_puja(?,?,?::numeric)', [$actor, $id, $monto], 'monto');
    }

    public function ranking(int $id, int $actor): array
    {
        return $this->ejecutar('select * from ranking_subasta(?,?)', [$id, $actor]);
    }

    public function guardarImagen(int $actor, ?int $subasta, string $ruta, int $tamano): void
    {
        $this->ejecutar('select * from guardar_imagen(?,?,?,?)', [$actor, $subasta, $ruta, $tamano], 'imagenes');
    }

    public function eliminarImagen(int $actor, int $id): void
    {
        $this->ejecutar('select eliminar_imagen(?,?)', [$actor, $id], 'imagenes');
    }

    public function notificaciones(int $actor, array $filters)
    {
        return Notificacion::query()->fromRaw('listar_notificaciones(?,?,?::boolean) as notificacion', [
            $actor, $filters['q'] ?? '', ($filters['solo_no_leidas'] ?? false) ? 'true' : 'false',
        ])->orderByDesc('fecha_notificacion')->orderByDesc('id_notificacion')->paginate(15)->withQueryString();
    }

    public function leerNotificacion(int $actor, int $id): void
    {
        $this->ejecutar('select marcar_notificacion(?,?)', [$actor, $id]);
    }

    public function leerTodas(int $actor): void
    {
        $this->ejecutar('select leer_todas_notificaciones(?)', [$actor]);
    }

    public function eliminarNotificacion(int $actor, int $id): void
    {
        $this->ejecutar('select eliminar_notificacion(?,?)', [$actor, $id]);
    }

    public function resenas(int $usuario)
    {
        return collect(DB::select('select * from listar_resenas(?)', [$usuario]));
    }

    public function valoraciones(int $usuario)
    {
        return collect(DB::select('select * from resumen_valoraciones(?)', [$usuario]));
    }
}
