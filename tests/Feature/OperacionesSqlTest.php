<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Imagen;
use App\Models\Subasta;
use App\Models\User;
use App\Services\OperacionesService;
use Database\Seeders\ActividadDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OperacionesSqlTest extends TestCase
{
    use RefreshDatabase;

    private function auction(User $owner): Subasta
    {
        return app(OperacionesService::class)->crearSubasta($owner->id_usuario, [
            'titulo' => 'Subasta para comprobar funciones', 'descripcion' => 'Descripción de prueba con suficiente detalle.',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'estado_articulo' => 'Usado', 'monto_inicial' => '100.00',
            'id_categoria' => Categoria::first()->id_categoria, 'fecha_fin' => now()->addDays(2)->toIso8601String(),
        ]);
    }

    public function test_script_installs_exact_default_categories_and_can_reseed_without_duplicates(): void
    {
        $this->seed();
        $this->seed();
        $this->assertEqualsCanonicalizing([
            'Vehículos', 'Antigüedades y coleccionables', 'Electrónica e informática', 'Muebles', 'Juguetes y juegos',
            'Ropa, calzado y accesorios', 'Instrumentos musicales', 'Deporte', 'Videojuegos', 'Libros, películas y música', 'Varios',
        ], Categoria::pluck('nombre_categoria')->all());
        $this->assertSame(['Nuevo', 'Usado'], Subasta::CONDITIONS);
    }

    public function test_ranking_groups_each_users_best_bid_and_is_private_to_participants_and_owner(): void
    {
        $owner = User::factory()->create();
        $one = User::factory()->create(['nombre' => 'Postor Uno']);
        $two = User::factory()->create(['nombre' => 'Postor Dos']);
        $auction = $this->auction($owner);
        $service = app(OperacionesService::class);
        $service->pujar($one->id_usuario, $auction->id_subasta, '100.00');
        $service->pujar($two->id_usuario, $auction->id_subasta, '110.00');
        $service->pujar($one->id_usuario, $auction->id_subasta, '120.00');
        $ranking = $service->ranking($auction->id_subasta, $owner->id_usuario);
        $this->assertCount(2, $ranking);
        $this->assertSame($one->id_usuario, $ranking[0]->id_usuario);
        $this->assertSame('120.00', $ranking[0]->mejor_oferta);
        $this->assertSame(2, $ranking[0]->ofertas);
        $url = '/subastas/'.$auction->id_subasta;
        $this->get($url)->assertOk()->assertDontSee('Ranking de participantes');
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Ranking de participantes')->assertSee('Líder')->assertSee('Finaliza el');
        $this->actingAs($two)->get($url)->assertSee('Postor Uno')->assertDontSee($one->correo);
        $outsider = User::factory()->create();
        $this->actingAs($outsider)->get($url)->assertDontSee('Ranking de participantes');
        $this->expectException(HttpException::class);
        $service->ranking($auction->id_subasta, $outsider->id_usuario);
    }

    public function test_welcome_and_outbid_notifications_are_created_in_database_exactly_once(): void
    {
        $owner = User::factory()->create();
        $one = User::factory()->create();
        $two = User::factory()->create();
        $this->assertSame(1, $one->notificaciones()->where('titulo', '¡Bienvenido a SubastaYA!')->count());
        $this->assertDatabaseHas('usuario_rol', ['id_usuario' => $one->id_usuario]);
        $auction = $this->auction($owner);
        $service = app(OperacionesService::class);
        $service->pujar($one->id_usuario, $auction->id_subasta, '100.00');
        $service->pujar($one->id_usuario, $auction->id_subasta, '110.00');
        $this->assertSame(0, $one->notificaciones()->where('titulo', 'Superaron tu oferta')->count());
        $service->pujar($two->id_usuario, $auction->id_subasta, '120.00');
        $this->assertSame(1, $one->notificaciones()->where('titulo', 'Superaron tu oferta')->count());
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $one->id_usuario, 'id_subasta' => $auction->id_subasta, 'titulo' => 'Superaron tu oferta']);
        $this->actingAs($one)->get('/notificaciones')->assertSee('Ver subasta');
    }

    public function test_rating_chart_uses_actual_distribution_including_empty_bins(): void
    {
        $user = User::factory()->create();
        $reviewer = User::factory()->create();
        foreach ([1, 4, 4.5, 5] as $stars) {
            DB::table('resenas')->insert(['id_usuario_resenado' => $user->id_usuario, 'id_usuario_resenador' => $reviewer->id_usuario, 'calificacion' => $stars]);
        }
        $ratings = app(OperacionesService::class)->valoraciones($user->id_usuario);
        $this->assertSame([1, 0, 0, 1, 2], $ratings->pluck('cantidad')->all());
        $this->actingAs($user)->get('/perfil')->assertOk()->assertSee('Mis valoraciones')->assertSee('5 estrellas: 2 valoraciones')->assertSee('3.6');
        $this->actingAs($reviewer)->get('/perfil')->assertOk()->assertSee('0 valoraciones recibidas');
    }

    public function test_coordinates_are_optional_but_must_form_a_valid_pair(): void
    {
        $auction = $this->auction(User::factory()->create());
        $this->assertNull($auction->latitud);
        $this->assertNull($auction->longitud);
        $auction->update(['latitud' => -16.5, 'longitud' => -68.15]);
        $this->assertSame('-16.500000', $auction->fresh()->latitud);
        foreach ([['latitud' => 91, 'longitud' => 0], ['latitud' => 0, 'longitud' => 181], ['latitud' => 0, 'longitud' => null]] as $invalid) {
            try {
                DB::transaction(fn () => $auction->update($invalid));
                $this->fail('La BD debe rechazar coordenadas inválidas.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
    }

    public function test_sql_mutations_check_ownership_even_without_controller_checks(): void
    {
        $auction = $this->auction(User::factory()->create());
        $intruder = User::factory()->create();
        try {
            app(OperacionesService::class)->eliminarSubasta($intruder->id_usuario, $auction->id_subasta);
            $this->fail('Debe impedir la eliminación.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $this->assertDatabaseHas('subasta', ['id_subasta' => $auction->id_subasta]);
    }

    public function test_demo_images_and_activity_are_persisted_and_reseeding_is_idempotent(): void
    {
        Storage::fake('public');
        $this->seed(ActividadDemoSeeder::class);
        $this->seed(ActividadDemoSeeder::class);
        $this->assertDatabaseCount('subasta', 7);
        $this->assertDatabaseCount('imagen', 7);
        $this->assertDatabaseCount('resenas', 5);
        $this->assertDatabaseCount('puja', 5);
        foreach (Imagen::all() as $image) {
            Storage::disk('public')->assertExists($image->ruta);
            $this->get('/imagenes/'.$image->id_imagen)->assertOk();
        }
    }
}
