<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Administrador inicial fuera de local y pruebas, donde no se cargan las
 * cuentas de demostración. Usa ADMIN_CORREO y ADMIN_CONTRASENA; si faltan,
 * mantiene temporalmente las credenciales de demostración, que deben
 * cambiarse después desde el perfil.
 */
class AdministradorSeeder extends Seeder
{
    public const CORREO_PREDETERMINADO = 'admin@subastaya.test';

    public const CONTRASENA_PREDETERMINADA = 'Admin2026!';

    public function run(): void
    {
        // En local y pruebas, CuentasDemoSeeder crea este administrador junto con las demás cuentas.
        if (app()->environment(['local', 'testing'])) {
            return;
        }

        $correo = mb_strtolower(trim((string) (env('ADMIN_CORREO') ?: self::CORREO_PREDETERMINADO)));
        $contrasena = (string) (env('ADMIN_CONTRASENA') ?: self::CONTRASENA_PREDETERMINADA);

        DB::transaction(function () use ($correo, $contrasena) {
            // Si ya existe, los reinicios respetan su contraseña, su estado y sus roles.
            if (User::whereRaw('lower(correo) = ?', [$correo])->exists()) {
                return;
            }
            $usuario = User::create(['nombre' => 'Administrador', 'correo' => $correo, 'contrasena' => $contrasena, 'ciudad' => 'La Paz']);
            DB::table('usuario_rol')->insertOrIgnore([
                'id_usuario' => $usuario->id_usuario,
                'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
            ]);
        });
    }
}
