<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuCuentaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_keep_login_and_do_not_have_a_logout_menu(): void
    {
        $this->get('/')->assertOk()->assertSee('Iniciar sesión')->assertDontSee('data-cuenta-menu', false)->assertDontSee('data-cerrar-sesion', false);
    }

    public function test_the_account_disclosure_contains_profile_appearance_and_post_logout(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/perfil')->assertOk()->assertSee('data-cuenta-menu', false)
            ->assertSee('Mi cuenta: '.$user->nombre)->assertSee('Opciones de tu cuenta')->assertSee('Mi perfil')
            ->assertSee('Apariencia')->assertSee('data-cerrar-sesion', false)->assertSee('data-confirmar-salida', false)
            ->assertSee('Seguir editando')->assertDontSee('class="logout"', false);
        $this->post('/cerrar-sesion')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_validation_errors_mark_pending_fields_without_sending_values_to_the_guard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->from('/subastas/crear')->post('/subastas', ['titulo' => 'Artículo pendiente', 'descripcion' => 'Texto'])
            ->assertSessionHasErrors()->assertRedirect('/subastas/crear');
        $this->assertSame('Artículo pendiente', session()->getOldInput('titulo'));
        $html = $this->get('/subastas/crear')->assertOk()->getContent();
        preg_match('/data-campos-pendientes="([^"]*)"/', $html, $match);
        $this->assertSame(['titulo', 'descripcion'], json_decode(html_entity_decode($match[1]), true));
        $this->assertDatabaseCount('subasta', 0);
    }
}
