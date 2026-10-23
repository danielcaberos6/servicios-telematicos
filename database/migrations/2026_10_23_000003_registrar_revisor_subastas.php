<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table subasta add column id_revisor bigint references usuario(id_usuario);
            alter table subasta add column fecha_decision timestamptz;
            alter table subasta add constraint subasta_decision_registrada check (
                (id_revisor is null and fecha_decision is null)
                or (id_revisor is not null and fecha_decision is not null and estado_revision <> 'En revisión')
            );
            SQL);
        DB::unprepared(file_get_contents(database_path('sql/RevisionSubastasAdmin.sql')));
    }

    public function down(): void
    {
        $sql = file_get_contents(database_path('sql/RevisionSubastasAdmin.sql'));
        $sql = preg_replace('/^\h*(id_revisor = p_actor|fecha_decision = v_publicacion),\R/m', '', $sql);
        DB::unprepared($sql);
        DB::unprepared('alter table subasta drop constraint subasta_decision_registrada, drop column id_revisor, drop column fecha_decision');
    }
};
