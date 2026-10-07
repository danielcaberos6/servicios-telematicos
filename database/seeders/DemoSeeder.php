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
        $items = $this->articulos();
        foreach ($items as $index => [$title, $category, $condition, $price, $location, $image, $hours, $description]) {
            $owner = $index === 0 ? $demo : $seller;
            $auction = $owner->subastas()->firstOrCreate(['titulo' => $title], ['descripcion' => $description, 'id_categoria' => Categoria::where('nombre_categoria', $category)->value('id_categoria'), 'estado_articulo' => $condition, 'monto_inicial' => $price, 'ubicacion' => $location, 'fecha_inicio' => now()->subDays($index % 4)->subMinutes(15), 'fecha_fin' => now()->addHours($hours)]);
            if (! $auction->imagenes()->exists()) {
                $contents = file_get_contents(public_path('images/ejemplos/'.$image));
                $path = 'subastas/'.$auction->id_subasta.'/'.$image;
                Storage::disk('public')->put($path, $contents);
                $auction->imagenes()->create(['ruta' => $path, 'tamano' => strlen($contents)]);
            }
        }
    }

    private function articulos(): array
    {
        return [
            ['Laptop para estudio y trabajo', 'Electrónica e informática', 'Usado', 2400, 'La Paz, Plaza Abaroa', 'laptop.png', 96, 'Publicación de ejemplo de una laptop para estudio y trabajo. Consulta sus características y acuerda la revisión al momento de la entrega.'],
            ['Cámara fotográfica digital', 'Electrónica e informática', 'Usado', 1200, 'Cochabamba, Plaza Colón', 'camara.jpg', 48, 'Cámara de fotografía de demostración. Revisa la imagen del artículo y coordina la entrega con el vendedor.'],
            ['Bicicleta para paseos y deporte', 'Deporte', 'Usado', 800, 'Santa Cruz, Plaza 24 de Septiembre', 'bicicleta.png', 72, 'Bicicleta de ejemplo para paseos y actividades deportivas. El lugar de encuentro se coordina en la plaza indicada.'],
            ['Camiseta de fútbol firmada', 'Antigüedades y coleccionables', 'Usado', 350, 'La Paz, Plaza España', 'camiseta-firmada.jpg', 120, 'Artículo de colección de demostración. La imagen ilustra una camiseta firmada; esta publicación no acredita autenticidad de las firmas.'],
            ['Consola de videojuegos', 'Videojuegos', 'Nuevo', 1900, 'Sucre, Plaza 25 de Mayo', 'consola.jpg', 144, 'Consola de videojuegos de demostración. Consulta qué accesorios se incluyen antes de participar.'],
            ['Guitarra firmada de colección', 'Instrumentos musicales', 'Usado', 650, 'Cochabamba, Plazuela de las Banderas', 'guitarra-firmada.jpg', 168, 'Guitarra de demostración para la categoría de instrumentos musicales. La publicación de ejemplo no acredita autenticidad de las firmas.'],
            ['Colección de libros', 'Libros, películas y música', 'Usado', 150, 'El Alto, Plaza del Obelisco', 'libros.png', 192, 'Conjunto de libros de demostración. Las fotografías permiten conocer los ejemplares antes de coordinar la entrega.'],
        ];
    }

}
