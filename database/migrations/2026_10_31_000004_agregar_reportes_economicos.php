<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('create index if not exists subasta_cierre_fondos_idx on subasta(fecha_fin,id_subasta) where requiere_fondos');
        DB::statement('create index if not exists movimiento_fecha_idx on movimiento_fondos(fecha_movimiento desc,id_movimiento desc)');
        DB::unprepared(file_get_contents(database_path('sql/ReportesEconomicos.sql')));
    }

    public function down(): void
    {
        // El reporte es de consulta y no modifica movimientos ni operaciones.
    }
};
