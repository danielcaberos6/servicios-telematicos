<?php

namespace Database\Seeders;

use App\Models\Categoria;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (['Tecnología', 'Hogar', 'Deportes', 'Moda y accesorios', 'Coleccionables', 'Otros'] as $name) {
            Categoria::firstOrCreate(['nombre_categoria' => $name]);
        }
        foreach (['Usuario', 'Administrador', 'Moderador'] as $role) {
            DB::table('rol')->insertOrIgnore(['nombre_rol' => $role]);
        }
    }
}
