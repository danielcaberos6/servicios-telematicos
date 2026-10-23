<?php

namespace Tests\Feature;

use App\Models\Subasta;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdiomaEspanolTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_auction_deadlines_are_in_spanish(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
        $this->assertSame('es', app()->getLocale());
        $auction = Subasta::where('estado_revision', 'Aceptada')->firstOrFail();

        foreach (['/', '/buscar', '/subastas/'.$auction->id_subasta] as $url) {
            $response = $this->get($url)->assertOk()->assertDontSee('from now')->assertDontSee(' ago');
            $this->assertMatchesRegularExpression('/Cierra (?:en|dentro de) \d+ días/u', $response->getContent());
        }
    }

    public function test_profile_months_and_notification_dates_are_in_spanish(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
        $user = User::where('correo', 'demo@subastaya.test')->sole();
        $user->forceFill(['fecha_registro' => '2026-01-15 12:00:00'])->save();
        $user->notificaciones()->update(['fecha_notificacion' => now()->subHours(2)]);

        $this->actingAs($user)->get('/perfil')->assertOk()->assertSee('Miembro desde enero 2026')
            ->assertDontSee('January');
        $this->get('/notificaciones')->assertOk()->assertSee('hace 2 horas')->assertDontSee('hours ago');
    }
}
