<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RevisionSubastasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

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
            'imagenes' => [UploadedFile::fake()->image('articulo.jpg')],
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
            ...array_diff_key($this->payload(), ['fecha_fin' => true, 'imagenes' => true]),
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

    public function test_seller_can_correct_expired_rejection_and_resubmit_with_history_and_notifications(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $auction = $this->auction('En revisión', [
            'fecha_inicio' => now()->subDays(5), 'fecha_fin' => now()->subDays(2),
        ]);
        $seller = $auction->usuario;
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Rechazada', 'motivo' => 'Necesitamos fotografías más claras.',
        ])->assertSessionHasNoErrors();
        $decision = $auction->decisiones()->sole();
        $this->assertEquals($admin->id_usuario, $decision->id_revisor);
        $this->actingAs($seller)->get("/subastas/{$auction->id_subasta}/editar")
            ->assertOk()->assertSee('Guardar y reenviar a revisión')->assertSee($decision->motivo);

        $payload = $this->payload([
            'reenviar' => '1', 'titulo' => 'Mochila corregida con fotografías nuevas',
            'imagenes' => [UploadedFile::fake()->image('corregida.jpg')],
        ]);
        $this->actingAs($seller)->put("/subastas/{$auction->id_subasta}", $payload)
            ->assertSessionHasNoErrors()->assertSessionHas('status', 'Correcciones guardadas. Tu subasta fue reenviada a revisión.');
        $auction->refresh();
        $this->assertSame('En revisión', $auction->estado_revision);
        $this->assertNull($auction->motivo_rechazo);
        $this->assertNull($auction->id_revisor);
        $this->assertNull($auction->fecha_decision);
        $this->assertTrue($auction->fecha_inicio->greaterThan(now()->subMinute()));
        $this->assertSame(1, $auction->imagenes()->count());
        $this->assertSame(1, $auction->decisiones()->count());
        $this->assertSame($decision->motivo, $auction->decisiones()->sole()->motivo);
        $this->assertSame(1, $admin->notificaciones()->where('id_subasta', $auction->id_subasta)
            ->where('titulo', 'Solicitud corregida para revisión')->count());
        $this->assertSame(1, $seller->notificaciones()->where('id_subasta', $auction->id_subasta)
            ->where('titulo', 'Solicitud reenviada a revisión')->count());
        $this->actingAs($seller)->get("/subastas/{$auction->id_subasta}")->assertOk()->assertSee($decision->motivo);
        $this->actingAs($admin)->get('/admin/subastas')->assertSee($payload['titulo']);
        $this->get('/buscar')->assertDontSee($payload['titulo']);
        $this->actingAs(User::factory()->create())->get("/subastas/{$auction->id_subasta}")->assertNotFound();

        $this->actingAs($seller)->put("/subastas/{$auction->id_subasta}", $payload)->assertSessionHasErrors();
        $this->assertSame(1, $auction->imagenes()->count());
        $duration = $auction->fecha_inicio->diffInSeconds($auction->fecha_fin);
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", ['decision' => 'Aceptada'])
            ->assertSessionHasNoErrors();
        $auction->refresh();
        $this->assertSame(2, $auction->decisiones()->count());
        $this->assertEqualsWithDelta($duration, $auction->fecha_inicio->diffInSeconds($auction->fecha_fin), 0.000001);
        $this->actingAs($seller)->get('/buscar')->assertOk()->assertSee($payload['titulo']);
        $this->actingAs(User::factory()->create())->get("/subastas/{$auction->id_subasta}")
            ->assertOk()->assertDontSee('Historial de revisiones')->assertDontSee($decision->motivo);
    }

    public function test_resubmission_requires_owner_and_valid_data_and_preserves_rejection_on_failure(): void
    {
        $admin = $this->admin();
        $auction = $this->auction('En revisión');
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Rechazada', 'motivo' => 'Corrige la descripción.',
        ])->assertSessionHasNoErrors();
        $payload = $this->payload(['reenviar' => '1']);
        $this->actingAs(User::factory()->create())->put("/subastas/{$auction->id_subasta}", $payload)->assertForbidden();
        $this->actingAs($auction->usuario)->put("/subastas/{$auction->id_subasta}", [
            ...$payload, 'fecha_fin' => now()->subDay()->toDateTimeString(),
        ])->assertSessionHasErrors('fecha_fin');
        Categoria::whereKey($payload['id_categoria'])->update(['habilitada' => false]);
        $this->actingAs($auction->usuario)->put("/subastas/{$auction->id_subasta}", $payload)->assertSessionHasErrors();
        $this->assertSame('Rechazada', $auction->refresh()->estado_revision);
        $this->assertSame('Corrige la descripción.', $auction->motivo_rechazo);
        $this->assertEquals($admin->id_usuario, $auction->id_revisor);
        $this->assertSame(1, $auction->decisiones()->count());
        $this->assertSame(0, $admin->notificaciones()->where('titulo', 'Solicitud corregida para revisión')->count());
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

    public function test_my_auctions_show_each_review_state_and_the_rejection_reason(): void
    {
        $seller = User::factory()->create();
        foreach (['En revisión', 'Aceptada', 'Rechazada'] as $revision) {
            $seller->subastas()->forceCreate([
                ...$this->auction($revision)->only(['descripcion', 'id_categoria', 'estado_articulo', 'ubicacion', 'monto_inicial', 'fecha_inicio', 'fecha_fin']),
                'titulo' => "Mi solicitud {$revision}",
                'estado_revision' => $revision,
                'motivo_rechazo' => $revision === 'Rechazada' ? 'La descripción no coincide con <b>las fotos</b>.' : null,
            ]);
        }

        $response = $this->actingAs($seller)->get('/mis-subastas')->assertOk()
            ->assertSee('Mi solicitud En revisión')->assertSee('Mi solicitud Aceptada')->assertSee('Mi solicitud Rechazada')
            ->assertSee('<span class="tag revision-tag pending">En revisión</span>', false)
            ->assertSee('<span class="tag revision-tag green">Aceptada</span>', false)
            ->assertSee('<span class="tag revision-tag danger">Rechazada</span>', false)
            ->assertSee('Motivo del rechazo:')
            ->assertSee('La descripción no coincide con &lt;b&gt;las fotos&lt;/b&gt;.', false)
            ->assertDontSee('<b>las fotos</b>', false)
            ->assertSee('Un administrador revisará tu solicitud.');
        $this->assertSame(2, substr_count($response->getContent(), 'Sin publicar'));

        $this->actingAs(User::factory()->create())->get('/mis-subastas')->assertOk()
            ->assertDontSee('Mi solicitud')->assertDontSee('La descripción no coincide');
    }

    public function test_detail_tells_owner_and_administrator_the_state_and_reason(): void
    {
        $rejected = $this->auction('Rechazada');
        $pending = $this->auction('En revisión');
        $accepted = $this->auction('Aceptada');
        $admin = $this->admin();

        foreach ([$rejected->usuario, $admin] as $viewer) {
            $this->actingAs($viewer)->get("/subastas/{$rejected->id_subasta}")->assertOk()
                ->assertSee('<span class="tag revision-tag danger">Rechazada</span>', false)
                ->assertSee('Las fotos no muestran el artículo.');
        }
        $this->actingAs($pending->usuario)->get("/subastas/{$pending->id_subasta}")->assertOk()
            ->assertSee('Esta subasta todavía no es pública: solo la ven su propietario y los administradores.')
            ->assertSee('Usado · Enviada el')->assertDontSee('<span class="tag green">Activa</span>', false);
        $this->actingAs($accepted->usuario)->get("/subastas/{$accepted->id_subasta}")->assertOk()
            ->assertDontSee('review-banner', false)
            ->assertSee('Usado · Publicada el')->assertSee('green">Activa</span>', false);
    }

    public function test_submission_notifies_the_seller_and_each_active_administrator_once(): void
    {
        $admins = [$this->admin(), $this->admin()];
        $inactive = $this->admin();
        $inactive->forceFill(['activo' => false])->save();
        $seller = User::factory()->create();

        $this->actingAs($seller)->post('/subastas', $this->payload(['titulo' => 'Mochila <script>alert(1)</script>']))->assertSessionHasNoErrors();
        $auction = Subasta::sole();

        $this->assertSame(1, $seller->notificaciones()->where('titulo', 'Solicitud en revisión')->where('id_subasta', $auction->id_subasta)->count());
        $this->assertSame(0, $seller->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->count());
        foreach ($admins as $admin) {
            $this->assertSame(1, $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->where('id_subasta', $auction->id_subasta)->count());
        }
        $this->assertSame(0, $inactive->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->count());

        $this->actingAs($seller)->get('/notificaciones')->assertOk()
            ->assertSee('Solicitud en revisión')
            ->assertSee('Enviaste «Mochila &lt;script&gt;alert(1)&lt;/script&gt;» a revisión.', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('href="'.route('subastas.show', $auction).'">Ver solicitud', false)
            ->assertDontSee('Nueva solicitud de publicación');
        $this->actingAs($admins[0])->get('/notificaciones')->assertOk()
            ->assertSee('Nueva solicitud de publicación')
            ->assertSee('href="'.route('subastas.show', $auction).'">Ver solicitud', false)
            ->assertDontSee('Solicitud en revisión');
        // El acceso del aviso funciona para su destinatario.
        $this->get(route('subastas.show', $auction))->assertOk()->assertSee('Mochila &lt;script&gt;', false);
    }

    public function test_administrator_cannot_submit_or_generate_publication_notices(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->post('/subastas', $this->payload())->assertForbidden();

        $this->assertSame([], $admin->notificaciones()->where('titulo', '<>', '¡Bienvenido a SubastaYA!')->pluck('titulo')->all());
    }

    public function test_only_each_recipient_can_read_or_mark_their_notices(): void
    {
        $admin = $this->admin();
        $otherAdmin = $this->admin();
        $seller = User::factory()->create();
        $stranger = User::factory()->create();
        $this->actingAs($seller)->post('/subastas', $this->payload())->assertSessionHasNoErrors();
        $sellerNotice = $seller->notificaciones()->where('titulo', 'Solicitud en revisión')->sole();
        $adminNotice = $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->sole();

        foreach ([[$stranger, $sellerNotice], [$stranger, $adminNotice], [$admin, $sellerNotice], [$seller, $adminNotice], [$otherAdmin, $adminNotice]] as [$user, $notice]) {
            $this->actingAs($user)->patch("/notificaciones/{$notice->id_notificacion}")->assertForbidden();
            $this->delete("/notificaciones/{$notice->id_notificacion}")->assertForbidden();
        }
        $this->actingAs($stranger)->get('/notificaciones')->assertDontSee('Solicitud en revisión')->assertDontSee('Nueva solicitud de publicación');
        $this->assertFalse($sellerNotice->refresh()->leido);
        $this->assertFalse($adminNotice->refresh()->leido);

        $this->actingAs($admin)->patch("/notificaciones/{$adminNotice->id_notificacion}")->assertRedirect();
        $this->actingAs($seller)->patch("/notificaciones/{$sellerNotice->id_notificacion}")->assertRedirect();
        $this->assertTrue($adminNotice->refresh()->leido);
        $this->assertTrue($sellerNotice->refresh()->leido);
        $this->assertFalse($otherAdmin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->sole()->leido);
    }

    public function test_administrator_navigation_includes_notifications(): void
    {
        $this->actingAs($this->admin())->get('/admin/usuarios')->assertOk()
            ->assertSee('href="'.route('notificaciones.index').'"', false);
    }

    public function test_invalid_requests_are_not_registered_or_notified(): void
    {
        $admin = $this->admin();
        $seller = User::factory()->create();
        $this->actingAs($seller)->post('/subastas', $this->payload(['titulo' => '', 'latitud' => '95']))->assertSessionHasErrors(['titulo', 'latitud']);
        $this->post('/subastas', $this->payload(['imagenes' => [UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')]]))->assertSessionHasErrors('imagenes.0');

        $this->assertDatabaseCount('subasta', 0);
        $this->assertSame(0, $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->count());
        $this->assertSame(0, $seller->notificaciones()->where('titulo', 'Solicitud en revisión')->count());
    }

    public function test_demo_data_includes_a_pending_and_a_rejected_request(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);
        $demo = User::where('correo', 'demo@subastaya.test')->sole();
        $admin = User::where('correo', 'admin@subastaya.test')->sole();

        $this->assertSame(['En revisión' => 1, 'Rechazada' => 1], $demo->subastas()->where('estado_revision', '<>', 'Aceptada')
            ->pluck('estado_revision')->countBy()->sortKeys()->all());
        $this->assertSame(1, $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->count());
        $this->get('/buscar')->assertDontSee('Escritorio de madera con cajones')->assertDontSee('Reloj de pulsera de colección');
        $this->actingAs($demo)->get('/mis-subastas')->assertOk()
            ->assertSee('Escritorio de madera con cajones')->assertSee('La descripción no indica la marca ni el modelo.');
    }

    public function test_editing_an_accepted_auction_sends_it_back_to_review(): void
    {
        Storage::fake('public');
        $admin = $this->admin();
        $auction = $this->auction('Aceptada');
        $this->actingAs($auction->usuario)->get("/subastas/{$auction->id_subasta}/editar")->assertOk()
            ->assertSee('Al guardar, la subasta vuelve a revisión');

        $this->put("/subastas/{$auction->id_subasta}", $this->payload([
            'titulo' => 'Contenido sin revisar',
            'imagenes' => [UploadedFile::fake()->image('nueva.jpg')],
        ]))->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Subasta actualizada. Quedó En revisión hasta que un administrador la acepte.');

        $this->assertSame('En revisión', $auction->refresh()->estado_revision);
        $this->assertCount(1, $auction->imagenes);
        $this->assertSame(1, $auction->usuario->notificaciones()->where('titulo', 'Solicitud en revisión')->count());
        $this->assertSame(1, $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')
            ->where('contenido', 'like', '%Contenido sin revisar%')->count());
        auth()->logout();
        $this->get('/buscar')->assertDontSee('Contenido sin revisar');
        $this->get("/subastas/{$auction->id_subasta}")->assertNotFound();
    }

    public function test_editing_a_reviewed_auction_preserves_history_and_requires_another_approval(): void
    {
        $admin = $this->admin();
        $auction = $this->auction('En revisión');
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Aceptada',
        ])->assertSessionHasNoErrors();
        $this->assertSame($admin->id_usuario, $auction->refresh()->id_revisor);
        $avisosAntes = $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->count();

        $this->actingAs($auction->usuario)->put("/subastas/{$auction->id_subasta}", $this->payload())
            ->assertSessionHasNoErrors();
        $this->assertSame('En revisión', $auction->refresh()->estado_revision);
        $this->assertNull($auction->id_revisor);
        $this->assertNull($auction->fecha_decision);
        $this->assertSame($admin->id_usuario, $auction->decisiones()->sole()->id_revisor);
        $this->assertSame($avisosAntes + 1, $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->count());

        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Aceptada',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Aceptada', $auction->refresh()->estado_revision);
        $this->assertSame(2, $auction->decisiones()->count());
    }

    public function test_editing_a_rejected_auction_resubmits_it_without_the_old_reason(): void
    {
        $auction = $this->auction('Rechazada');
        $this->actingAs($auction->usuario)->put("/subastas/{$auction->id_subasta}", $this->payload(['titulo' => 'Corregida por el vendedor', 'reenviar' => '1']))
            ->assertSessionHasNoErrors();

        $auction->refresh();
        $this->assertSame('En revisión', $auction->estado_revision);
        $this->assertNull($auction->motivo_rechazo);
        $this->get('/mis-subastas')->assertSee('Corregida por el vendedor')->assertDontSee('Las fotos no muestran el artículo.');
    }

    public function test_changing_only_the_photos_of_an_accepted_auction_sends_it_back_to_review(): void
    {
        Storage::fake('public');
        $auction = $this->auction('Aceptada');
        Storage::disk('public')->put("subastas/{$auction->id_subasta}/foto.jpg", 'imagen');
        $image = $auction->imagenes()->create(['ruta' => "subastas/{$auction->id_subasta}/foto.jpg", 'tamano' => 6]);

        DB::select('select eliminar_imagen(?,?)', [$auction->id_usuario, $image->id_imagen]);
        $this->assertSame('En revisión', $auction->refresh()->estado_revision);

        DB::table('subasta')->where('id_subasta', $auction->id_subasta)->update(['estado_revision' => 'Aceptada']);
        DB::select('select * from guardar_imagen(?,?,?,?)', [$auction->id_usuario, $auction->id_subasta, 'subastas/x.jpg', 10]);
        $this->assertSame('En revisión', $auction->refresh()->estado_revision);
    }

    public function test_editing_a_request_in_review_does_not_repeat_the_notices(): void
    {
        $admin = $this->admin();
        $auction = $this->auction('En revisión');
        $before = DB::table('notificacion')->count();
        $this->actingAs($auction->usuario)->put("/subastas/{$auction->id_subasta}", $this->payload())->assertSessionHasNoErrors();

        $this->assertSame('En revisión', $auction->refresh()->estado_revision);
        $this->assertSame($before, DB::table('notificacion')->count());
        $this->assertSame(1, $admin->notificaciones()->where('titulo', 'Nueva solicitud de publicación')->count());
    }

    public function test_reinstalling_sql_functions_keeps_editing_behind_review(): void
    {
        $this->artisan('subastas:instalar-funciones')->assertSuccessful();
        $auction = $this->auction('Aceptada');
        $this->actingAs($auction->usuario)->put("/subastas/{$auction->id_subasta}", $this->payload())->assertSessionHasNoErrors();

        $this->assertSame('En revisión', $auction->refresh()->estado_revision);
    }

    public function test_reinstalling_sql_functions_keeps_requests_unpublished(): void
    {
        $this->auction('En revisión');
        $this->artisan('subastas:instalar-funciones')->assertSuccessful();

        $this->get('/buscar')->assertDontSee('Solicitud En revisión');
        $this->assertSame(0, DB::selectOne("select count(*) as total from catalogo_subastas('{}'::jsonb)")->total);
    }
}
