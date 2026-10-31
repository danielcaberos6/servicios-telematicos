<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table usuario add column ciudad_publica boolean not null default false;
            alter table resenas add column id_operacion bigint references operacion_subasta(id_operacion);
            alter table resenas add column rol_resenado text check(rol_resenado in ('Comprador','Vendedor'));
            alter table resenas add column comentario_oculto boolean not null default false;
            alter table resenas add constraint resena_verificada_valida check(
                (id_operacion is null and rol_resenado is null) or
                (id_operacion is not null and rol_resenado is not null and calificacion=trunc(calificacion) and length(coalesce(comentario,''))<=1000));
            create unique index resena_autor_operacion_unica on resenas(id_operacion,id_usuario_resenador) where id_operacion is not null;
            create table reporte_resena (
                id_reporte bigint generated always as identity primary key,
                id_resena bigint not null references resenas(id_resena),
                id_reportante bigint not null references usuario(id_usuario),
                categoria text not null check(categoria in ('Insultos','Acoso','Datos personales')),
                detalle text not null check(length(trim(detalle)) between 5 and 1000),
                fecha_creacion timestamptz not null default clock_timestamp(),
                accion text check(accion in ('Ocultar comentario','Sin acción')),
                motivo_resolucion text,
                id_administrador bigint references usuario(id_usuario),
                fecha_resolucion timestamptz,
                unique(id_resena,id_reportante),
                check((accion is null and motivo_resolucion is null and id_administrador is null and fecha_resolucion is null)
                   or (accion is not null and length(trim(motivo_resolucion)) between 5 and 2000 and id_administrador is not null and fecha_resolucion is not null))
            );
            create table moderacion_resena (
                id_moderacion bigint generated always as identity primary key,
                id_reporte bigint not null unique references reporte_resena(id_reporte),
                id_administrador bigint not null references usuario(id_usuario),
                accion text not null,
                motivo text not null,
                fecha_creacion timestamptz not null default clock_timestamp()
            );
            SQL);
        DB::unprepared(file_get_contents(database_path('sql/Normativa.sql')));
        DB::unprepared(file_get_contents(database_path('sql/Reputacion.sql')));
        DB::unprepared(file_get_contents(database_path('sql/Funciones.sql')));
    }

    public function down(): void
    {
        // Las reseñas y su trazabilidad deben conservarse.
    }
};
