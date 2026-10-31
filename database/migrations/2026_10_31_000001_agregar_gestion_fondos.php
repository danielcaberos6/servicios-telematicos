<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            -- Las publicaciones que ya existen no adquieren reservas retroactivas.
            alter table subasta add column requiere_fondos boolean not null default false;
            create table billetera (
                id_usuario bigint primary key references usuario(id_usuario) on delete cascade,
                fecha_creacion timestamptz not null default clock_timestamp()
            );
            insert into billetera(id_usuario) select id_usuario from usuario;
            create table recarga (
                id_recarga uuid primary key,
                id_usuario bigint not null references billetera(id_usuario),
                monto numeric(14,2) not null check (monto between 5 and 10000 and monto=trunc(monto)),
                estado text not null default 'Pendiente' check (estado in ('Pendiente','Aprobada','Rechazada','Cancelada')),
                fecha_creacion timestamptz not null default clock_timestamp(),
                fecha_resolucion timestamptz
            );
            create index recarga_usuario_idx on recarga(id_usuario,fecha_creacion desc);
            create table movimiento_fondos (
                id_movimiento bigint generated always as identity primary key,
                id_usuario bigint not null references billetera(id_usuario),
                tipo text not null check (tipo in ('Recarga','Reserva','Liberación','Devolución','Liquidación')),
                importe numeric(14,2) not null check (importe>0),
                disponible numeric(14,2) not null,
                reservado numeric(14,2) not null,
                referencia text not null,
                id_subasta bigint references subasta(id_subasta) on delete restrict,
                fecha_movimiento timestamptz not null default clock_timestamp(),
                unique(id_usuario,tipo,referencia)
            );
            create index movimiento_usuario_idx on movimiento_fondos(id_usuario,fecha_movimiento desc,id_movimiento desc);
            create table reserva_fondos (
                id_subasta bigint primary key references subasta(id_subasta) on delete restrict,
                id_usuario bigint not null references billetera(id_usuario),
                monto numeric(14,2) not null check (monto>0),
                id_puja bigint not null references puja(id_puja) deferrable initially deferred,
                estado text not null check (estado in ('Activa','Retenida','Liberada')),
                fecha_actualizacion timestamptz not null default clock_timestamp()
            );
            SQL);
        foreach (['Normativa.sql', 'Fondos.sql', 'Funciones.sql', 'BusquedaGeografica.sql', 'PublicacionSubastas.sql'] as $archivo) {
            DB::unprepared(file_get_contents(database_path('sql/'.$archivo)));
        }
    }

    public function down(): void
    {
        // Conservar movimientos, recargas y reservas; no borrar el historial económico.
    }
};
