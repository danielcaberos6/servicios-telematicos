<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/RevisionSubastasAdmin.sql')));
    }

    public function down(): void
    {
        // Mantener la corrección para no volver a publicar solicitudes vencidas.
    }
};
