<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            alter table mensaje_operacion
                add column tipo text not null default 'Texto',
                add column archivo_ruta text,
                add column archivo_mime text,
                add column archivo_bytes bigint,
                add column archivo_sha256 text;
            alter table mensaje_operacion drop constraint mensaje_operacion_texto_check;
            alter table mensaje_operacion add constraint mensaje_contenido_check check (
                length(trim(texto))<=2000 and (
                    (tipo='Texto' and length(trim(texto))>=1 and archivo_ruta is null and archivo_mime is null and archivo_bytes is null and archivo_sha256 is null)
                    or (tipo in ('Foto','Audio') and archivo_ruta is not null and archivo_mime is not null
                        and archivo_bytes is not null and archivo_sha256 is not null
                        and archivo_bytes between 1 and 5242880 and archivo_sha256 ~ '^[a-f0-9]{64}$')
                )
            );
            SQL);
        DB::unprepared(file_get_contents(database_path('sql/Operaciones.sql')));
    }

    public function down(): void
    {
        // Conservar mensajes y adjuntos privados del historial.
    }
};
