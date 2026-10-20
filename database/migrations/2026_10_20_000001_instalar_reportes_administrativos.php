<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('CREATE INDEX IF NOT EXISTS usuario_registro_idx ON usuario(fecha_registro);
            CREATE INDEX IF NOT EXISTS subasta_publicacion_idx ON subasta(fecha_inicio);
            CREATE INDEX IF NOT EXISTS puja_usuario_fecha_idx ON puja(id_usuario,fecha_puja);
            CREATE INDEX IF NOT EXISTS resenas_usuario_fecha_idx ON resenas(id_usuario_resenado,fecha_creacion);');
        DB::unprepared(file_get_contents(database_path('sql/Funciones.sql')));
    }

    public function down(): void
    {
        DB::unprepared('DROP FUNCTION IF EXISTS reporte_subastas(bigint,jsonb), reporte_usuarios(bigint,jsonb), ciudades_reporte(bigint);
            DROP FUNCTION IF EXISTS validar_periodo_reportes(jsonb), exigir_administrador(bigint);
            DROP INDEX IF EXISTS usuario_registro_idx, subasta_publicacion_idx, puja_usuario_fecha_idx, resenas_usuario_fecha_idx;');
    }
};
