<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\PujaService;
use Database\Seeders\ReportesDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('usuario_rol')->insert([
            'id_usuario' => $user->id_usuario,
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
        ]);

        return $user;
    }

    public function test_only_active_administrators_can_list_accounts(): void
    {
        $this->get('/admin/usuarios')->assertRedirect('/iniciar-sesion');
        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->get('/admin/usuarios')->assertRedirect('/');
        $this->getJson('/admin/usuarios')->assertForbidden();
        $this->actingAs($this->admin())->get('/admin/usuarios')->assertOk()->assertSee('Gestión de usuarios');
    }

    public function test_search_combines_name_or_email_with_state_and_preserves_pagination(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 17; $i++) {
            User::factory()->create(['nombre' => sprintf('Persona %02d', $i), 'correo' => 'persona'.$i.'@example.test']);
        }
        User::factory()->create(['nombre' => 'Persona desactivada'])->forceFill(['activo' => false])->save();

        $this->actingAs($admin)->getJson('/admin/usuarios?q=PERSONA&estado=activo&page=2')
            ->assertOk()->assertJsonPath('total', 17)->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.nombre', 'Persona 15');
        $this->get('/admin/usuarios?q=PERSONA&estado=activo')->assertOk()
            ->assertSee('Página 1 de 2')->assertSee('q=PERSONA', false)->assertSee('estado=activo', false);
        $this->getJson('/admin/usuarios?q=PERSONA&estado=inactivo')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.activo', false);
        $this->getJson('/admin/usuarios?q=PERSONA16%40EXAMPLE.TEST')
            ->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.correo', 'persona16@example.test');
    }

    public function test_list_exposes_only_allowed_fields_and_handles_empty_or_invalid_filters(): void
    {
        $admin = $this->admin();
        $response = $this->actingAs($admin)->getJson('/admin/usuarios')->assertOk();
        $keys = array_keys($response->json('data.0'));
        sort($keys);
        $this->assertSame(['activo', 'administrador', 'correo', 'fecha_registro', 'id_usuario', 'nombre'], $keys);
        $this->get('/admin/usuarios')->assertDontSee($admin->contrasena);
        $this->get('/admin/usuarios?q=inexistente')->assertSee('No se encontraron usuarios con estos filtros.');
        $this->getJson('/admin/usuarios?q=%25')->assertJsonPath('total', 0);
        $this->getJson('/admin/usuarios?estado=desconocido&page=0')->assertUnprocessable()
            ->assertJsonValidationErrors(['estado', 'page']);
    }

    public function test_database_rejects_ordinary_and_inactive_admin_actors(): void
    {
        $ordinary = User::factory()->create();
        $admin = $this->admin()->forceFill(['activo' => false]);
        $admin->save();
        foreach ([$ordinary, $admin] as $actor) {
            try {
                DB::transaction(fn () => DB::select('select listar_usuarios_gestion(?, ?::jsonb)', [$actor->id_usuario, '{}']));
                $this->fail('La consulta debe exigir un administrador activo.');
            } catch (QueryException $exception) {
                $this->assertSame('42501', $exception->errorInfo[0]);
            }
        }
    }

    public function test_account_changes_are_audited_and_repeated_requests_do_not_duplicate_decisions(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $this->actingAs($admin);
        foreach ([['estado', false], ['estado', false], ['estado', true], ['administrador', true], ['administrador', false]] as [$operacion, $valor]) {
            $this->patch('/admin/usuarios/'.$user->id_usuario, compact('operacion', 'valor'))->assertSessionHasNoErrors();
        }
        $this->assertTrue($user->refresh()->activo);
        $this->assertFalse($user->esAdministrador());
        $this->assertDatabaseCount('decision_usuario', 4);
        $this->assertDatabaseHas('decision_usuario', ['id_actor' => $admin->id_usuario, 'id_usuario' => $user->id_usuario]);
        $this->get('/admin/usuarios/'.$user->id_usuario.'/historial')->assertOk()->assertSee('Estado de cuenta');
    }

    public function test_demo_accounts_show_their_existing_auctions_and_bids_without_administrative_decisions(): void
    {
        Storage::fake('public');
        $this->seed(ReportesDemoSeeder::class);
        $admin = User::where('correo', 'admin@subastaya.test')->sole();
        $user = User::where('correo', 'comunidad1@subastaya.test')->sole();

        $response = $this->actingAs($admin)->get('/admin/usuarios/'.$user->id_usuario.'/historial')
            ->assertOk()->assertSee('Camila Torres')->assertSee('Subastas de este usuario')
            ->assertSee('Pujas realizadas')->assertSee('No hay decisiones registradas para esta cuenta.')
            ->assertViewHas('subastas', fn ($p) => $p->total() === 4 && $p->every(fn ($s) => $s->id_usuario === $user->id_usuario))
            ->assertViewHas('pujas', fn ($p) => $p->total() === 10 && $p->every(fn ($bid) => $bid->id_usuario === $user->id_usuario));
        $subasta = $response->viewData('subastas')->first();
        $puja = $response->viewData('pujas')->first();
        $response->assertSee($subasta->titulo)->assertSee(route('admin.subastas.show', $subasta), false)
            ->assertSee($puja->subasta->titulo)->assertSee('Bs '.number_format($puja->monto, 2, ',', '.'));

        $bidder = User::where('correo', 'participante1@subastaya.test')->sole();
        $this->get('/admin/usuarios/'.$bidder->id_usuario.'/historial')
            ->assertOk()->assertViewHas('subastas', fn ($p) => $p->total() === 0)
            ->assertViewHas('pujas', fn ($p) => $p->total() === 2);
    }

    public function test_activity_pages_are_independent_scoped_and_include_unpublished_auctions(): void
    {
        $admin = $this->admin();
        $user = User::factory()->create();
        $other = User::factory()->create();
        $category = Categoria::firstOrCreate(['nombre_categoria' => 'Pruebas']);
        $create = fn (User $seller, string $title, string $state = 'Aceptada') => $seller->subastas()->forceCreate([
            'titulo' => $title, 'descripcion' => 'Artículo usado para probar el historial.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'id_categoria' => $category->id_categoria, 'fecha_inicio' => now()->subHour(),
            'fecha_fin' => now()->addDays(2), 'estado_revision' => $state,
        ]);
        for ($i = 0; $i < 16; $i++) {
            $create($user, 'Publicación propia '.$i, $i === 0 ? 'En revisión' : 'Aceptada');
        }
        $auction = $create($other, 'Subasta donde oferta el usuario');
        for ($i = 0; $i < 16; $i++) {
            app(PujaService::class)->registrar($user->id_usuario, $auction->id_subasta, (string) (100 + $i));
        }
        app(PujaService::class)->registrar($other->id_usuario, $user->subastas()->where('estado_revision', 'Aceptada')->first()->id_subasta, '100');

        $url = '/admin/usuarios/'.$user->id_usuario.'/historial';
        $this->actingAs($admin)->get($url.'?publicaciones=2&ofertas=1')->assertOk()
            ->assertViewHas('subastas', fn ($p) => $p->total() === 16 && $p->count() === 1 && $p->currentPage() === 2)
            ->assertViewHas('pujas', fn ($p) => $p->total() === 16 && $p->count() === 15 && $p->currentPage() === 1)
            ->assertSee('En revisión')->assertSee('publicaciones=2&amp;ofertas=2#pujas-usuario', false);
        $this->get($url.'?publicaciones=1&ofertas=2')->assertOk()
            ->assertViewHas('subastas', fn ($p) => $p->count() === 15 && $p->currentPage() === 1)
            ->assertViewHas('pujas', fn ($p) => $p->count() === 1 && $p->first()->monto === '100.00');
    }

    public function test_empty_history_is_explicit_and_invalid_page_numbers_are_rejected(): void
    {
        $user = User::factory()->create();
        $url = '/admin/usuarios/'.$user->id_usuario.'/historial';
        $this->actingAs($this->admin())->get($url)->assertOk()
            ->assertSee('Este usuario todavía no tiene subastas registradas.')
            ->assertSee('Este usuario todavía no ha realizado pujas.')
            ->assertSee('No hay decisiones registradas para esta cuenta.');
        $this->getJson($url.'?publicaciones=0&ofertas=abc')->assertUnprocessable()
            ->assertJsonValidationErrors(['publicaciones', 'ofertas']);
    }

    public function test_admin_cannot_disable_self_or_remove_the_last_active_admin(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->patch('/admin/usuarios/'.$admin->id_usuario, ['operacion' => 'estado', 'valor' => false])
            ->assertSessionHasErrors('cuenta');
        $this->patch('/admin/usuarios/'.$admin->id_usuario, ['operacion' => 'administrador', 'valor' => false])
            ->assertSessionHasErrors('cuenta');
        $this->assertDatabaseCount('decision_usuario', 0);
        $other = $this->admin();
        $this->patch('/admin/usuarios/'.$admin->id_usuario, ['operacion' => 'administrador', 'valor' => false])
            ->assertSessionHasErrors('cuenta');
        $this->assertTrue($admin->refresh()->esAdministrador());
        $this->assertDatabaseCount('decision_usuario', 0);
        $this->get('/admin/usuarios')->assertOk()
            ->assertDontSee('action="'.route('admin.usuarios.update', $admin).'"', false)
            ->assertSee('action="'.route('admin.usuarios.update', $other).'"', false);

        // Otro administrador sí puede retirar el rol de una cuenta ajena.
        $this->actingAs($other)->patch('/admin/usuarios/'.$admin->id_usuario, ['operacion' => 'administrador', 'valor' => false])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->getJson('/admin/usuarios')->assertForbidden();
        $this->assertTrue($other->esAdministrador());
    }

    public function test_database_rejects_self_role_removal_with_another_active_admin(): void
    {
        $admin = $this->admin();
        $this->admin();

        try {
            DB::transaction(fn () => DB::select('select gestionar_cuenta(?,?,?,?::boolean)', [
                $admin->id_usuario, $admin->id_usuario, 'administrador', 'false',
            ]));
            $this->fail('El administrador no debe poder retirarse su propio rol.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('No puedes retirarte tu propio rol de administrador.', $exception->getMessage());
        }

        $this->assertTrue($admin->refresh()->esAdministrador());
        $this->assertDatabaseCount('decision_usuario', 0);
    }

    public function test_ordinary_users_cannot_change_roles_or_consult_audit_history(): void
    {
        $admin = $this->admin();
        $ordinary = User::factory()->create();
        $this->actingAs($ordinary)->patchJson('/admin/usuarios/'.$ordinary->id_usuario, ['operacion' => 'administrador', 'valor' => true])->assertForbidden();
        $this->getJson('/admin/usuarios/'.$admin->id_usuario.'/historial')->assertForbidden();
        $this->assertFalse($ordinary->esAdministrador());
        $this->assertDatabaseCount('decision_usuario', 0);
    }

    public function test_reinstalling_sql_functions_keeps_the_administrative_access_message(): void
    {
        $ordinary = User::factory()->create();
        $this->artisan('subastas:instalar-funciones')->assertSuccessful();

        try {
            DB::transaction(fn () => DB::select('select exigir_administrador(?)', [$ordinary->id_usuario]));
            $this->fail('Un usuario sin rol administrativo superó exigir_administrador.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('Solo los administradores activos pueden acceder a esta sección.', $exception->getMessage());
        }
    }
}
