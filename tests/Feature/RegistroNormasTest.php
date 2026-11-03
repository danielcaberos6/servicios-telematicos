<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\NormativaService;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistroNormasTest extends TestCase
{
    use RefreshDatabase;

    private function datos(): array
    {
        return ['nombres' => 'Ana', 'apellidos' => 'Pérez', 'correo' => 'registro@example.test',
            'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123',
            'normas_version' => app(NormativaService::class)->version()];
    }

    public function test_registration_discloses_acceptance_next_to_submit_without_a_checkbox(): void
    {
        $this->get('/registro')->assertOk()->assertSee('Al hacer clic en «Crear mi cuenta», aceptas nuestros')
            ->assertSee('Términos y condiciones')->assertSee(route('normas.show'), false)
            ->assertSee('name="normas_version"', false)->assertDontSee('type="checkbox"', false);
    }

    public function test_creating_account_records_current_acceptance_and_does_not_ask_again(): void
    {
        $data = $this->datos();
        $this->post('/registro', $data)->assertRedirect('/')->assertSessionHasNoErrors();
        $user = User::where('correo', $data['correo'])->sole();
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('aceptacion_normas', ['id_usuario' => $user->id_usuario, 'version' => $data['normas_version']]);
        $this->assertNotNull(DB::table('aceptacion_normas')->where('id_usuario', $user->id_usuario)->value('fecha_aceptacion'));
        $this->get('/subastas/crear')->assertOk();
        $this->get('/normas-y-politicas')->assertOk()->assertSee('Ya aceptaste la versión')->assertDontSee('name="acepto"', false);
        $this->post('/mi-billetera/recargas', ['referencia' => (string) Str::uuid(), 'monto' => 100])
            ->assertSessionHasNoErrors()->assertRedirectContains('/mi-billetera/recargas/');
        $vendedor = User::factory()->create();
        $subasta = app(SubastaService::class)->crear($vendedor->id_usuario, [
            'titulo' => 'Artículo para nueva cuenta', 'descripcion' => 'Descripción del artículo de prueba.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'incremento_puja' => 10, 'id_categoria' => Categoria::firstOrFail()->id_categoria,
            'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        $subasta->forceFill(['estado_revision' => 'Aceptada'])->save();
        $this->get('/subastas/'.$subasta->id_subasta)->assertOk()->assertDontSee('Leer y aceptar las normas');
        // Sin saldo, se rechaza por fondos; nunca vuelve a pedir aceptación.
        $this->post('/subastas/'.$subasta->id_subasta.'/pujas', ['monto' => 100])->assertRedirect()->assertSessionHasErrors('monto');
    }

    public function test_outdated_version_rolls_back_account_wallet_roles_and_acceptance(): void
    {
        $this->post('/registro', [...$this->datos(), 'normas_version' => 'version-anterior'])->assertSessionHasErrors('version');
        $this->assertGuest();
        foreach (['usuario', 'billetera', 'usuario_rol', 'aceptacion_normas'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_missing_version_cannot_create_an_account_and_existing_users_are_not_backfilled(): void
    {
        $existing = User::factory()->sinAceptarNormas()->create();
        $data = $this->datos();
        unset($data['normas_version']);
        $this->post('/registro', $data)->assertSessionHasErrors('normas_version');
        $this->assertDatabaseCount('usuario', 1);
        $this->assertDatabaseMissing('aceptacion_normas', ['id_usuario' => $existing->id_usuario]);
        $this->assertGuest();
    }
}
