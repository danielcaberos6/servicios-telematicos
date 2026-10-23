<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReportesDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los ejemplos de reportes solo se permiten en local o pruebas.');
        }
        $this->call(ActividadDemoSeeder::class);
        DB::transaction(function () {
            $base = now()->startOfMonth()->subMonths(5);
            $admin = User::firstOrCreate(['correo' => 'admin@subastaya.test'], [
                'nombre' => 'Mariana Salazar', 'ciudad' => 'La Paz', 'contrasena' => 'SubastaYA2026!',
            ]);
            if ($admin->wasRecentlyCreated) {
                $admin->forceFill(['fecha_registro' => $base->copy()->subMonths(2)])->save();
            }
            DB::table('usuario_rol')->insertOrIgnore([
                'id_usuario' => $admin->id_usuario,
                'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
            ]);
            $people = collect();
            foreach ([
                ['Camila Torres', 'Cochabamba'], ['Andrés Vargas', 'Santa Cruz'], ['Fernanda Méndez', 'La Paz'],
                ['Gabriel Ríos', 'Sucre'], ['Paola Álvarez', 'Tarija'], ['Luis Cárdenas', 'Oruro'],
                ['Daniela Paredes', 'El Alto'], ['Jorge Molina', 'Potosí'], ['Isabel Herrera', 'Trinidad'],
                ['Santiago Chávez', 'Santa Cruz'], ['Natalia Romero', 'Cochabamba'], ['Martín Aguilar', 'Sucre'],
                ['Carolina Peña', 'La Paz'], ['Sebastián Cruz', 'Tarija'], ['Alejandra Soto', 'El Alto'], ['Pablo Arce', 'Oruro'],
            ] as $i => [$name, $city]) {
                $person = User::firstOrCreate(['correo' => 'comunidad'.($i + 1).'@subastaya.test'], [
                    'nombre' => $name, 'ciudad' => $city, 'contrasena' => 'SubastaYA2026!',
                    'biografia' => 'Me interesa comprar y vender artículos para darles una nueva oportunidad.',
                ]);
                if ($person->wasRecentlyCreated) {
                    $registration = $i < 3 ? $base->copy() : $base->copy()->addMonths(($i - 3) % 6)->addDays($i % 12);
                    $person->forceFill(['fecha_registro' => $registration->min(now()->subHour())])->save();
                }
                $people->push($person);
            }
            $items = [
                ['Vehículos', 'Motocicleta urbana 150 cc', 6500, null],
                ['Antigüedades y coleccionables', 'Camiseta firmada de colección', 320, 'camiseta-firmada.jpg'],
                ['Electrónica e informática', 'Cámara fotográfica con accesorios', 1450, 'camara.jpg'],
                ['Muebles', 'Escritorio de madera restaurado', 580, null],
                ['Juguetes y juegos', 'Ajedrez con piezas de madera', 120, null],
                ['Ropa, calzado y accesorios', 'Chaqueta de invierno', 180, null],
                ['Instrumentos musicales', 'Guitarra para estudio musical', 700, 'guitarra-firmada.jpg'],
                ['Deporte', 'Bicicleta para paseos urbanos', 850, 'bicicleta.png'],
                ['Videojuegos', 'Consola con control inalámbrico', 2100, 'consola.jpg'],
                ['Libros, películas y música', 'Selección de novelas y cuentos', 160, 'libros.png'],
                ['Varios', 'Kit de herramientas para el hogar', 230, null],
                ['Electrónica e informática', 'Laptop para trabajo y estudio', 2800, 'laptop.png'],
            ];
            $number = 0;
            foreach ([4, 6, 5, 8, 10, 7] as $month => $quantity) {
                for ($j = 0; $j < $quantity; $j++, $number++) {
                    $published = $base->copy()->addMonths($month)->addDays(2 + $j)->setTime(10, 30)->min(now()->subHour());
                    $eligible = $people->filter(fn ($person) => $person->fecha_registro->lte($published))->values();
                    $owner = $eligible[$number % $eligible->count()];
                    [$category, $title, $price, $image] = $items[$number % count($items)];
                    $state = ['Finalizada', 'Activa', 'Cancelada', 'Finalizada'][$number % 4];
                    $end = $state === 'Activa' ? now()->addDays(3 + $number % 12) : $published->copy()->addDays(2)->min(now()->subMinutes(15));
                    // En los primeros días de un mes, el cierre histórico debe seguir al inicio.
                    if ($end->lte($published)) {
                        $published = $end->copy()->subDay();
                    }
                    $auction = $owner->subastas()->firstOrNew(['titulo' => $title.' · lote '.($number + 1).' / '.$base->format('Y-m')]);
                    if ($auction->exists) {
                        continue;
                    }
                    // Lotes históricos ya revisados: se insertan aceptados y con su estado final.
                    $auction->forceFill([
                        'descripcion' => 'Artículo disponible para revisar al coordinar la entrega. Publicación de ejemplo para explorar los reportes del sistema.',
                        'ubicacion' => $owner->ciudad.', plaza principal', 'id_categoria' => Categoria::where('nombre_categoria', $category)->value('id_categoria'),
                        'estado_articulo' => $number % 3 === 0 ? 'Nuevo' : 'Usado', 'monto_inicial' => $price + ($number % 5) * 20,
                        'fecha_inicio' => $published, 'fecha_fin' => $end, 'estado_revision' => 'Aceptada', 'estado_subasta' => $state,
                    ])->save();
                    if ($image) {
                        $path = 'subastas/'.$auction->id_subasta.'/'.$image;
                        $contents = file_get_contents(public_path('imagenes/ejemplos/'.$image));
                        Storage::disk('public')->put($path, $contents);
                        $auction->imagenes()->create(['ruta' => $path, 'tamano' => strlen($contents)]);
                    }
                    if ($state === 'Activa') {
                        $bidders = $eligible->reject(fn ($person) => $person->id_usuario === $owner->id_usuario)->values();
                        for ($bid = 0; $bid < 2 + intdiv($number, 4) % 4; $bid++) {
                            DB::table('puja')->insert([
                                'id_subasta' => $auction->id_subasta, 'id_usuario' => $bidders[$bid % $bidders->count()]->id_usuario,
                                'monto' => $auction->monto_inicial + ($bid + 1) * 25,
                                'fecha_puja' => $published->copy()->addHours($bid + 1)->min(now()->subMinutes(5)),
                            ]);
                        }
                    }
                }
            }
            foreach ($people as $i => $person) {
                for ($r = 0; $r < 1 + $i % 4; $r++) {
                    $reviewer = $people[($i + $r + 1) % $people->count()];
                    if (DB::table('resenas')->where('id_usuario_resenado', $person->id_usuario)->where('id_usuario_resenador', $reviewer->id_usuario)->exists()) {
                        continue;
                    }
                    $date = $person->fecha_registro->copy()->max($reviewer->fecha_registro)->addDay()->max(now()->subDays(45 - $i));
                    DB::table('resenas')->insert([
                        'id_usuario_resenado' => $person->id_usuario, 'id_usuario_resenador' => $reviewer->id_usuario,
                        'calificacion' => [5, 4, 5, 3, 4, 2, 5, 1][($i + $r) % 8],
                        'comentario' => ['Buena comunicación al coordinar la entrega.', 'El artículo coincidía con la descripción.', 'La coordinación podría ser más rápida.'][$r % 3],
                        'fecha_creacion' => $date->min(now()->subMinutes(5)),
                    ]);
                }
            }
        });
    }
}
