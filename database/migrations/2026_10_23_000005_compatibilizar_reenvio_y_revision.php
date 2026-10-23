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
        // Conservar las reglas de revisión y los datos existentes.
    }
};
