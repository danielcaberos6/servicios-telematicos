<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table decision_subasta drop constraint decision_subasta_estado_check;
            alter table decision_subasta add constraint decision_subasta_estado_check
                check (estado in ('Aceptada', 'Rechazada', 'Cancelada'));
            SQL);

        foreach (['Funciones.sql', 'PublicacionSubastas.sql', 'RevisionSubastasAdmin.sql'] as $archivo) {
            DB::unprepared(file_get_contents(database_path('sql/'.$archivo)));
        }
    }

    public function down(): void
    {
        // El historial de cancelaciones se conserva.
    }
};
