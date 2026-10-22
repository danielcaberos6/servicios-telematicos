<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/GestionUsuarios.sql')));
    }

    public function down(): void
    {
        DB::unprepared('drop function if exists listar_usuarios_gestion(bigint, jsonb)');
    }
};
