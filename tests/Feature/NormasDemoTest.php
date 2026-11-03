<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\NormativaService;
use App\Services\SubastaService;
use Database\Seeders\NormasDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class NormasDemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_startup_demo_account_can_recharge_publish_and_bid_without_reaccepting(): void
    {
        Storage::fake('public');
        $this->artisan('subastas:cargar-demo')->assertSuccessful();
        $usuario = User::where('correo', 'comunidad2@subastaya.test')->sole();
        $normas = app(NormativaService::class);
        $this->assertTrue($normas->aceptada($usuario->id_usuario));
        $this->actingAs($usuario)->get('/subastas/crear')->assertOk();
        $ref = (string) Str::uuid();
        $this->post('/mi-billetera/recargas', ['referencia' => $ref, 'monto' => 500])->assertSessionHasNoErrors()->assertRedirectContains('/mi-billetera/recargas/');
        $this->post('/mi-billetera/recargas/'.$ref, ['resultado' => 'Aprobada'])->assertSessionHasNoErrors();
        $vendedor = User::where('correo', 'demo@subastaya.test')->sole();
        $subasta = app(SubastaService::class)->crear($vendedor->id_usuario, [
            'titulo' => 'Artículo para probar normas', 'descripcion' => 'Descripción de prueba del artículo.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'incremento_puja' => 10, 'id_categoria' => Categoria::firstOrFail()->id_categoria,
            'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
        $subasta->forceFill(['estado_revision' => 'Aceptada'])->save();
        $this->get('/subastas/'.$subasta->id_subasta)->assertOk()->assertDontSee('Leer y aceptar las normas');
        $this->post('/subastas/'.$subasta->id_subasta.'/pujas', ['monto' => 100])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('puja', ['id_subasta' => $subasta->id_subasta, 'id_usuario' => $usuario->id_usuario]);
    }

    public function test_demo_preparation_is_idempotent_and_does_not_accept_for_real_or_admin_accounts(): void
    {
        $demo = User::factory()->sinAceptarNormas()->create(['correo' => 'comunidad2@subastaya.test']);
        $real = User::factory()->sinAceptarNormas()->create(['correo' => 'persona@example.test']);
        $otro = User::factory()->sinAceptarNormas()->create(['correo' => 'otra@subastaya.test']);
        $admin = User::factory()->sinAceptarNormas()->create(['correo' => 'demo@subastaya.test']);
        DB::table('usuario_rol')->insert(['id_usuario' => $admin->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $this->seed(NormasDemoSeeder::class);
        $registro = DB::table('aceptacion_normas')->sole();
        $this->assertSame($demo->id_usuario, $registro->id_usuario);
        $this->seed(NormasDemoSeeder::class);
        $this->assertSame($registro->fecha_aceptacion, DB::table('aceptacion_normas')->sole()->fecha_aceptacion);
        foreach ([$real, $otro, $admin] as $cuenta) {
            $this->assertDatabaseMissing('aceptacion_normas', ['id_usuario' => $cuenta->id_usuario]);
        }
    }

    public function test_demo_acceptances_are_not_generated_in_production(): void
    {
        User::factory()->sinAceptarNormas()->create(['correo' => 'comunidad2@subastaya.test']);
        $this->app['env'] = 'production';
        app(NormasDemoSeeder::class)->run();
        $this->assertDatabaseCount('aceptacion_normas', 0);
    }
}
