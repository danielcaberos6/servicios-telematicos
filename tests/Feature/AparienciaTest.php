<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AparienciaTest extends TestCase
{
    use RefreshDatabase;

    private function comprobar(array $rutas): void
    {
        foreach ($rutas as $ruta) {
            $html = $this->get($ruta)->assertOk()->assertSee('data-tema-selector', false)
                ->assertSee('aria-label="Modo oscuro"', false)->assertSee('js/apariencia.js', false)->assertSee('css/apariencia.css', false)->getContent();
            $this->assertLessThan(strpos($html, 'css/app.css'), strpos($html, 'js/apariencia.js'), 'El tema debe inicializarse antes de los estilos.');
            $this->assertStringNotContainsString('apariencia.js" defer', $html);
        }
    }

    public function test_appearance_is_available_on_public_and_authentication_pages(): void
    {
        $this->comprobar(['/', '/buscar', '/como-funciona', '/normas-y-politicas', '/iniciar-sesion', '/registro']);
    }

    public function test_appearance_is_available_on_private_pages_and_publication_forms(): void
    {
        $this->actingAs(User::factory()->create());
        $this->comprobar(['/perfil', '/subastas/crear', '/notificaciones', '/mis-participaciones', '/favoritos']);
    }

    public function test_appearance_is_available_in_administration_and_reports(): void
    {
        $admin = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $admin->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $this->actingAs($admin);
        $this->comprobar(['/admin/usuarios', '/admin/categorias', '/admin/subastas', '/admin/identidad', '/admin/consultas', '/reportes/subastas', '/reportes/usuarios']);
    }
}
