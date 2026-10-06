<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('subastas:finalizar', function () {
    $result = DB::selectOne('select finalizar_subastas() as total');
    $this->info("Subastas finalizadas: {$result->total}");
})->purpose('Finaliza subastas vencidas y notifica a sus participantes');
Schedule::command('subastas:finalizar')->everyMinute()->withoutOverlapping();

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
