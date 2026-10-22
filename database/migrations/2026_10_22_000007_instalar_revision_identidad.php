<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/RevisionIdentidad.sql')));
    }

    public function down(): void
    {
        DB::unprepared('drop function if exists resolver_verificacion(bigint,bigint,text,text)');
    }
};
