<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(file_get_contents(database_path('sql/HistorialRevisionSubastas.sql')));
        DB::unprepared(file_get_contents(database_path('sql/PublicacionSubastas.sql')));
    }

    public function down(): void
    {
        DB::unprepared('drop trigger subasta_historial_decision on subasta;
            drop trigger subasta_notificar_reenvio on subasta;
            drop function registrar_decision_subasta();
            drop function notificar_reenvio_subasta();
            drop table decision_subasta');
    }
};
