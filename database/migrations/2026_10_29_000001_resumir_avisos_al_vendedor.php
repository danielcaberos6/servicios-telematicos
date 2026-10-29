<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Mantener el mismo orden que subastas:instalar-funciones, sin borrar datos.
        foreach (['Funciones.sql', 'BusquedaGeografica.sql', 'PublicacionSubastas.sql'] as $archivo) {
            DB::unprepared(file_get_contents(database_path('sql/'.$archivo)));
        }
    }

    public function down(): void
    {
        // Conservar los avisos existentes y las reglas de publicación vigentes.
    }
};
