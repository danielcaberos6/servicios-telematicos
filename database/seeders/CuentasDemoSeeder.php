<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CuentasDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            return;
        }

        DB::transaction(function () {
            foreach ([
                ['demo@subastaya.test', 'Rodrigo Vargas', 'SubastaYA2026!', 'La Paz', 'Usuario'],
                ['vendedor@subastaya.test', 'Lucía Mendoza', 'SubastaYA2026!', 'Cochabamba', 'Usuario'],
                ['admin@subastaya.test', 'Administrador', 'Admin2026!', 'La Paz', 'Administrador'],
            ] as [$correo, $nombre, $contrasena, $ciudad, $rol]) {
                $user = User::firstOrCreate(['correo' => $correo], compact('nombre', 'contrasena', 'ciudad'));

                // Los reinicios respetan también las decisiones administrativas existentes.
                if ($user->wasRecentlyCreated) {
                    DB::table('usuario_rol')->insertOrIgnore([
                        'id_usuario' => $user->id_usuario,
                        'id_rol' => DB::table('rol')->where('nombre_rol', $rol)->value('id_rol'),
                    ]);
                }
            }
        });
    }
}
