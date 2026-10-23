<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Reinstalar las reglas sin reemplazar las cuentas ni sus decisiones.
        DB::unprepared(file_get_contents(database_path('sql/RolesUsuarios.sql')));
    }

    public function down(): void
    {
        // Conservar la protección de la cuenta administrativa y su historial.
    }
};
