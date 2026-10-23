<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('subastas:instalar-funciones', function () {
    DB::transaction(function () {
        DB::unprepared(file_get_contents(database_path('sql/Funciones.sql')));
        // Redefine catalogo_subastas; sin esto, el filtro por radio dejaría de aplicarse.
        DB::unprepared(file_get_contents(database_path('sql/BusquedaGeografica.sql')));
    });
    $this->info('Funciones.sql y BusquedaGeografica.sql instalados. Las tablas y sus datos se conservaron.');
})->purpose('Instala o actualiza las funciones y triggers de PostgreSQL');

Artisan::command('subastas:finalizar', function () {
    $result = DB::selectOne('select finalizar_subastas() as total');
    $this->info("Subastas finalizadas: {$result->total}");
})->purpose('Finaliza subastas vencidas y notifica a sus participantes');
Schedule::command('subastas:finalizar')->everyMinute()->withoutOverlapping();

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

use Illuminate\Foundation\Inspiring;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
