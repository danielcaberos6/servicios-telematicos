<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use App\Services\ParticipacionService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SeguimientoParticipacionesTest extends TestCase
{
    use RefreshDatabase;

    private function subasta(array $datos = []): Subasta
    {
        return User::factory()->create()->subastas()->forceCreate(array_replace([
            'titulo' => 'Artículo para seguimiento', 'descripcion' => 'Artículo para comprobar las participaciones privadas.',
            'ubicacion' => 'La Paz', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'modelo_puja' => 1,
            'fecha_inicio' => now()->subDay(), 'fecha_fin' => now()->addDays(2), 'estado_revision' => 'Aceptada',
        ], $datos))->refresh();
    }

    private function pujar(Subasta $s, User $u, int $monto): void
    {
        $s->pujas()->create(['id_usuario' => $u->id_usuario, 'monto' => $monto]);
    }

    public function test_list_groups_bids_and_filters_every_result_privately(): void
    {
        $yo = User::factory()->create();
        $otro = User::factory()->create();
        $ganando = $this->subasta(['titulo' => 'Voy liderando']);
        $this->pujar($ganando, $yo, 100);
        $this->pujar($ganando, $yo, 110);
        $superada = $this->subasta(['titulo' => 'Me superó otra cuenta']);
        $this->pujar($superada, $yo, 100);
        $this->pujar($superada, $otro, 120);
        // Dos cierres próximos permiten probar el estado efectivo antes de que pase el scheduler.
        $ganada = $this->subasta(['titulo' => 'Artículo ganado', 'fecha_fin' => now()->addSeconds(2)]);
        $this->pujar($ganada, $yo, 100);
        $perdida = $this->subasta(['titulo' => 'Artículo no ganado', 'fecha_fin' => now()->addSeconds(2)]);
        $this->pujar($perdida, $yo, 100);
        $this->pujar($perdida, $otro, 150);
        $ajena = $this->subasta(['titulo' => 'Participación ajena']);
        $this->pujar($ajena, $otro, 100);
        DB::statement('select pg_sleep(2.1)');
        $this->actingAs($yo)->get('/mis-participaciones?usuario='.$otro->id_usuario)->assertOk()
            ->assertViewHas('subastas', fn ($p) => $p->total() === 4 && $p->firstWhere('id_subasta', $ganando->id_subasta)->oferta_propia === '110.00')
            ->assertDontSee('Participación ajena')->assertDontSee($otro->correo);
        foreach (['ganando' => 'Voy liderando', 'superadas' => 'Me superó otra cuenta', 'ganadas' => 'Artículo ganado', 'no_ganadas' => 'Artículo no ganado'] as $apartado => $titulo) {
            $this->get('/mis-participaciones?apartado='.$apartado)->assertOk()->assertSee($titulo)->assertViewHas('subastas', fn ($p) => $p->total() === 1);
        }
        $this->get('/mis-participaciones?apartado=canceladas')->assertOk()->assertSee('No tienes participaciones en este apartado');
        $this->get('/mis-participaciones?apartado=inventado')->assertSessionHasErrors('apartado');
    }

    public function test_participations_and_favorites_paginate_fifteen_items_and_keep_filters(): void
    {
        $yo = User::factory()->create();
        for ($i = 0; $i < 16; $i++) {
            $s = $this->subasta(['titulo' => 'Artículo '.$i]);
            $this->pujar($s, $yo, 100);
            app(ParticipacionService::class)->guardar($yo->id_usuario, $s->id_subasta);
        }
        $this->actingAs($yo)->get('/mis-participaciones?apartado=ganando&page=2')->assertOk()
            ->assertViewHas('subastas', fn ($p) => $p->total() === 16 && $p->count() === 1)->assertSee('apartado=ganando', false);
        $this->get('/favoritos?page=2')->assertOk()->assertViewHas('subastas', fn ($p) => $p->total() === 16 && $p->count() === 1);
    }

    public function test_saving_is_idempotent_private_and_does_not_bid_or_disclose_unavailable_items(): void
    {
        $yo = User::factory()->create();
        $otro = User::factory()->create();
        $s = $this->subasta();
        $url = '/favoritos/'.$s->id_subasta;
        $this->actingAs($yo)->put($url)->assertSessionHasNoErrors();
        $this->put($url)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('favorito_subasta', 1);
        $this->assertSame(0, $s->pujas()->count());
        $this->actingAs($otro)->get('/favoritos?usuario='.$yo->id_usuario)->assertOk()->assertDontSee($s->titulo);
        $this->delete($url)->assertRedirect();
        $this->assertDatabaseCount('favorito_subasta', 1);
        $this->actingAs($yo)->get('/favoritos')->assertSee($s->titulo);
        $s->forceFill(['estado_revision' => 'En revisión'])->save();
        $this->get('/favoritos')->assertDontSee($s->titulo);
        $this->put($url)->assertNotFound();
        $this->delete($url)->assertRedirect();
        $this->assertDatabaseCount('favorito_subasta', 0);
    }

    public function test_visitors_administrators_and_inactive_accounts_cannot_use_private_sections(): void
    {
        foreach (['/mis-participaciones', '/favoritos'] as $url) {
            $this->get($url)->assertRedirect('/iniciar-sesion');
        }
        $admin = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $admin->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $s = $this->subasta();
        $this->actingAs($admin)->getJson('/mis-participaciones')->assertForbidden();
        $this->getJson('/favoritos')->assertForbidden();
        $this->put('/favoritos/'.$s->id_subasta)->assertForbidden();
        $this->delete('/favoritos/'.$s->id_subasta)->assertForbidden();
        $inactive = User::factory()->create(['activo' => false]);
        foreach ([$admin->id_usuario, $inactive->id_usuario, null] as $actor) {
            foreach (['listar_participaciones', 'listar_favoritos'] as $funcion) {
                try {
                    DB::transaction(fn () => DB::select('select * from '.$funcion.'(?)', [$actor]));
                    $this->fail('La consulta debe rechazar esta cuenta.');
                } catch (QueryException $e) {
                    $this->assertSame('42501', $e->errorInfo[0]);
                }
            }
        }
    }

    public function test_scheduler_notifies_once_only_eligible_favorites_without_external_messages(): void
    {
        $yo = User::factory()->create();
        $servicio = app(ParticipacionService::class);
        $valid = $this->subasta(['fecha_fin' => now()->addMinutes(20)]);
        $late = $this->subasta(['fecha_fin' => now()->addMinutes(20)]);
        $future = $this->subasta(['fecha_fin' => now()->addMinutes(40)]);
        $expired = $this->subasta(['fecha_fin' => now()->subMinute()]);
        $cancelled = $this->subasta(['estado_subasta' => 'Cancelada', 'fecha_fin' => now()->addMinutes(20)]);
        foreach ([$valid, $late, $future, $expired, $cancelled] as $s) {
            $servicio->guardar($yo->id_usuario, $s->id_subasta);
        }
        DB::table('favorito_subasta')->where('id_subasta', '<>', $late->id_subasta)->update(['fecha_guardado' => now()->subHour()]);
        $this->artisan('subastas:recordar-favoritos')->assertSuccessful();
        $this->artisan('subastas:recordar-favoritos')->assertSuccessful();
        $avisos = $yo->notificaciones()->where('titulo', 'Tu favorito está por finalizar')->get();
        $this->assertCount(1, $avisos);
        $this->assertSame($valid->id_subasta, $avisos->sole()->id_subasta);
        $servicio->retirar($yo->id_usuario, $valid->id_subasta);
        $servicio->guardar($yo->id_usuario, $valid->id_subasta);
        DB::table('favorito_subasta')->where('id_subasta', $valid->id_subasta)->update(['fecha_guardado' => now()->subHour()]);
        $this->artisan('subastas:recordar-favoritos')->assertSuccessful();
        $this->assertSame(1, $yo->notificaciones()->where('titulo', 'Tu favorito está por finalizar')->count());
        $this->assertDatabaseCount('recordatorio_favorito', 1);
    }

    public function test_cancelled_historical_participations_do_not_appear_as_wins(): void
    {
        $yo = User::factory()->create();
        $s = $this->subasta(['titulo' => 'Cancelación histórica']);
        $this->pujar($s, $yo, 100);
        // Representa un registro histórico; no habilita cancelar publicaciones con pujas en la aplicación.
        DB::statement('alter table subasta disable trigger subasta_proteccion');
        try {
            $s->forceFill(['estado_subasta' => 'Cancelada'])->save();
        } finally {
            DB::statement('alter table subasta enable trigger subasta_proteccion');
        }
        $this->actingAs($yo)->get('/mis-participaciones?apartado=canceladas')->assertOk()
            ->assertSee('Cancelación histórica')->assertViewHas('subastas', fn ($p) => $p->total() === 1);
        $this->get('/mis-participaciones?apartado=ganadas')->assertViewHas('subastas', fn ($p) => $p->total() === 0);
    }

    public function test_reminders_exclude_unavailable_publications_and_ineligible_accounts(): void
    {
        $yo = User::factory()->create();
        $s = $this->subasta(['fecha_fin' => now()->addMinutes(20)]);
        app(ParticipacionService::class)->guardar($yo->id_usuario, $s->id_subasta);
        DB::table('favorito_subasta')->update(['fecha_guardado' => now()->subHour()]);
        $s->forceFill(['estado_revision' => 'En revisión'])->save();
        $this->assertSame(0, DB::selectOne('select recordar_cierres_favoritos() as total')->total);
        $s->forceFill(['estado_revision' => 'Aceptada'])->save();
        $yo->forceFill(['activo' => false])->save();
        $this->assertSame(0, DB::selectOne('select recordar_cierres_favoritos() as total')->total);
        $yo->forceFill(['activo' => true])->save();
        DB::table('usuario_rol')->insert(['id_usuario' => $yo->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        $this->assertSame(0, DB::selectOne('select recordar_cierres_favoritos() as total')->total);
        $this->assertDatabaseCount('recordatorio_favorito', 0);
    }
}
