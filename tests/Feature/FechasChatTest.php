<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;

class FechasChatTest extends TestCase
{
    public function test_server_message_dates_use_bolivian_calendar_days_and_keep_the_time(): void
    {
        Carbon::setTestNow('2026-11-03T03:30:00Z');
        try {
            foreach ([
                '2026-11-03T00:44:00Z' => 'Hoy 20:44',
                '2026-11-02T03:30:00Z' => 'Ayer 23:30',
                '2026-10-31T16:00:00Z' => '31/10/26 12:00',
            ] as $fecha => $texto) {
                $this->view('operaciones.mensaje', ['m' => (object) [
                    'id_mensaje' => 1, 'id_autor' => 2, 'autor' => 'Persona de prueba',
                    'tipo' => 'Texto', 'texto' => 'Mensaje', 'fecha_creacion' => $fecha,
                ]])->assertSee($texto)->assertSee('data-chat-fecha', false);
            }
        } finally {
            Carbon::setTestNow();
        }
    }
}
