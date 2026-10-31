<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\FondosService;
use App\Services\NormativaService;
use App\Services\PujaService;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class NormativaTest extends TestCase
{
    use RefreshDatabase;

    private function version(): string
    {
        return app(NormativaService::class)->version();
    }

    public function test_public_reading_and_footer_do_not_require_acceptance(): void
    {
        $this->get('/normas-y-politicas')->assertOk()->assertSee('Artículos prohibidos')->assertSee('Datos y privacidad')->assertSee('Iniciar sesión');
        $this->get('/')->assertOk()->assertSee('Normas y políticas');
        $user = User::factory()->sinAceptarNormas()->create();
        $this->actingAs($user)->get('/buscar')->assertOk();
        $this->get('/normas-y-politicas')->assertSee('name="acepto"', false)->assertDontSee('checked', false);
        $this->assertDatabaseCount('aceptacion_normas', 0);
    }

    public function test_explicit_acceptance_is_private_idempotent_and_does_not_publish(): void
    {
        $user = User::factory()->sinAceptarNormas()->create();
        $other = User::factory()->sinAceptarNormas()->create();
        $this->actingAs($user)->post('/normas-y-politicas/aceptar', ['version' => $this->version()])->assertSessionHasErrors('acepto');
        $this->assertDatabaseCount('aceptacion_normas', 0);
        $payload = ['version' => $this->version(), 'acepto' => 1, 'volver' => '/subastas/crear', 'id_usuario' => $other->id_usuario, 'fecha_aceptacion' => '2000-01-01'];
        $this->post('/normas-y-politicas/aceptar', $payload)->assertRedirect('/subastas/crear');
        $row = DB::table('aceptacion_normas')->sole();
        $this->assertSame($user->id_usuario, $row->id_usuario);
        $this->assertGreaterThan('2000-01-02', $row->fecha_aceptacion);
        $this->post('/normas-y-politicas/aceptar', $payload)->assertRedirect('/subastas/crear');
        $this->assertSame($row->fecha_aceptacion, DB::table('aceptacion_normas')->sole()->fecha_aceptacion);
        $this->assertDatabaseCount('subasta', 0);
        $this->assertDatabaseCount('puja', 0);
    }

    public function test_stale_acceptance_requires_reading_the_new_version_and_keeps_history(): void
    {
        $user = User::factory()->create();
        $old = $this->version();
        DB::unprepared("create or replace function normas_version_vigente() returns text language sql immutable as 'select ''version-nueva''::text';");
        $this->actingAs($user)->get('/subastas/crear')->assertRedirectContains('/normas-y-politicas');
        $this->post('/normas-y-politicas/aceptar', ['acepto' => 1, 'version' => $old])->assertSessionHasErrors('version');
        $this->post('/normas-y-politicas/aceptar', ['acepto' => 1, 'version' => 'version-nueva'])->assertRedirect('/');
        $this->assertDatabaseCount('aceptacion_normas', 2);
        $this->get('/subastas/crear')->assertOk();
    }

    public function test_write_requests_and_sql_operations_cannot_skip_acceptance(): void
    {
        $owner = User::factory()->create();
        $bidder = User::factory()->sinAceptarNormas()->create();
        $data = ['titulo' => 'Artículo de prueba', 'descripcion' => 'Descripción de prueba para las normas.', 'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'id_categoria' => Categoria::firstOrFail()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String()];
        $auction = app(SubastaService::class)->crear($owner->id_usuario, $data);
        $auction->forceFill(['estado_revision' => 'Aceptada'])->save();
        $this->actingAs($bidder)->get('/subastas/'.$auction->id_subasta)->assertOk()->assertSee('Leer y aceptar las normas')->assertDontSee('Confirmar oferta de Bs 100');
        $this->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 100])->assertRedirectContains('/normas-y-politicas');
        $this->post('/subastas', $data)->assertRedirectContains('/normas-y-politicas');
        $this->postJson('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 100])->assertForbidden();
        foreach ([fn () => app(PujaService::class)->registrar($bidder->id_usuario, $auction->id_subasta, '100'), fn () => app(SubastaService::class)->crear($bidder->id_usuario, $data)] as $operation) {
            try {
                $operation();
                $this->fail('Debe exigir aceptación también desde SQL.');
            } catch (ValidationException $e) {
                $this->assertStringContainsString('normas', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('puja', 0);
        $this->assertDatabaseCount('subasta', 1);
        DB::table('aceptacion_normas')->where('id_usuario', $owner->id_usuario)->delete();
        try {
            app(SubastaService::class)->actualizar($owner->id_usuario, $auction->id_subasta, $data);
            $this->fail('La edición SQL también debe exigir las normas.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('normas', $e->getMessage());
        }
        $this->actingAs($owner)->put('/subastas/'.$auction->id_subasta, $data)->assertRedirectContains('/normas-y-politicas');
        $this->post('/normas-y-politicas/aceptar', ['acepto' => 1, 'version' => $this->version(), 'volver' => '/subastas/'.$auction->id_subasta])->assertRedirect('/subastas/'.$auction->id_subasta);
        $this->actingAs($bidder)->post('/normas-y-politicas/aceptar', ['acepto' => 1, 'version' => $this->version()]);
        $fondos = app(FondosService::class);
        $recarga = $fondos->solicitar($bidder->id_usuario, (string) Str::uuid(), '100');
        $fondos->resolver($bidder->id_usuario, $recarga->id_recarga, 'Aprobada');
        $this->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 100])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('puja', 1);
    }

    public function test_guests_admins_and_disabled_accounts_cannot_accept(): void
    {
        $payload = ['acepto' => 1, 'version' => $this->version()];
        $this->post('/normas-y-politicas/aceptar', $payload)->assertRedirect('/iniciar-sesion');
        $user = User::factory()->sinAceptarNormas()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $user->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $this->actingAs($user)->post('/normas-y-politicas/aceptar', $payload)->assertForbidden();
        $disabled = User::factory()->sinAceptarNormas()->create(['activo' => false]);
        $this->actingAs($disabled)->post('/normas-y-politicas/aceptar', $payload)->assertRedirect('/iniciar-sesion');
        $this->assertDatabaseCount('aceptacion_normas', 0);
    }

    public function test_return_addresses_cannot_redirect_outside_the_site(): void
    {
        $user = User::factory()->sinAceptarNormas()->create();
        foreach (['https://example.com', '//example.com', '/\\example.com', '/subastas/1?next=https://example.com'] as $destination) {
            $this->actingAs($user)->post('/normas-y-politicas/aceptar', ['acepto' => 1, 'version' => $this->version(), 'volver' => $destination])->assertRedirect('/');
        }
    }
}
