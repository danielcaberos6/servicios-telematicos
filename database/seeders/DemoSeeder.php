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
        $demo = User::firstOrCreate(['correo' => 'demo@subastaya.test'], ['nombre' => 'Rodrigo Vargas', 'contrasena' => 'SubastaYA2026!', 'ciudad' => 'La Paz', 'biografia' => 'Me gusta encontrar nuevas oportunidades y dar otra vida a mis artículos.']);
        User::whereKey($demo->id_usuario)->where('nombre', 'Rodrigo Demo')->update(['nombre' => 'Rodrigo Vargas']);
        $seller = User::firstOrCreate(['correo' => 'vendedor@subastaya.test'], ['nombre' => 'Lucía Mendoza', 'contrasena' => 'SubastaYA2026!', 'ciudad' => 'Cochabamba']);
        $admin = User::firstOrCreate(['correo' => 'admin@subastaya.test'], ['nombre' => 'Administrador', 'contrasena' => 'Admin2026!', 'ciudad' => 'La Paz']);

        $role = DB::table('rol')->where('nombre_rol', 'Usuario')->value('id_rol');
        $roleAdmin = DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol');

        foreach ([$demo, $seller] as $user) {
            DB::table('usuario_rol')->insertOrIgnore(['id_usuario' => $user->id_usuario, 'id_rol' => $role]);
        }
        DB::table('usuario_rol')->insertOrIgnore(['id_usuario' => $admin->id_usuario, 'id_rol' => $roleAdmin]);
        $items = $this->articulos();
        foreach ($items as $index => [$title, $category, $condition, $price, $location, $image, $hours, $description, $coords]) {
            $owner = $index === 0 ? $demo : $seller;
            $auction = $owner->subastas()->firstOrCreate(['titulo' => $title], ['descripcion' => $description, 'id_categoria' => Categoria::where('nombre_categoria', $category)->value('id_categoria'), 'estado_articulo' => $condition, 'monto_inicial' => $price, 'ubicacion' => $location, 'latitud' => $coords[0] ?? null, 'longitud' => $coords[1] ?? null, 'fecha_inicio' => now()->subDays($index % 4)->subMinutes(15), 'fecha_fin' => now()->addHours($hours)]);
            // Completa el mapa de demostraciones anteriores; las que ya tienen pujas no admiten cambios.
            if ($coords && is_null($auction->latitud) && ! $auction->pujas()->exists()) {
                $auction->update(['latitud' => $coords[0], 'longitud' => $coords[1]]);
            }
            if (! $auction->imagenes()->exists()) {
                $contents = file_get_contents(public_path('imagenes/ejemplos/'.$image));
                $path = 'subastas/'.$auction->id_subasta.'/'.$image;
                Storage::disk('public')->put($path, $contents);
                $auction->imagenes()->create(['ruta' => $path, 'tamano' => strlen($contents)]);
            }
        }
    }

    // Coordenadas aproximadas de cada plaza; los libros quedan sin mapa como una publicación anterior.
    private function articulos(): array
    {
        return [
            ['Laptop para estudio y trabajo', 'Electrónica e informática', 'Usado', 2400, 'La Paz, Plaza Abaroa', 'laptop.png', 96, 'Publicación de ejemplo de una laptop para estudio y trabajo. Consulta sus características y acuerda la revisión al momento de la entrega.', [-16.510400, -68.126500]],
            ['Cámara fotográfica digital', 'Electrónica e informática', 'Usado', 1200, 'Cochabamba, Plaza Colón', 'camara.jpg', 48, 'Cámara de fotografía de demostración. Revisa la imagen del artículo y coordina la entrega con el vendedor.', [-17.389300, -66.153200]],
            ['Bicicleta para paseos y deporte', 'Deporte', 'Usado', 800, 'Santa Cruz, Plaza 24 de Septiembre', 'bicicleta.png', 72, 'Bicicleta de ejemplo para paseos y actividades deportivas. El lugar de encuentro se coordina en la plaza indicada.', [-17.783400, -63.182100]],
            ['Camiseta de fútbol firmada', 'Antigüedades y coleccionables', 'Usado', 350, 'La Paz, Plaza España', 'camiseta-firmada.jpg', 120, 'Artículo de colección de demostración. La imagen ilustra una camiseta firmada; esta publicación no acredita autenticidad de las firmas.', [-16.512700, -68.124000]],
            ['Consola de videojuegos', 'Videojuegos', 'Nuevo', 1900, 'Sucre, Plaza 25 de Mayo', 'consola.jpg', 144, 'Consola de videojuegos de demostración. Consulta qué accesorios se incluyen antes de participar.', [-19.047600, -65.259600]],
            ['Guitarra firmada de colección', 'Instrumentos musicales', 'Usado', 650, 'Cochabamba, Plazuela de las Banderas', 'guitarra-firmada.jpg', 168, 'Guitarra de demostración para la categoría de instrumentos musicales. La publicación de ejemplo no acredita autenticidad de las firmas.', [-17.381300, -66.159700]],
            ['Colección de libros', 'Libros, películas y música', 'Usado', 150, 'El Alto, Plaza del Obelisco', 'libros.png', 192, 'Conjunto de libros de demostración. Las fotografías permiten conocer los ejemplares antes de coordinar la entrega.', null],
        ];
    }
}
