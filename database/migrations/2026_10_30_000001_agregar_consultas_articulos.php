<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table consulta_articulo (
                id_consulta bigint generated always as identity primary key,
                id_subasta bigint not null references subasta(id_subasta) on delete cascade,
                id_usuario bigint not null references usuario(id_usuario),
                clave_envio uuid not null,
                pregunta text not null check (char_length(btrim(pregunta)) between 1 and 1000),
                fecha_pregunta timestamptz not null default clock_timestamp(),
                respuesta text check (char_length(btrim(respuesta)) between 1 and 2000),
                id_responde bigint references usuario(id_usuario),
                fecha_respuesta timestamptz,
                pregunta_oculta boolean not null default false,
                respuesta_oculta boolean not null default false,
                unique(id_usuario,clave_envio),
                check ((respuesta is null and id_responde is null and fecha_respuesta is null)
                    or (respuesta is not null and id_responde is not null and fecha_respuesta is not null))
            );
            create index consulta_articulo_publicacion_idx on consulta_articulo(id_subasta,fecha_pregunta,id_consulta);
            create table reporte_consulta (
                id_reporte bigint generated always as identity primary key,
                id_consulta bigint not null references consulta_articulo(id_consulta) on delete cascade,
                id_usuario bigint not null references usuario(id_usuario),
                tipo text not null check (tipo in ('pregunta','respuesta')),
                motivo text not null check (char_length(btrim(motivo)) between 1 and 500),
                fecha_reporte timestamptz not null default clock_timestamp(),
                accion text check (accion in ('ocultar','desestimar')),
                motivo_resolucion text,
                id_moderador bigint references usuario(id_usuario),
                fecha_resolucion timestamptz,
                unique(id_consulta,id_usuario,tipo),
                check ((accion is null and motivo_resolucion is null and id_moderador is null and fecha_resolucion is null)
                    or (accion is not null and char_length(btrim(motivo_resolucion)) between 1 and 500 and id_moderador is not null and fecha_resolucion is not null))
            );
            create index reporte_consulta_pendientes_idx on reporte_consulta(fecha_reporte,id_reporte) where accion is null;
            SQL);
        DB::unprepared(file_get_contents(database_path('sql/ConsultasArticulos.sql')));
    }

    public function down(): void
    {
        // Conservar preguntas, respuestas y decisiones de moderación.
    }
};
