<?php

namespace Database\Seeders;

use App\Models\Subasta;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ActividadDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('La actividad de ejemplo solo se permite en local o pruebas.');
        }
        $this->call(DemoSeeder::class);
        DB::transaction(function () {
            $demo = User::where('correo', 'demo@subastaya.test')->firstOrFail();
            $people = [];
            foreach ([['Ana Quispe', 1], ['Marco Rojas', 3], ['Sofía Mamani', 4], ['Diego Salinas', 5], ['Valeria Flores', 5]] as $i => [$name, $stars]) {
                $people[] = $person = User::firstOrCreate(['correo' => 'participante'.($i + 1).'@subastaya.test'], [
                    'nombre' => $name, 'contrasena' => 'SubastaYA2026!', 'ciudad' => 'La Paz',
                ]);
                User::whereKey($person->id_usuario)->where('nombre', explode(' ', $name)[0].' Demo')->update(['nombre' => $name]);
                if (! DB::table('resenas')->where('id_usuario_resenado', $demo->id_usuario)->where('id_usuario_resenador', $person->id_usuario)->exists()) {
                    DB::table('resenas')->insert([
                        'id_usuario_resenado' => $demo->id_usuario, 'id_usuario_resenador' => $person->id_usuario,
                        'calificacion' => $stars, 'comentario' => 'Valoración de prueba para mostrar el gráfico del perfil.',
                        'fecha_creacion' => now()->subDays(5 - $i),
                    ]);
                }
            }
            $auction = Subasta::where('id_usuario', $demo->id_usuario)->where('titulo', 'Laptop para estudio y trabajo')->lockForUpdate()->first();
            if ($auction && $auction->estado === 'Activa' && ! $auction->pujas()->exists()) {
                foreach ([0, 1, 0, 2, 1] as $i => $personIndex) {
                    $amount = number_format((float) $auction->monto_inicial + ($i + 1) * 100, 2, '.', '');
                    // Datos sintéticos del historial, igual que ReportesDemoSeeder.
                    // Nunca registrar una aceptación de normas en nombre de una cuenta.
                    DB::table('puja')->insert(['id_usuario' => $people[$personIndex]->id_usuario, 'id_subasta' => $auction->id_subasta, 'monto' => $amount]);
                }
            }
        });
    }
}
