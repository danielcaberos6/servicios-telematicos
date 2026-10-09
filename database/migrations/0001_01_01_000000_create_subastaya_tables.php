<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            throw new RuntimeException('SubastaYA requiere PostgreSQL. Revisa DB_CONNECTION en .env.');
        }
        DB::unprepared(file_get_contents(database_path('sql/Script.sql')));
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        foreach (['notificacion', 'resenas', 'imagen', 'puja', 'subasta', 'usuario_rol', 'usuario', 'rol', 'categoria'] as $table) {
            Schema::dropIfExists($table);
        }
        foreach (['validar_puja', 'proteger_subasta', 'notificar_publicacion', 'notificar_puja', 'finalizar_subastas'] as $function) {
            DB::statement("drop function if exists {$function}()");
        }
    }
};
