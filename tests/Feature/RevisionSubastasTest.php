<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevisionSubastasTest extends TestCase
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

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'titulo' => 'Mochila de excursión 40 litros',
            'descripcion' => 'Mochila con armazón interno, cubierta para lluvia y bolsillos laterales.',
            'id_categoria' => Categoria::firstOrCreate(['nombre_categoria' => 'Deporte'])->id_categoria,
            'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz, Plaza Avaroa',
            'latitud' => '-16.510400',
            'longitud' => '-68.126500',
            'monto_inicial' => '320.00',
            'fecha_fin' => now()->addDays(4)->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    private function auction(string $revision, array $overrides = []): Subasta
    {
        return User::factory()->create()->subastas()->forceCreate([
            ...array_diff_key($this->payload(), ['fecha_fin' => true]),
            'titulo' => "Solicitud {$revision}",
            'fecha_inicio' => now()->subHour(),
            'fecha_fin' => now()->addDays(4),
            'estado_revision' => $revision,
            'motivo_rechazo' => $revision === 'Rechazada' ? 'Las fotos no muestran el artículo.' : null,
            ...$overrides,
        ])->refresh();
    }

    public function test_valid_request_stays_in_review_owned_by_the_seller_and_unpublished(): void
    {
        Storage::fake('public');
        $seller = User::factory()->create();

        $this->actingAs($seller)->post('/subastas', $this->payload([
            'imagenes' => [UploadedFile::fake()->image('mochila.jpg')],
            'estado_revision' => 'Aceptada',
            'id_usuario' => User::factory()->create()->id_usuario,
        ]))->assertSessionHasNoErrors()->assertRedirect()
            ->assertSessionHas('status', 'Enviaste tu subasta a revisión. Se publicará cuando un administrador la acepte.');

        $auction = Subasta::with('imagenes')->sole();
        $this->assertSame('En revisión', $auction->estado_revision);
        $this->assertNull($auction->motivo_rechazo);
        $this->assertSame($seller->id_usuario, $auction->id_usuario);
        $this->assertSame('La Paz, Plaza Avaroa', $auction->ubicacion);
        $this->assertCount(1, $auction->imagenes);
        $this->get('/buscar')->assertDontSee('Mochila de excursión 40 litros');
        $this->get('/')->assertDontSee('Mochila de excursión 40 litros');
    }

    public function test_inactive_seller_cannot_submit_a_request(): void
    {
        $seller = User::factory()->create();
        $seller->forceFill(['activo' => false])->save();

        $this->actingAs($seller)->post('/subastas', $this->payload())->assertRedirect('/iniciar-sesion');
        $this->assertDatabaseCount('subasta', 0);
    }

    public function test_requests_in_review_or_rejected_are_hidden_from_third_parties(): void
    {
        Storage::fake('public');
        $accepted = $this->auction('Aceptada');
        $stranger = User::factory()->create();
        foreach (['En revisión', 'Rechazada'] as $revision) {
            $auction = $this->auction($revision);
            Storage::disk('public')->put("subastas/{$auction->id_subasta}/foto.jpg", 'imagen');
            $image = $auction->imagenes()->create(['ruta' => "subastas/{$auction->id_subasta}/foto.jpg", 'tamano' => 6]);

            foreach ([null, $stranger] as $viewer) {
                $viewer ? $this->actingAs($viewer) : auth()->logout();
                $this->get('/')->assertOk()->assertDontSee("Solicitud {$revision}")->assertSee($accepted->titulo);
                $this->get('/buscar')->assertOk()->assertDontSee("Solicitud {$revision}")->assertSee($accepted->titulo);
                $this->get("/subastas/{$auction->id_subasta}")->assertNotFound();
                $this->get($image->url)->assertNotFound();
            }

            $this->actingAs($auction->usuario)->get("/subastas/{$auction->id_subasta}")->assertOk()
                ->assertSee("Solicitud {$revision}")->assertDontSee('Hacer oferta')->assertSee('Esta solicitud no está publicada y no admite ofertas.');
            $this->get($image->url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $this->actingAs($this->admin())->get("/subastas/{$auction->id_subasta}")->assertOk()
                ->assertSee("Solicitud {$revision}")->assertDontSee('Hacer oferta');
        }
        auth()->logout();
        $this->get("/subastas/{$accepted->id_subasta}")->assertOk();
    }

    public function test_unpublished_requests_do_not_accept_bids(): void
    {
        $bidder = User::factory()->create();
        foreach (['En revisión', 'Rechazada'] as $revision) {
            $auction = $this->auction($revision);
            $this->actingAs($bidder)->post("/subastas/{$auction->id_subasta}/pujas", ['monto' => '500.00'])->assertNotFound();
            try {
                DB::transaction(fn () => DB::table('puja')->insert(['id_subasta' => $auction->id_subasta, 'id_usuario' => $bidder->id_usuario, 'monto' => 500]));
                $this->fail('La base de datos aceptó una puja en una solicitud sin publicar.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('Esta subasta todavía no está publicada.', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('puja', 0);
    }

    public function test_finalization_ignores_requests_that_were_never_published(): void
    {
        $pending = $this->auction('En revisión', ['fecha_inicio' => now()->subDays(3), 'fecha_fin' => now()->subMinute()]);
        $accepted = $this->auction('Aceptada', ['fecha_inicio' => now()->subDays(3), 'fecha_fin' => now()->subMinute()]);

        $this->assertSame(1, DB::selectOne('select finalizar_subastas() as total')->total);
        $this->assertSame('Activa', $pending->refresh()->estado_subasta);
        $this->assertSame('Finalizada', $accepted->refresh()->estado_subasta);
        $this->assertDatabaseMissing('notificacion', ['id_subasta' => $pending->id_subasta, 'titulo' => 'Subasta finalizada']);
    }

    public function test_database_enforces_review_states_and_rejection_reason(): void
    {
        $auction = $this->auction('En revisión');
        $invalid = [
            ['estado_revision' => 'Publicada', 'motivo_rechazo' => null],
            ['estado_revision' => 'Rechazada', 'motivo_rechazo' => null],
            ['estado_revision' => 'Rechazada', 'motivo_rechazo' => '   '],
            ['estado_revision' => 'Rechazada', 'motivo_rechazo' => str_repeat('x', 1001)],
            ['estado_revision' => 'Aceptada', 'motivo_rechazo' => 'Motivo que no corresponde.'],
        ];
        foreach ($invalid as $values) {
            try {
                DB::transaction(fn () => DB::table('subasta')->where('id_subasta', $auction->id_subasta)->update($values));
                $this->fail('La base de datos aceptó un estado de revisión inválido: '.json_encode($values));
            } catch (QueryException $exception) {
                $this->assertMatchesRegularExpression('/subasta_(estado_revision|motivo_rechazo)_valido/', $exception->getMessage());
            }
        }
        DB::table('subasta')->where('id_subasta', $auction->id_subasta)->update(['estado_revision' => 'Rechazada', 'motivo_rechazo' => str_repeat('x', 1000)]);
        $this->assertSame('Rechazada', $auction->refresh()->estado_revision);
    }

    public function test_reinstalling_sql_functions_keeps_requests_unpublished(): void
    {
        $this->auction('En revisión');
        $this->artisan('subastas:instalar-funciones')->assertSuccessful();

        $this->get('/buscar')->assertDontSee('Solicitud En revisión');
        $this->assertSame(0, DB::selectOne("select count(*) as total from catalogo_subastas('{}'::jsonb)")->total);
    }
}
