<?php

namespace Database\Seeders;

use App\Models\Categoria;
use App\Models\User;
use Database\Seeders\Concerns\CargaFotosDemo;
use Illuminate\Database\Seeder;

class DemoSeeder extends Seeder
{
    use CargaFotosDemo;

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {

            throw new \RuntimeException('Los datos de demostración solo se cargan en entornos locales o de pruebas.');
        }
        $this->call(DatabaseSeeder::class);
        $this->call(CuentasDemoSeeder::class);
        $demo = User::where('correo', 'demo@subastaya.test')->firstOrFail();
        User::whereKey($demo->id_usuario)->where('nombre', 'Rodrigo Demo')->update(['nombre' => 'Rodrigo Vargas']);
        $seller = User::where('correo', 'vendedor@subastaya.test')->firstOrFail();
        $items = $this->articulos();
        foreach ($items as $index => [$title, $category, $condition, $price, $location, $image, $hours, $description, $coords]) {
            $owner = $index === 0 ? $demo : $seller;
            // Las demostraciones se insertan ya aceptadas: representan publicaciones revisadas.
            $auction = $owner->subastas()->firstOrNew(['titulo' => $title]);
            if (! $auction->exists) {
                $auction->forceFill(['modelo_puja' => 1, 'descripcion' => $description, 'id_categoria' => Categoria::where('nombre_categoria', $category)->value('id_categoria'), 'estado_articulo' => $condition, 'monto_inicial' => $price, 'ubicacion' => $location, 'latitud' => $coords[0] ?? null, 'longitud' => $coords[1] ?? null, 'fecha_inicio' => now()->subDays($index % 4)->subMinutes(15), 'fecha_fin' => now()->addHours($hours), 'estado_revision' => 'Aceptada'])->save();
            }
            // Completa el mapa de demostraciones anteriores; las que ya tienen pujas no admiten cambios.
            if ($coords && is_null($auction->latitud) && ! $auction->pujas()->exists()) {
                $auction->update(['latitud' => $coords[0], 'longitud' => $coords[1]]);
            }
            $this->asegurarFoto($auction, $image);
        }
        $this->solicitudes($demo);
    }

    // Solicitudes del usuario demo para ver En revisión y Rechazada en Mis subastas.
    // Las pendientes avisan al vendedor y a los administradores al insertarse.
    private function solicitudes(User $demo): void
    {
        $items = [
            ['Escritorio de madera con cajones', 'Muebles', 'Usado', 520, 'La Paz, Plaza Abaroa', 'En revisión', null,
                'Escritorio de demostración pendiente de revisión. Todavía no aparece en el catálogo.'],
            ['Reloj de pulsera de colección', 'Ropa, calzado y accesorios', 'Usado', 900, 'La Paz, Plaza España', 'Rechazada',
                'La descripción no indica la marca ni el modelo. Completa esos datos y agrega fotos propias del reloj.',
                'Reloj de demostración rechazado para mostrar el motivo que ve el vendedor.'],
        ];
        foreach ($items as [$title, $category, $condition, $price, $location, $revision, $reason, $description]) {
            $auction = $demo->subastas()->firstOrNew(['titulo' => $title]);
            if (! $auction->exists) {
                $auction->forceFill(['modelo_puja' => 1, 'descripcion' => $description, 'id_categoria' => Categoria::where('nombre_categoria', $category)->value('id_categoria'), 'estado_articulo' => $condition, 'monto_inicial' => $price, 'ubicacion' => $location, 'fecha_inicio' => now()->subHours(3), 'fecha_fin' => now()->addDays(6), 'estado_revision' => $revision, 'motivo_rechazo' => $reason])->save();
            }
            $this->asegurarFoto($auction, $category === 'Muebles' ? 'escritorio.jpg' : 'reloj.jpg');
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
