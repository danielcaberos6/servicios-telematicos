<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Crea el primer administrador en producción, donde no se cargan las cuentas
 * de demostración. Es seguro repetirlo: si la cuenta existe, solo le asigna el
 * rol y la reactiva, sin cambiar su contraseña.
 */
class CrearAdministrador extends Command
{
    protected $signature = 'subastas:crear-admin
        {correo? : Correo de la cuenta; si falta, se usa ADMIN_CORREO o se pregunta}
        {--nombre=Administrador : Nombre visible de la cuenta nueva}';

    protected $description = 'Crea una cuenta de administrador o asigna el rol a una cuenta existente';

    public function handle(): int
    {
        $correo = $this->argument('correo') ?: env('ADMIN_CORREO');
        if (! $correo && $this->input->isInteractive()) {
            $correo = $this->ask('Correo del administrador');
        }
        $correo = mb_strtolower(trim((string) $correo));
        $nombre = trim((string) $this->option('nombre'));

        $errores = Validator::make(compact('correo', 'nombre'), [
            'correo' => ['required', 'email', 'max:254'],
            'nombre' => ['required', 'string', 'max:100'],
        ])->errors();
        if ($errores->isNotEmpty()) {
            return $this->fallar($errores->first());
        }

        $usuario = User::whereRaw('lower(correo) = ?', [$correo])->first();
        $contrasena = null;
        if (! $usuario) {
            $contrasena = $this->pedirContrasena();
            if ($contrasena === null) {
                return self::FAILURE;
            }
        }

        $idRol = DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol');
        if (! $idRol) {
            return $this->fallar('No existe el rol Administrador. Ejecuta primero php artisan migrate --force y php artisan db:seed --force.');
        }

        $reactivada = false;
        DB::transaction(function () use (&$usuario, &$reactivada, $correo, $nombre, $contrasena, $idRol) {
            if (! $usuario) {
                $usuario = User::create(compact('nombre', 'correo', 'contrasena'));
            } elseif (! $usuario->activo) {
                // Sin una cuenta activa no se podría iniciar sesión para reactivarla.
                $usuario->forceFill(['activo' => true])->save();
                $reactivada = true;
            }
            DB::table('usuario_rol')->insertOrIgnore(['id_usuario' => $usuario->id_usuario, 'id_rol' => $idRol]);
        });

        if ($usuario->wasRecentlyCreated) {
            $this->info("Administrador {$correo} creado. Ya puedes iniciar sesión.");
        } else {
            $this->info("La cuenta {$correo} ya existía: tiene el rol Administrador y conserva su contraseña.");
            if ($reactivada) {
                $this->warn('La cuenta estaba desactivada y se reactivó.');
            }
        }

        return self::SUCCESS;
    }

    private function pedirContrasena(): ?string
    {
        $contrasena = env('ADMIN_CONTRASENA');
        if (! $contrasena) {
            if (! $this->input->isInteractive()) {
                $this->fallar('Define ADMIN_CONTRASENA o ejecuta el comando en una terminal interactiva.');

                return null;
            }
            $contrasena = (string) $this->secret('Contraseña (8 a 72 caracteres)');
            if ($contrasena !== (string) $this->secret('Repite la contraseña')) {
                $this->fallar('Las contraseñas no coinciden.');

                return null;
            }
        }

        // Mismas reglas que el formulario de registro.
        $errores = Validator::make(['contrasena' => $contrasena], ['contrasena' => ['required', 'string', 'min:8', 'max:72']])->errors();
        if ($errores->isNotEmpty()) {
            $this->fallar($errores->first());

            return null;
        }

        return $contrasena;
    }

    private function fallar(string $mensaje): int
    {
        $this->error($mensaje);

        return self::FAILURE;
    }
}
