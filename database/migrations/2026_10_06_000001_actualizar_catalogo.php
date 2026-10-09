<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Bloqueo exclusivo: la conversión de categorías no compite con publicaciones o pujas.
        DB::statement('LOCK TABLE subasta IN ACCESS EXCLUSIVE MODE');
        DB::unprepared('DROP TRIGGER IF EXISTS subasta_proteccion ON subasta');
        $seed = explode('-- BEGIN SEMILLAS PREDETERMINADAS', file_get_contents(database_path('sql/Script.sql')))[1];
        DB::unprepared(explode('-- END SEMILLAS PREDETERMINADAS', $seed)[0]);
        foreach (['Tecnología' => 'Electrónica e informática', 'Hogar' => 'Muebles', 'Deportes' => 'Deporte', 'Moda y accesorios' => 'Ropa, calzado y accesorios', 'Coleccionables' => 'Antigüedades y coleccionables', 'Otros' => 'Varios'] as $old => $new) {
            $oldId = DB::table('categoria')->where('nombre_categoria', $old)->value('id_categoria');
            if ($oldId) {
                $newId = DB::table('categoria')->where('nombre_categoria', $new)->value('id_categoria');
                DB::table('subasta')->where('id_categoria', $oldId)->update(['id_categoria' => $newId]);
                DB::table('categoria')->where('id_categoria', $oldId)->delete();
            }
        }
        DB::statement("UPDATE subasta SET estado_articulo = 'Usado' WHERE estado_articulo = 'Como nuevo'");
        DB::statement('ALTER TABLE subasta DROP CONSTRAINT IF EXISTS articulo_estado_valido');
        DB::statement("ALTER TABLE subasta ADD CONSTRAINT articulo_estado_valido CHECK (estado_articulo IN ('Nuevo', 'Usado'))");
        if (DB::selectOne("SELECT to_regprocedure('proteger_subasta()') AS funcion")->funcion) {
            DB::statement('CREATE TRIGGER subasta_proteccion BEFORE UPDATE OR DELETE ON subasta FOR EACH ROW EXECUTE FUNCTION proteger_subasta()');
        }
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE subasta DROP CONSTRAINT IF EXISTS articulo_estado_valido');
        DB::statement("ALTER TABLE subasta ADD CONSTRAINT articulo_estado_valido CHECK (estado_articulo IN ('Nuevo', 'Como nuevo', 'Usado'))");
        // No se revierte la reclasificación: se conservan los artículos y sus relaciones.
    }
};
