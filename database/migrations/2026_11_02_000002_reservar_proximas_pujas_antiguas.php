<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('alter table subasta add column activar_fondos_al_pujar boolean not null default false');
        DB::statement("update subasta set activar_fondos_al_pujar=true where not requiere_fondos and estado_subasta='Activa' and fecha_fin>clock_timestamp()");
        foreach (['Funciones.sql', 'PublicacionSubastas.sql'] as $archivo) {
            DB::unprepared(file_get_contents(database_path('sql/'.$archivo)));
        }
    }

    public function down(): void
    {
        // Conservar reservas y movimientos ya registrados.
    }
};
