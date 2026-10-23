<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavegacionVisitanteTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_navigation_contains_only_public_sections(): void
    {
        $this->get('/')->assertOk()->assertSee('Cómo funciona')->assertSee(route('subastas.index'))
            ->assertDontSee('Mis subastas')->assertDontSee('Notificaciones')
            ->assertDontSee('href="'.route('perfil.edit').'"', false);
        $this->get('/como-funciona')->assertOk()->assertSee('Preguntas frecuentes')
            ->assertSee('Participa con cuidado')->assertSee('Crear una cuenta')
            ->assertSee('aria-current="page"', false);
        $this->get('/mis-subastas')->assertRedirect('/iniciar-sesion');
        $this->get('/notificaciones')->assertRedirect('/iniciar-sesion');
        $this->get('/perfil')->assertRedirect('/iniciar-sesion');
    }

    public function test_signed_in_users_keep_their_personal_navigation(): void
    {
        $this->actingAs(User::factory()->create())->get('/')->assertOk()
            ->assertSee('Mis subastas')->assertSee('Notificaciones')->assertSee('Mi perfil:');
        $this->get('/como-funciona')->assertOk()->assertDontSee('Crear una cuenta');
    }

    public function test_guest_returns_to_the_same_auction_after_signing_in_to_bid(): void
    {
        $seller = User::factory()->create();
        $auction = $seller->subastas()->forceCreate([
            'titulo' => 'Artículo de ejemplo', 'descripcion' => 'Artículo con descripción completa para una prueba.',
            'id_categoria' => Categoria::first()->id_categoria, 'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz', 'monto_inicial' => 100, 'fecha_inicio' => now()->subHour(),
            'fecha_fin' => now()->addDays(2), 'estado_revision' => 'Aceptada',
        ]);
        $participar = route('subastas.participar', $auction);
        $this->get(route('subastas.show', $auction))->assertOk()->assertSee($participar);
        $this->get($participar)->assertRedirect('/iniciar-sesion')->assertSessionHas('url.intended', $participar);
        $bidder = User::factory()->create();
        $this->post('/iniciar-sesion', ['correo' => $bidder->correo, 'password' => 'password'])->assertRedirect($participar);
        $this->get($participar)->assertRedirect(route('subastas.show', $auction));
        $this->get(route('subastas.show', $auction))->assertOk()->assertSee('Hacer oferta');
        $this->assertDatabaseCount('puja', 0);
    }
}
