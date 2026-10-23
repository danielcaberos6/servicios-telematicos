<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/RevisionSubastas.sql')));
        DB::unprepared(file_get_contents(database_path('sql/PublicacionSubastas.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('El estado de revisión de las subastas se conserva. Usa una migración compensatoria.');
    }
};
