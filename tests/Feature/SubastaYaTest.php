<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Notificacion;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SubastaYaTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'titulo' => 'Cámara fotográfica de prueba',
            'descripcion' => 'Artículo en excelente estado con todos sus accesorios originales.',
            'id_categoria' => Categoria::firstOrCreate(['nombre_categoria' => 'Electrónica e informática'])->id_categoria,
            'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz, Plaza Abaroa',
            'monto_inicial' => '100.50',
            'fecha_fin' => now()->addDays(3)->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    private function auction(?User $user = null, array $overrides = []): Subasta
    {
        return ($user ?? User::factory()->create())->subastas()->create([
            ...$this->payload(), 'fecha_inicio' => now()->subDay(), ...$overrides,
        ])->refresh();
    }

    public function test_guests_can_browse_but_cannot_create_or_manage(): void
    {
        $this->get('/')->assertOk()->assertDontSee('Crear subasta')->assertSee('Iniciar sesión');
        foreach (['/subastas/crear', '/mis-subastas', '/perfil', '/notificaciones'] as $url) {
            $this->get($url)->assertRedirect('/iniciar-sesion');
        }
        $this->post('/subastas', $this->payload())->assertRedirect('/iniciar-sesion');
        $this->assertDatabaseCount('subasta', 0);
        $this->actingAs(User::factory()->create())->get('/')->assertSee('Crear subasta');
    }

    public function test_registration_login_and_logout_use_original_usuario_table(): void
    {
        $this->post('/registro', ['nombre' => 'Ana Prueba', 'correo' => 'ANA@example.test', 'password' => 'ClaveSegura123', 'password_confirmation' => 'ClaveSegura123'])->assertRedirect('/');
        $user = User::where('correo', 'ana@example.test')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertTrue(Hash::check('ClaveSegura123', $user->contrasena));
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $user->id_usuario, 'titulo' => '¡Bienvenido a SubastaYA!']);
        $this->post('/cerrar-sesion')->assertRedirect('/');
        $this->assertGuest();
        $this->post('/iniciar-sesion', ['correo' => 'ANA@example.test', 'password' => 'incorrecta'])->assertSessionHasErrors('correo');
        $this->post('/iniciar-sesion', ['correo' => 'ANA@example.test', 'password' => 'ClaveSegura123'])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/iniciar-sesion', ['correo' => $user->correo, 'password' => 'incorrecta'])->assertSessionHasErrors('correo');
        }
        $this->post('/iniciar-sesion', ['correo' => $user->correo, 'password' => 'password'])->assertSessionHasErrors('correo');
        $this->assertGuest();
    }

    public function test_create_saves_photos_ownership_notification_and_server_start_date(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $this->actingAs($owner)->post('/subastas', $this->payload([
            'id_usuario' => User::factory()->create()->id_usuario,
            'fecha_inicio' => now()->addMonth()->toDateTimeString(),
            'estado_subasta' => 'Finalizada',
            'imagenes' => [UploadedFile::fake()->image('camara.jpg')],
        ]))->assertSessionHasNoErrors()->assertRedirect();
        $auction = Subasta::with('imagenes')->firstOrFail();
        $this->assertSame($owner->id_usuario, $auction->id_usuario);
        $this->assertSame('Activa', $auction->estado);
        $this->assertTrue($auction->fecha_inicio->isToday());
        Storage::disk('public')->assertExists($auction->imagenes->first()->ruta);
        $this->get($auction->imagenes->first()->url)->assertOk();
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $owner->id_usuario, 'titulo' => 'Subasta publicada']);
        $this->get('/buscar')->assertSee($auction->titulo);
    }

    public function test_invalid_dates_amounts_categories_and_uploads_are_rejected(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create())->post('/subastas', $this->payload([
            'fecha_fin' => now()->subDay()->toDateTimeString(), 'monto_inicial' => '-1', 'id_categoria' => 99999,
            'imagenes' => [UploadedFile::fake()->create('script.php', 1, 'text/plain')],
        ]))->assertSessionHasErrors(['fecha_fin', 'monto_inicial', 'id_categoria', 'imagenes.0']);
        $this->post('/subastas', $this->payload(['monto_inicial' => '1.001', 'imagenes' => [UploadedFile::fake()->image('large.png')->size(6000)]]))->assertSessionHasErrors(['monto_inicial', 'imagenes.0']);
        $this->assertDatabaseCount('subasta', 0);
    }

    public function test_catalog_excludes_expired_future_and_cancelled_auctions(): void
    {
        $active = $this->auction(null, ['titulo' => 'Artículo activo visible']);
        $this->auction(null, ['titulo' => 'Artículo vencido oculto', 'fecha_fin' => now()->subHour()]);
        $this->auction(null, ['titulo' => 'Artículo futuro oculto', 'fecha_inicio' => now()->addDay()]);
        $cancelled = $this->auction(null, ['titulo' => 'Artículo cancelado oculto']);
        $cancelled->estado_subasta = 'Cancelada';
        $cancelled->save();
        $this->get('/buscar')->assertOk()->assertSee($active->titulo)->assertDontSee('Artículo vencido oculto')->assertDontSee('Artículo futuro oculto')->assertDontSee('Artículo cancelado oculto');
    }

    public function test_all_catalog_filters_can_be_combined_and_price_sort_works(): void
    {
        $match = $this->auction(null, ['titulo' => 'Cámara única buscada', 'fecha_inicio' => now()->subDays(2), 'monto_inicial' => 350]);
        $other = $this->auction(null, ['titulo' => 'Otro artículo visible', 'monto_inicial' => 90]);
        $this->auction(null, ['titulo' => 'Cámara fuera de precio', 'monto_inicial' => 900]);
        $params = ['q' => 'cámara', 'categoria' => $match->id_categoria, 'estado' => 'Usado', 'ubicacion' => 'la paz', 'desde' => now()->subDays(3)->toDateString(), 'hasta' => now()->subDay()->toDateString(), 'min' => 300, 'max' => 400];
        $this->get('/buscar?'.http_build_query($params))->assertOk()->assertSee($match->titulo)->assertDontSee($other->titulo)->assertDontSee('Cámara fuera de precio');
        $this->get('/buscar?orden=precio_asc')->assertSeeInOrder([$other->titulo, $match->titulo, 'Cámara fuera de precio']);
        $this->getJson('/buscar?min=500&max=100')->assertUnprocessable()->assertJsonValidationErrors('max');
        $this->getJson('/buscar?desde=2026-10-10&hasta=2026-10-01')->assertUnprocessable()->assertJsonValidationErrors('hasta');
        $this->get('/buscar?q=sincoincidencias')->assertSee('No encontramos coincidencias');
    }

    public function test_publication_date_filter_includes_last_seconds_of_local_day(): void
    {
        $day = now()->subDay()->startOfDay();
        $match = $this->auction(null, ['titulo' => 'Publicación al final del día', 'fecha_inicio' => $day->copy()->endOfDay()]);
        $this->get('/buscar?'.http_build_query(['desde' => $day->toDateString(), 'hasta' => $day->toDateString()]))->assertSee($match->titulo);
    }

    public function test_owner_can_edit_remove_photos_and_delete_but_other_users_cannot(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $auction = $this->auction($owner);
        Storage::disk('public')->put('subastas/test.jpg', 'test');
        $image = $auction->imagenes()->create(['ruta' => 'subastas/test.jpg', 'tamano' => 4]);
        $this->actingAs(User::factory()->create())->get('/subastas/'.$auction->id_subasta.'/editar')->assertForbidden();
        $this->put('/subastas/'.$auction->id_subasta, $this->payload())->assertForbidden();
        $this->delete('/subastas/'.$auction->id_subasta)->assertForbidden();
        $this->actingAs($owner)->get('/subastas/'.$auction->id_subasta.'/editar')->assertOk();
        $this->put('/subastas/'.$auction->id_subasta, $this->payload(['titulo' => 'Título modificado', 'eliminar_imagenes' => [$image->id_imagen]]))->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing('subastas/test.jpg');
        $this->assertDatabaseHas('subasta', ['id_subasta' => $auction->id_subasta, 'titulo' => 'Título modificado']);
        $this->delete('/subastas/'.$auction->id_subasta)->assertRedirect('/mis-subastas');
        $this->assertDatabaseMissing('subasta', ['id_subasta' => $auction->id_subasta]);
    }

    public function test_bids_reject_low_amounts_self_bidding_and_lock_auction_edits(): void
    {
        $owner = User::factory()->create();
        $auction = $this->auction($owner);
        $bidder = User::factory()->create();
        $this->actingAs($owner)->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 150])->assertForbidden();
        $this->actingAs($bidder)->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 90])->assertSessionHasErrors('monto');
        $this->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => '100.50'])->assertSessionHasNoErrors();
        $this->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => '100.50'])->assertSessionHasErrors('monto');
        $this->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => '100.51'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('puja', 2);
        $this->actingAs($owner)->put('/subastas/'.$auction->id_subasta, $this->payload())->assertSessionHasErrors('subasta');
        $this->delete('/subastas/'.$auction->id_subasta)->assertSessionHasErrors('subasta');
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $owner->id_usuario, 'titulo' => 'Nueva oferta']);
    }

    public function test_database_itself_rejects_an_invalid_direct_bid(): void
    {
        $auction = $this->auction();
        $this->expectException(QueryException::class);
        DB::table('puja')->insert(['id_subasta' => $auction->id_subasta, 'id_usuario' => $auction->id_usuario, 'monto' => 1000]);
    }

    public function test_outbid_notifications_and_expired_bid_rejection(): void
    {
        $auction = $this->auction();
        $one = User::factory()->create();
        $two = User::factory()->create();
        $this->actingAs($one)->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 200])->assertSessionHasNoErrors();
        $this->actingAs($two)->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 210])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('notificacion', ['id_usuario' => $one->id_usuario, 'titulo' => 'Superaron tu oferta']);
        $expired = $this->auction(null, ['fecha_fin' => now()->subHour()]);
        $this->post('/subastas/'.$expired->id_subasta.'/pujas', ['monto' => 250])->assertSessionHasErrors('monto');
    }

    public function test_finalize_is_idempotent_and_notifies_the_owner(): void
    {
        $auction = $this->auction(null, ['fecha_fin' => now()->subHour()]);
        $this->artisan('subastas:finalizar')->assertSuccessful();
        $this->artisan('subastas:finalizar')->assertSuccessful();
        $this->assertSame('Finalizada', $auction->fresh()->estado_subasta);
        $this->assertSame(1, Notificacion::where('id_usuario', $auction->id_usuario)->where('titulo', 'Subasta finalizada')->count());
    }

    public function test_notification_actions_are_scoped_to_the_current_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $owner->notificaciones()->create(['titulo' => 'Privada', 'contenido' => 'Solo para su destinatario']);
        $this->actingAs($other)->get('/notificaciones')->assertDontSee('Solo para su destinatario');
        $this->patch('/notificaciones/'.$notification->id_notificacion)->assertForbidden();
        $this->delete('/notificaciones/'.$notification->id_notificacion)->assertForbidden();
        $this->actingAs($owner)->patch('/notificaciones/leer-todas')->assertRedirect();
        $this->assertTrue($notification->fresh()->leido);
        $this->get('/notificaciones?solo_no_leidas=1')->assertDontSee('Solo para su destinatario');
        $this->delete('/notificaciones/'.$notification->id_notificacion)->assertRedirect();
        $this->assertDatabaseMissing('notificacion', ['id_notificacion' => $notification->id_notificacion]);
    }

    public function test_profile_saves_avatar_and_requires_current_password_for_sensitive_changes(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $data = ['nombre' => 'Nuevo nombre', 'correo' => $user->correo, 'ciudad' => 'Sucre'];
        $this->actingAs($user)->put('/perfil', [...$data, 'foto' => UploadedFile::fake()->image('perfil.png')])->assertSessionHasNoErrors();
        $path = $user->fresh()->imagen->ruta;
        Storage::disk('public')->assertExists($path);
        $this->get('/perfil')->assertOk()->assertSee('Nuevo nombre');
        $this->put('/perfil', [...$data, 'correo' => 'otro@example.test'])->assertSessionHasErrors('current_password');
        $this->put('/perfil', [...$data, 'current_password' => 'password', 'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123', 'quitar_foto' => 1])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($path);
        $this->assertTrue(Hash::check('NuevaClave123', $user->fresh()->contrasena));
    }

    public function test_auction_text_is_escaped_when_displayed(): void
    {
        $auction = $this->auction(null, ['titulo' => '<script>alert(1)</script>', 'descripcion' => '<img src=x onerror=alert(1)>']);
        $this->get('/subastas/'.$auction->id_subasta)->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
    }

    public function test_finalization_notifies_winner_once_and_contact_is_private(): void
    {
        $owner = User::factory()->create();
        $winner = User::factory()->create();
        $auction = $this->auction($owner, ['fecha_fin' => now()->addSeconds(2)]);
        $this->actingAs($winner)->post('/subastas/'.$auction->id_subasta.'/pujas', ['monto' => 150])->assertSessionHasNoErrors();
        // PostgreSQL utiliza el reloj real; adelantar el reloj PHP no afectaría al trigger.
        DB::statement('select pg_sleep(2.1)');
        $this->artisan('subastas:finalizar')->assertSuccessful();
        $this->artisan('subastas:finalizar')->assertSuccessful();
        $this->assertSame(1, Notificacion::where('id_usuario', $winner->id_usuario)->where('titulo', '¡Ganaste una subasta!')->count());
        $this->get('/subastas/'.$auction->id_subasta)->assertSee($owner->correo);
        $this->actingAs($owner)->get('/subastas/'.$auction->id_subasta)->assertSee($winner->correo);
        $this->actingAs(User::factory()->create())->get('/subastas/'.$auction->id_subasta)->assertDontSee($owner->correo)->assertDontSee($winner->correo);
    }

    public function test_image_ownership_and_combined_upload_limit_are_enforced(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $auction = $this->auction($owner);
        $other = $this->auction();
        $foreign = $other->imagenes()->create(['ruta' => 'otra.jpg', 'tamano' => 1]);
        $this->actingAs($owner)->put('/subastas/'.$auction->id_subasta, $this->payload(['eliminar_imagenes' => [$foreign->id_imagen]]))->assertForbidden();
        for ($i = 0; $i < 5; $i++) {
            $auction->imagenes()->create(['ruta' => 'propia'.$i.'.jpg', 'tamano' => 1]);
        }
        $this->put('/subastas/'.$auction->id_subasta, $this->payload(['imagenes' => [UploadedFile::fake()->image('sexta.jpg')]]))->assertSessionHasErrors('imagenes');
        $this->assertSame(5, $auction->imagenes()->count());
    }

    public function test_catalog_pagination_retains_filters(): void
    {
        $owner = User::factory()->create();
        for ($i = 1; $i <= 13; $i++) {
            $this->auction($owner, ['titulo' => 'Artículo paginado '.$i]);
        }
        $first = $this->get('/buscar?estado=Usado&min=50')->assertOk();
        $first->assertSee('Página 1 de 2');
        $first->assertSee('estado=Usado&amp;min=50&amp;page=2', false);
        $this->get('/buscar?estado=Usado&min=50&page=2')->assertOk()->assertSee('Página 2 de 2');
    }
}
