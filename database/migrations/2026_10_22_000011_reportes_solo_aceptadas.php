<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Funciones.sql redefine funciones que PublicacionSubastas.sql reemplaza: se instalan en el mismo orden que subastas:instalar-funciones.
        DB::unprepared(file_get_contents(database_path('sql/Funciones.sql')));
        DB::unprepared(file_get_contents(database_path('sql/BusquedaGeografica.sql')));
        DB::unprepared(file_get_contents(database_path('sql/PublicacionSubastas.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('Los reportes dependen de estas funciones. Usa una migración compensatoria.');
    }
};
