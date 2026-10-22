<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/GestionCategorias.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('Las categorías y su historial se conservan. Usa una migración compensatoria.');
    }
};
