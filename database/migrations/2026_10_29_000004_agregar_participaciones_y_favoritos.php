<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            create table favorito_subasta (
                id_usuario bigint not null references usuario(id_usuario) on delete cascade,
                id_subasta bigint not null references subasta(id_subasta) on delete cascade,
                fecha_guardado timestamptz not null default clock_timestamp(),
                primary key(id_usuario,id_subasta)
            );
            create index favorito_subasta_cierre_idx on favorito_subasta(id_subasta,fecha_guardado);
            create table recordatorio_favorito (
                id_usuario bigint not null references usuario(id_usuario) on delete cascade,
                id_subasta bigint not null references subasta(id_subasta) on delete cascade,
                fecha_recordatorio timestamptz not null default clock_timestamp(),
                primary key(id_usuario,id_subasta)
            );
            SQL);
        DB::unprepared(file_get_contents(database_path('sql/ParticipacionSubastas.sql')));
    }

    public function down(): void
    {
        // Conservar favoritos y recordatorios ya registrados.
    }
};
