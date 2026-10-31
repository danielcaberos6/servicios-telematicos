<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table movimiento_fondos drop constraint movimiento_fondos_tipo_check;
            alter table movimiento_fondos add constraint movimiento_fondos_tipo_check check(tipo in ('Recarga','Reserva','Liberación','Devolución','Liquidación','Pago'));
            alter table reserva_fondos drop constraint reserva_fondos_estado_check;
            alter table reserva_fondos add constraint reserva_fondos_estado_check check(estado in ('Activa','Retenida','Liberada','Consumida'));
            create table operacion_subasta (
                id_operacion bigint generated always as identity primary key,
                id_subasta bigint not null unique references subasta(id_subasta),
                id_comprador bigint not null references usuario(id_usuario),
                id_vendedor bigint not null references usuario(id_usuario),
                importe numeric(14,2) not null check(importe>0),
                comision numeric(14,2) not null check(comision>=0),
                neto numeric(14,2) not null check(neto>=0),
                con_fondos boolean not null,
                estado text not null default 'Pendiente' check(estado in ('Pendiente','En revisión','Completada','Reembolsada')),
                fecha_creacion timestamptz not null default clock_timestamp(),
                fecha_confirmacion_comprador timestamptz,
                fecha_confirmacion_vendedor timestamptz,
                fecha_resolucion timestamptz,
                aviso_72h timestamptz,
                leido_comprador bigint not null default 0,
                leido_vendedor bigint not null default 0,
                check(id_comprador<>id_vendedor),
                check((con_fondos and neto+comision=importe) or (not con_fondos and comision=0 and neto=0))
            );
            create table comision_operacion (
                id_operacion bigint primary key references operacion_subasta(id_operacion),
                importe numeric(14,2) not null check(importe>0),
                fecha_creacion timestamptz not null default clock_timestamp()
            );
            create table mensaje_operacion (
                id_mensaje bigint generated always as identity primary key,
                referencia uuid not null unique,
                id_operacion bigint not null references operacion_subasta(id_operacion),
                id_autor bigint not null references usuario(id_usuario),
                texto text not null check(length(trim(texto)) between 1 and 2000),
                fecha_creacion timestamptz not null default clock_timestamp()
            );
            create index mensajes_operacion_idx on mensaje_operacion(id_operacion,id_mensaje desc);
            create table acuerdo_operacion (
                id_acuerdo bigint generated always as identity primary key,
                id_operacion bigint not null references operacion_subasta(id_operacion),
                id_proponente bigint not null references usuario(id_usuario),
                lugar text not null check(length(trim(lugar)) between 5 and 250),
                fecha_encuentro timestamptz not null,
                estado text not null default 'Propuesto' check(estado in ('Propuesto','Aceptado','Cambio solicitado','Reemplazado')),
                respuesta text,
                fecha_creacion timestamptz not null default clock_timestamp(),
                fecha_respuesta timestamptz
            );
            create unique index acuerdo_propuesto_unico on acuerdo_operacion(id_operacion) where estado='Propuesto';
            create table incidencia_operacion (
                id_incidencia bigint generated always as identity primary key,
                id_operacion bigint not null references operacion_subasta(id_operacion),
                id_reportante bigint references usuario(id_usuario),
                motivo text not null check(length(trim(motivo)) between 5 and 2000),
                estado text not null default 'Abierta' check(estado in ('Abierta','En revisión','Resuelta')),
                id_administrador bigint references usuario(id_usuario),
                resolucion text,
                decision text check(decision in ('Liquidar','Devolver')),
                fecha_creacion timestamptz not null default clock_timestamp(),
                fecha_atencion timestamptz,
                fecha_resolucion timestamptz
            );
            create unique index incidencia_abierta_unica on incidencia_operacion(id_operacion) where estado<>'Resuelta';
            create table evento_operacion (
                id_evento bigint generated always as identity primary key,
                id_operacion bigint not null references operacion_subasta(id_operacion),
                id_actor bigint references usuario(id_usuario),
                accion text not null,
                detalle text not null,
                fecha_creacion timestamptz not null default clock_timestamp()
            );
            create table acceso_conversacion (
                id_acceso bigint generated always as identity primary key,
                id_incidencia bigint not null references incidencia_operacion(id_incidencia),
                id_administrador bigint not null references usuario(id_usuario),
                motivo text not null,
                fecha_acceso timestamptz not null default clock_timestamp()
            );
            SQL);
        DB::unprepared(file_get_contents(database_path('sql/Normativa.sql')));
        DB::unprepared(file_get_contents(database_path('sql/Operaciones.sql')));
        DB::unprepared(file_get_contents(database_path('sql/Fondos.sql')));
        DB::unprepared(file_get_contents(database_path('sql/PublicacionSubastas.sql')));
    }

    public function down(): void
    {
        // Mantener operaciones, mensajes y movimientos ya registrados.
    }
};
