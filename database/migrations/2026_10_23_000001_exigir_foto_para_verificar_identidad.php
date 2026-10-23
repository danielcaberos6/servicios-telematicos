<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/FotoIdentidad.sql')));
    }

    public function down(): void
    {
        DB::unprepared('drop trigger if exists verificacion_exigir_foto on verificacion_identidad; drop function if exists identidad_exigir_foto()');
    }
};
