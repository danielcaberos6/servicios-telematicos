<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\EjecutaFuncionesSql;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuarioService
{
    use EjecutaFuncionesSql;

    public function registrar(array $data): User
    {
        $row = $this->ejecutar('select * from crear_usuario(?::jsonb)', [$this->json([
            'nombre' => $data['nombre'], 'correo' => $data['correo'], 'contrasena' => Hash::make($data['password']),
        ])], 'correo')[0];

        return (new User)->newFromBuilder((array) $row);
    }

    public function actualizar(int $actor, array $data): User
    {
        if (! empty($data['password'])) {
            $data['contrasena'] = Hash::make($data['password']);
        }
        unset($data['password']);
        $row = $this->ejecutar('select * from actualizar_usuario(?,?::jsonb)', [$actor, $this->json($data)], 'correo')[0];

        return (new User)->newFromBuilder((array) $row);
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
