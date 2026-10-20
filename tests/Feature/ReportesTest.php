<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use App\Services\ReporteService;
use Database\Seeders\ReportesDemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReportesTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['fecha_registro' => '2024-01-01', 'ciudad' => 'La Paz']);
        DB::table('usuario_rol')->insert(['id_usuario' => $user->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);

        return $user;
    }

    private function auction(User $owner, array $data = []): Subasta
    {
        return $owner->subastas()->create(array_replace([
            'titulo' => 'Publicación para reportes', 'descripcion' => 'Descripción del artículo para probar reportes.',
            'ubicacion' => 'Tarija, plaza principal', 'id_categoria' => Categoria::where('nombre_categoria', 'Deporte')->value('id_categoria'),
            'estado_articulo' => 'Usado', 'monto_inicial' => '100.50', 'fecha_inicio' => '2025-06-15 10:00:00-04', 'fecha_fin' => now()->addDays(5),
        ], $data));
    }

    public function test_only_administrators_can_open_reports_or_obtain_their_data(): void
    {
        foreach (['subastas', 'usuarios'] as $type) {
            $this->get('/reportes/'.$type)->assertRedirect('/iniciar-sesion');
        }
        $ordinary = User::factory()->create();
        $admin = $this->admin();
        $this->actingAs($ordinary)->get('/')->assertDontSee('Reportes</a>', false);
        foreach (['subastas', 'usuarios'] as $type) {
            $this->get('/reportes/'.$type.'?actor='.$admin->id_usuario)->assertRedirect('/');
            $this->getJson('/reportes/'.$type)->assertForbidden();
        }
        $this->actingAs($admin)->get('/')->assertSee('Reportes</a>', false);
        $this->get('/reportes/subastas')->assertOk()->assertSee('Reporte de Subastas');
        $this->get('/reportes/usuarios')->assertOk()->assertSee('Reporte de Usuarios');
        DB::table('usuario_rol')->where('id_usuario', $admin->id_usuario)->where('id_rol', DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'))->delete();
        $this->getJson('/reportes/subastas')->assertForbidden();
    }

    public function test_database_functions_also_reject_an_ordinary_user(): void
    {
        $user = User::factory()->create();
        foreach (['reporte_subastas', 'reporte_usuarios'] as $function) {
            try {
                DB::transaction(fn () => DB::select('select '.$function.'(?,?::jsonb)', [$user->id_usuario, json_encode(['desde' => '2025-06-01', 'hasta' => '2025-06-30'])]));
                $this->fail('Debe exigir el rol Administrador en PostgreSQL.');
            } catch (QueryException $e) {
                $this->assertSame('42501', $e->errorInfo[0]);
            }
        }
    }

    public function test_auction_report_uses_inclusive_bolivia_dates_and_effective_state(): void
    {
        $admin = $this->admin();
        $this->auction($admin, ['titulo' => 'Primera del mes', 'fecha_inicio' => '2025-06-01T00:00:00-04:00']);
        $this->auction($admin, ['titulo' => 'Última del mes', 'fecha_inicio' => '2025-06-30T23:59:59.999999-04:00']);
        $ended = $this->auction($admin, ['fecha_fin' => '2025-06-16T00:00:00-04:00']);
        $cancelled = $this->auction($admin);
        $cancelled->forceFill(['estado_subasta' => 'Cancelada'])->save();
        $this->auction($admin, ['fecha_inicio' => '2025-07-01T00:00:00-04:00']);
        $this->auction($admin, ['fecha_inicio' => '2025-05-31T23:59:59-04:00']);
        $result = $this->actingAs($admin)->getJson('/reportes/subastas?desde=2025-06-01&hasta=2025-06-30')->assertOk();
        $result->assertJsonPath('indicadores.total', 4)->assertJsonPath('indicadores.activas', 2)
            ->assertJsonPath('indicadores.finalizadas', 1)->assertJsonPath('indicadores.canceladas', 1)
            ->assertJsonPath('meses.0.cantidad', 4)->assertJsonPath('categorias.0.cantidad', 4);
        $this->assertSame('Finalizada', collect($result->json('detalle'))->firstWhere('id_subasta', $ended->id_subasta)['estado']);
    }

    public function test_filters_and_pagination_apply_to_the_whole_auction_report(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 17; $i++) {
            $this->auction($admin, ['titulo' => 'Bicicleta '.$i, 'fecha_fin' => now()->addDays($i + 1)]);
        }
        $this->auction($admin, ['ubicacion' => 'Sucre']);
        $this->actingAs($admin)->getJson('/reportes/subastas?desde=2025-06-01&hasta=2025-06-30&ubicacion=Tarija&estado=Activa&page=2')
            ->assertOk()->assertJsonPath('total', 17)->assertJsonCount(2, 'detalle')->assertJsonPath('indicadores.total', 17);
        $this->get('/reportes/subastas?desde=2025-06-01&hasta=2025-06-30&ubicacion=Tarija&estado=Activa')->assertOk()->assertSee('Página 1 de 2')->assertSee('ubicacion=Tarija', false);
        $data = app(ReporteService::class)->consultar('subastas', $admin->id_usuario, ['desde' => '2025-06-01', 'hasta' => '2025-06-30', 'q' => 'Bicicleta']);
        $this->assertSame('Bicicleta 16', $data['detalle'][0]['titulo']);
    }

    public function test_user_report_compares_equal_periods_and_weights_reviews_correctly(): void
    {
        $admin = $this->admin();
        $people = [];
        foreach (['2025-05-05', '2025-05-20', '2025-06-01', '2025-06-05', '2025-06-20', '2025-06-30 23:59:59-04', '2025-07-01'] as $i => $date) {
            $people[] = User::factory()->create(['nombre' => 'Usuario '.$i, 'fecha_registro' => $date, 'ciudad' => 'Tarija']);
        }
        $this->auction($people[0]);
        $this->auction($people[0], ['fecha_inicio' => '2025-05-15']);
        $this->auction($people[1], ['fecha_inicio' => '2025-07-15']);
        foreach ([[0, 5], [0, 5], [1, 1]] as [$person, $stars]) {
            DB::table('resenas')->insert(['id_usuario_resenado' => $people[$person]->id_usuario, 'id_usuario_resenador' => $admin->id_usuario, 'calificacion' => $stars, 'fecha_creacion' => '2025-06-15']);
        }
        DB::table('resenas')->insert(['id_usuario_resenado' => $people[0]->id_usuario, 'id_usuario_resenador' => $admin->id_usuario, 'calificacion' => 1, 'fecha_creacion' => '2025-07-01']);
        $this->actingAs($admin)->getJson('/reportes/usuarios?desde=2025-06-01&hasta=2025-06-30&ciudad=Tarija')->assertOk()
            ->assertJsonPath('indicadores.total', 6)->assertJsonPath('indicadores.nuevos', 4)->assertJsonPath('indicadores.anteriores', 2)
            ->assertJsonPath('indicadores.crecimiento', 100)->assertJsonPath('indicadores.publicadores', 1)
            ->assertJsonPath('indicadores.valoracion', 3.67)->assertJsonPath('valoraciones.4.cantidad', 2)->assertJsonPath('valoraciones.0.cantidad', 1)
            ->assertJsonPath('detalle.0.id_usuario', $people[0]->id_usuario)->assertJsonPath('detalle.0.publicaciones', 2);
        $this->getJson('/reportes/usuarios?desde=2025-06-01&hasta=2025-06-30&ciudad=Tarija&actividad=publicadores&valoracion=4')->assertJsonPath('total', 1);
    }

    public function test_no_new_users_produces_flat_cumulative_line_and_empty_reports_are_safe(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/reportes/usuarios?desde=2025-05-01&hasta=2025-06-30')->assertOk()
            ->assertJsonPath('indicadores.total', 1)->assertJsonPath('indicadores.nuevos', 0)->assertJsonPath('indicadores.crecimiento', 0)
            ->assertJsonPath('meses.0.cantidad', 1)->assertJsonPath('meses.1.cantidad', 1)->assertJsonPath('indicadores.valoracion', null);
        $this->get('/reportes/usuarios?desde=1901-01-01&hasta=1901-03-31')->assertOk()->assertSee('Sin datos para el periodo seleccionado');
        $this->getJson('/reportes/subastas?desde=2025-05-01&hasta=2025-06-30')->assertJsonPath('indicadores.total', 0)->assertJsonCount(0, 'detalle')->assertJsonPath('meses.1.cantidad', 0);
        $this->get('/reportes/subastas?desde=2025-05-01&hasta=2025-06-30')->assertOk()->assertSee('Sin datos para el periodo seleccionado');
    }

    public function test_invalid_dates_and_filters_are_rejected(): void
    {
        $this->actingAs($this->admin());
        foreach (['subastas', 'usuarios'] as $type) {
            $this->getJson('/reportes/'.$type.'?desde=2025-06-30&hasta=2025-06-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
            $this->getJson('/reportes/'.$type.'?desde=2025-02-30')->assertUnprocessable()->assertJsonValidationErrors('desde');
        }
        $this->getJson('/reportes/subastas?estado=Inexistente')->assertUnprocessable()->assertJsonValidationErrors('estado');
        $this->getJson('/reportes/usuarios?valoracion=9')->assertUnprocessable()->assertJsonValidationErrors('valoracion');
    }

    public function test_report_examples_are_varied_and_do_not_duplicate_on_reseed(): void
    {
        Storage::fake('public');
        $this->seed(ReportesDemoSeeder::class);
        $counts = [User::count(), Subasta::count(), DB::table('puja')->count(), DB::table('resenas')->count()];
        $this->seed(ReportesDemoSeeder::class);
        $this->assertSame($counts, [User::count(), Subasta::count(), DB::table('puja')->count(), DB::table('resenas')->count()]);
        $this->assertSame(47, Subasta::count());
        $this->assertSame(0, User::where('nombre', 'like', '%Demo%')->count());
        $this->assertGreaterThanOrEqual(9, User::distinct('ciudad')->count('ciudad'));
        $admin = User::where('correo', 'admin@subastaya.test')->firstOrFail();
        $this->assertTrue($admin->esAdministrador());
        $this->assertFalse(User::where('correo', 'demo@subastaya.test')->firstOrFail()->esAdministrador());
    }
}
