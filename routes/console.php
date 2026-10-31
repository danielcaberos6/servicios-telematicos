<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('subastas:cargar-demo', function () {
    if (! app()->environment(['local', 'testing'])) {
        return;
    }

    return $this->call('db:seed', ['--class' => ReportesDemoSeeder::class, '--force' => true]);
})->purpose('Carga cuentas, subastas con fotos, pujas y valoraciones de demostración en local');

Artisan::command('subastas:instalar-funciones', function () {
    DB::transaction(function () {
        DB::unprepared(file_get_contents(database_path('sql/Funciones.sql')));
        DB::unprepared(file_get_contents(database_path('sql/BusquedaGeografica.sql')));
        // Redefine el catálogo, las pujas y la finalización; sin esto se publicarían
        // solicitudes sin revisar y dejaría de aplicarse el filtro por radio.
        DB::unprepared(file_get_contents(database_path('sql/PublicacionSubastas.sql')));
        DB::unprepared(file_get_contents(database_path('sql/ParticipacionSubastas.sql')));
        DB::unprepared(file_get_contents(database_path('sql/Normativa.sql')));
        DB::unprepared(file_get_contents(database_path('sql/ConsultasArticulos.sql')));
        DB::unprepared(file_get_contents(database_path('sql/Fondos.sql')));
    });
    $this->info('Funciones.sql, BusquedaGeografica.sql y PublicacionSubastas.sql instalados. Las tablas y sus datos se conservaron.');
})->purpose('Instala o actualiza las funciones y triggers de PostgreSQL');

Artisan::command('subastas:finalizar', function () {
    $result = DB::selectOne('select finalizar_subastas() as total');
    $this->info("Subastas finalizadas: {$result->total}");
})->purpose('Finaliza subastas vencidas y notifica a sus participantes');
Schedule::command('subastas:finalizar')->everyMinute()->withoutOverlapping();

Artisan::command('subastas:recordar-favoritos', function () {
    $resultado = DB::selectOne('select recordar_cierres_favoritos() as total');
    $this->info("Recordatorios generados: {$resultado->total}");
})->purpose('Recuerda una sola vez el cierre próximo de los favoritos');
Schedule::command('subastas:recordar-favoritos')->everyMinute()->withoutOverlapping();

Artisan::command('subastas:crear-bd-pruebas', function () {
    // Debe coincidir con DB_DATABASE de phpunit.xml.
    $nombre = 'subastaya_test';
    if (DB::selectOne('select 1 as existe from pg_database where datname = ?', [$nombre])) {
        $this->info("La base {$nombre} ya existe.");

        return;
    }
    DB::statement('create database '.DB::getQueryGrammar()->wrap($nombre));
    $this->info("Base {$nombre} creada.");
})->purpose('Crea la base de datos que usa la suite de pruebas');

use Database\Seeders\ReportesDemoSeeder;
use Illuminate\Foundation\Inspiring;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
