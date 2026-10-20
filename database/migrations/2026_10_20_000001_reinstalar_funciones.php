<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // Aplica a bases existentes: perfil conserva campos ausentes y el cierre avisa a todos los participantes.
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/Funciones.sql')));
    }

    public function down(): void
    {
        // Funciones.sql siempre refleja la versión vigente; no hay una versión anterior que restaurar.
    }
};
