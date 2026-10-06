<?php

namespace Database\Seeders;

use App\Models\Subasta;
use App\Models\User;
use App\Services\OperacionesService;
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
            foreach ([['Ana', 1], ['Marco', 3], ['Sofía', 4], ['Diego', 5], ['Valeria', 5]] as $i => [$name, $stars]) {
                $people[] = $person = User::firstOrCreate(['correo' => 'participante'.($i + 1).'@subastaya.test'], [
                    'nombre' => $name.' Demo', 'contrasena' => 'SubastaYA2026!', 'ciudad' => 'La Paz',
                ]);
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
                    app(OperacionesService::class)->pujar($people[$personIndex]->id_usuario, $auction->id_subasta, $amount);
                }
            }
        });
    }
}
