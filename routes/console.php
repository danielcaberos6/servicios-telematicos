<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('subastas:instalar-funciones', function () {
    DB::transaction(fn () => DB::unprepared(file_get_contents(base_path('Funciones.sql'))));
    $this->info('Funciones.sql instalado. Las tablas y sus datos se conservaron.');
})->purpose('Instala o actualiza las funciones y triggers de PostgreSQL');

Artisan::command('subastas:finalizar', function () {
    $result = DB::selectOne('select finalizar_subastas() as total');
    $this->info("Subastas finalizadas: {$result->total}");
})->purpose('Finaliza subastas vencidas y notifica a sus participantes');
Schedule::command('subastas:finalizar')->everyMinute()->withoutOverlapping();

use Illuminate\Foundation\Inspiring;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
