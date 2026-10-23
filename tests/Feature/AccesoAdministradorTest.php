<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccesoAdministradorTest extends TestCase
{
    use RefreshDatabase;

    private function administrador(): User
    {
        $user = User::factory()->create();
        foreach (['Administrador', 'Usuario'] as $rol) {
            DB::table('usuario_rol')->insertOrIgnore([
                'id_usuario' => $user->id_usuario,
                'id_rol' => DB::table('rol')->where('nombre_rol', $rol)->value('id_rol'),
            ]);
        }

        return $user;
    }

    private function subasta(User $user): Subasta
    {
        return $user->subastas()->forceCreate([
            'titulo' => 'Artículo de prueba', 'descripcion' => 'Descripción completa del artículo de prueba.',
            'id_categoria' => Categoria::first()->id_categoria, 'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz', 'monto_inicial' => 100,
            'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDays(2),
            'estado_revision' => 'Aceptada',
        ])->refresh();
    }

    public function test_administrator_enters_management_and_cannot_open_user_sections_even_with_both_roles(): void
    {
        $admin = $this->administrador();
        $subasta = $this->subasta($admin);
        $this->post('/iniciar-sesion', ['correo' => $admin->correo, 'password' => 'password'])
            ->assertRedirect('/admin/usuarios');

        foreach (['/', '/buscar', '/mis-subastas', '/subastas/crear', "/subastas/{$subasta->id_subasta}/editar", '/iniciar-sesion', '/registro'] as $url) {
            $this->get($url)->assertRedirect('/admin/usuarios');
        }
        foreach (['/buscar', '/mis-subastas', '/subastas/crear'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
        $this->get('/admin/usuarios')->assertOk()->assertDontSee('href="'.route('inicio').'"', false)
            ->assertDontSee('href="'.route('subastas.index').'"', false)->assertSee('Usuarios')->assertSee('Categorías');
        $this->get('/notificaciones')->assertOk();
        $this->get('/perfil')->assertOk()->assertDontSee('Solicitar verificación');
    }

    public function test_administrator_cannot_publish_edit_delete_bid_or_request_personal_verification(): void
    {
        $admin = $this->administrador();
        $propia = $this->subasta($admin);
        $ajena = $this->subasta(User::factory()->create());
        $this->actingAs($admin);

        $this->post('/subastas', [])->assertForbidden();
        $this->put("/subastas/{$propia->id_subasta}", [])->assertForbidden();
        $this->delete("/subastas/{$propia->id_subasta}")->assertForbidden();
        $this->post("/subastas/{$ajena->id_subasta}/pujas", ['monto' => 200])->assertForbidden();
        $this->post('/perfil/identidad', [])->assertForbidden();
        $this->get("/subastas/{$ajena->id_subasta}")->assertOk()->assertDontSee('Hacer oferta')
            ->assertSee('Volver a administración');
        $this->assertDatabaseCount('subasta', 2);
        $this->assertDatabaseCount('puja', 0);
        $this->assertDatabaseCount('verificacion_identidad', 0);
    }

    public function test_users_and_guests_keep_their_existing_access(): void
    {
        $this->get('/')->assertOk();
        $this->get('/buscar')->assertOk();
        $this->get('/mis-subastas')->assertRedirect('/iniciar-sesion');
        $this->actingAs(User::factory()->create());
        foreach (['/', '/buscar', '/mis-subastas', '/subastas/crear', '/perfil'] as $url) {
            $this->get($url)->assertOk();
        }
        $this->getJson('/admin/usuarios')->assertForbidden();
    }
}
