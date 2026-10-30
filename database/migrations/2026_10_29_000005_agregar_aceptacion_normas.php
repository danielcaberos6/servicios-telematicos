<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table aceptacion_normas (
                id_usuario bigint not null references usuario(id_usuario),
                version text not null,
                fecha_aceptacion timestamptz not null default clock_timestamp(),
                primary key(id_usuario,version)
            );
            SQL);
        foreach (['Normativa.sql', 'Funciones.sql', 'BusquedaGeografica.sql', 'PublicacionSubastas.sql'] as $archivo) {
            DB::unprepared(file_get_contents(database_path('sql/'.$archivo)));
        }
    }

    public function down(): void
    {
        // Conservar el historial de aceptación y las funciones que lo exigen.
    }
};
