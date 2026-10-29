<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subasta', function (Blueprint $table) {
            $table->smallInteger('incremento_puja')->default(5);
            $table->smallInteger('modelo_puja')->default(1);
            $table->boolean('condiciones_fijadas')->default(false);
        });
        DB::statement('alter table subasta alter column modelo_puja set default 2');
        DB::statement('alter table subasta add constraint subasta_modelo_puja_valido check (modelo_puja in (1,2))');
        DB::statement('alter table subasta add constraint subasta_monto_modelo_valido check (modelo_puja=1 or mod(monto_inicial,5)=0)');
        DB::statement('alter table subasta add constraint subasta_incremento_puja_valido check (incremento_puja in (5,10,20,50,100))');
        foreach (['Funciones.sql', 'BusquedaGeografica.sql', 'PublicacionSubastas.sql'] as $archivo) {
            DB::unprepared(file_get_contents(database_path('sql/'.$archivo)));
        }
    }

    public function down(): void
    {
        // Las funciones vigentes dependen del incremento. Conservarlo también preserva las condiciones históricas.
    }
};
