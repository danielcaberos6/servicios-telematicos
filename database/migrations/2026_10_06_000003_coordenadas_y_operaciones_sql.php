<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            ALTER TABLE subasta ADD COLUMN IF NOT EXISTS latitud numeric(9,6);
            ALTER TABLE subasta ADD COLUMN IF NOT EXISTS longitud numeric(10,6);
            ALTER TABLE subasta DROP CONSTRAINT IF EXISTS subasta_coordenadas_validas;
            ALTER TABLE subasta ADD CONSTRAINT subasta_coordenadas_validas CHECK (
                (latitud IS NULL AND longitud IS NULL) OR
                (latitud IS NOT NULL AND longitud IS NOT NULL AND latitud BETWEEN -90 AND 90 AND longitud BETWEEN -180 AND 180)
            );
            ALTER TABLE notificacion ADD COLUMN IF NOT EXISTS id_subasta bigint REFERENCES subasta(id_subasta) ON DELETE SET NULL;
            SQL);
        DB::unprepared(file_get_contents(base_path('Funciones.sql')));
    }

    public function down(): void
    {
        throw new RuntimeException('Esta ampliación conserva datos y funciones relacionadas. Para revertirla, crea una migración compensatoria.');
    }
};
