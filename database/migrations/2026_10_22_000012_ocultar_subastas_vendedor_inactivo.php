<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/PublicacionSubastas.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('El catálogo y las pujas dependen de estas funciones. Usa una migración compensatoria.');
    }
};
