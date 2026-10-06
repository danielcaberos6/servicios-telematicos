<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \RuntimeException('Los datos de demostración solo se cargan en entornos locales o de pruebas.');
        }
        $this->call(DatabaseSeeder::class);
        $demo = User::firstOrCreate(['correo' => 'demo@subastaya.test'], ['nombre' => 'Rodrigo Demo', 'contrasena' => 'SubastaYA2026!', 'ciudad' => 'La Paz', 'biografia' => 'Me gusta encontrar nuevas oportunidades y dar otra vida a mis artículos.']);
        $seller = User::firstOrCreate(['correo' => 'vendedor@subastaya.test'], ['nombre' => 'Lucía Mendoza', 'contrasena' => 'SubastaYA2026!', 'ciudad' => 'Cochabamba']);
        $role = DB::table('rol')->where('nombre_rol', 'Usuario')->value('id_rol');
        foreach ([$demo, $seller] as $user) {
            DB::table('usuario_rol')->insertOrIgnore(['id_usuario' => $user->id_usuario, 'id_rol' => $role]);
        }
        $items = [
            ['Cámara réflex con lente 18–55 mm', 'Tecnología', 'Como nuevo', 1450, 'La Paz, Plaza Abaroa, entrada principal', 'camera', 3, 'Cámara réflex en excelente estado, ideal para comenzar en fotografía. Incluye lente, batería, cargador y correa.'],
            ['Bicicleta urbana de 7 velocidades', 'Deportes', 'Usado', 780, 'Cochabamba, Plaza Colón, fuente central', 'bike', 8, 'Bicicleta para moverte por la ciudad. Frenos revisados, llantas en buen estado y algunos detalles de pintura por el uso.'],
            ['Audífonos inalámbricos de diadema', 'Tecnología', 'Nuevo', 250, 'Santa Cruz, Ventura Mall, acceso principal', 'headphones', 14, 'Audífonos inalámbricos nuevos, en caja. Incluyen cable de carga, entrada auxiliar y almohadillas acolchadas.'],
            ['Sillón de lectura estilo nórdico', 'Hogar', 'Como nuevo', 590, 'La Paz, Sopocachi, Plaza España', 'chair', 22, 'Cómodo sillón tapizado color arena con patas de madera. Se usó muy poco y no tiene manchas. El ganador coordina el transporte.'],
            ['Laptop de 14 pulgadas, 16 GB RAM', 'Tecnología', 'Usado', 2800, 'El Alto, Plaza del Obelisco', 'laptop', 48, 'Laptop para estudio y trabajo. 16 GB de RAM, SSD de 512 GB y cargador original. La batería dura aproximadamente 4 horas.'],
            ['Reloj de pulsera clásico', 'Moda y accesorios', 'Como nuevo', 320, 'Sucre, Plaza 25 de Mayo', 'watch', 72, 'Reloj de pulsera con correa de cuero marrón y esfera clara. Funcionando correctamente. Incluye su estuche.'],
            ['Cámara analógica de colección', 'Coleccionables', 'Usado', 420, 'Cochabamba, Plazuela de las Banderas', 'camera', 96, 'Cámara analógica para colección. Conserva sus piezas originales y presenta marcas propias del tiempo. No incluye rollo.'],
            ['Bicicleta de paseo con canasto', 'Deportes', 'Como nuevo', 950, 'Santa Cruz, Plaza 24 de Septiembre', 'bike', 120, 'Bicicleta de paseo, asiento cómodo y mantenimiento reciente. Ideal para recorridos cortos y paseos de fin de semana.'],
        ];
        foreach ($items as $index => [$title, $category, $condition, $price, $location, $image, $hours, $description]) {
            $owner = $index === 4 ? $demo : $seller;
            $auction = $owner->subastas()->firstOrCreate(['titulo' => $title], ['descripcion' => $description, 'id_categoria' => Categoria::where('nombre_categoria', $category)->value('id_categoria'), 'estado_articulo' => $condition, 'monto_inicial' => $price, 'ubicacion' => $location, 'fecha_inicio' => now()->subDays($index % 4)->subMinutes(15), 'fecha_fin' => now()->addHours($hours)]);
            if (! $auction->imagenes()->exists()) {
                $contents = file_get_contents(public_path('images/demo/'.$image.'.svg'));
                $path = 'subastas/'.$auction->id_subasta.'/demo.svg';
                Storage::disk('public')->put($path, $contents);
                $auction->imagenes()->create(['ruta' => $path, 'tamano' => strlen($contents)]);
            }
        }
    }
}
