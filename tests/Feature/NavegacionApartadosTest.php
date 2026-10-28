<?php

namespace Tests\Feature;

use App\Models\Subasta;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NavegacionApartadosTest extends TestCase
{
    use RefreshDatabase;

    private function ejemplos(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
    }

    public function test_public_pages_keep_their_layout_without_section_navigation(): void
    {
        $this->ejemplos();
        $subasta = Subasta::where('estado_revision', 'Aceptada')->firstOrFail();
        foreach (['/', '/como-funciona', '/buscar', '/subastas/'.$subasta->id_subasta] as $url) {
            $this->get($url)->assertOk()->assertDontSee('data-espacio-seccion', false);
        }
    }

    public function test_profile_and_publication_sections_respect_the_user_role(): void
    {
        $this->ejemplos();
        $this->actingAs(User::where('correo', 'demo@subastaya.test')->sole())->get('/perfil')
            ->assertOk()->assertSee('href="#seguridad"', false)->assertSee('href="#identidad"', false)
            ->assertSee('id="resenas" data-apartado', false);
        $this->get('/subastas/crear')->assertOk()->assertSee('href="#encuentro"', false)
            ->assertSee('id="encuentro" data-apartado', false);

        // Otra cuenta, otra sesión: la anterior guarda el hash de contraseña del vendedor.
        $this->flushSession();
        $this->actingAs(User::where('correo', 'admin@subastaya.test')->sole())->get('/perfil')
            ->assertOk()->assertSee('href="#seguridad"', false)->assertDontSee('href="#identidad"', false);
        foreach (['/admin/usuarios', '/admin/categorias', '/admin/identidad', '/admin/subastas', '/reportes/subastas'] as $url) {
            $this->get($url)->assertOk()->assertSee('data-espacio-seccion', false);
        }
    }
}
