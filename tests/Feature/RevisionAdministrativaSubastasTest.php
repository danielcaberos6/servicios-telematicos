<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RevisionAdministrativaSubastasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(bool $activo = true): User
    {
        $user = User::factory()->create(['activo' => $activo]);
        DB::table('usuario_rol')->insert([
            'id_usuario' => $user->id_usuario,
            'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol'),
        ]);

        return $user;
    }

    private function seller(): User
    {
        return User::factory()->create();
    }

    private function auction(string $revision = 'En revisión', array $overrides = []): Subasta
    {
        $seller = $this->seller();
        $categoria = Categoria::firstOrCreate(['nombre_categoria' => 'Deporte']);

        return $seller->subastas()->forceCreate(array_replace([
            'titulo' => 'Mochila de montaña 50L',
            'descripcion' => 'Mochila con soporte lumbar y protector impermeable.',
            'id_categoria' => $categoria->id_categoria,
            'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz, Plaza Murillo',
            'latitud' => '-16.495500',
            'longitud' => '-68.133600',
            'monto_inicial' => '250.00',
            'fecha_inicio' => now()->subHour(),
            'fecha_fin' => now()->addDays(5),
            'estado_subasta' => 'Activa',
            'estado_revision' => $revision,
            'motivo_rechazo' => $revision === 'Rechazada' ? 'Fotos poco claras.' : null,
        ], $overrides))->refresh();
    }

    public function test_only_active_administrators_can_access_auction_review(): void
    {
        $auction = $this->auction('En revisión');
        $user = User::factory()->create();
        $inactiveAdmin = $this->admin(false);
        $admin = $this->admin(true);

        // Invitado es redirigido a login
        $this->get('/admin/subastas')->assertRedirect('/iniciar-sesion');
        $this->get("/admin/subastas/{$auction->id_subasta}")->assertRedirect('/iniciar-sesion');
        $this->patch("/admin/subastas/{$auction->id_subasta}", ['decision' => 'Aceptada'])->assertRedirect('/iniciar-sesion');

        // Usuario común es redirigido a inicio con error de acceso (o 403 en JSON)
        $this->actingAs($user)->get('/admin/subastas')->assertRedirect('/')->assertSessionHasErrors('acceso');
        $this->actingAs($user)->get("/admin/subastas/{$auction->id_subasta}")->assertRedirect('/')->assertSessionHasErrors('acceso');
        $this->actingAs($user)->patch("/admin/subastas/{$auction->id_subasta}", ['decision' => 'Aceptada'])->assertRedirect('/')->assertSessionHasErrors('acceso');
        $this->actingAs($user)->getJson('/admin/subastas')->assertForbidden();

        // Administrador inactivo es redirigido a login
        $this->actingAs($inactiveAdmin)->get('/admin/subastas')->assertRedirect('/iniciar-sesion');

        // Administrador activo accede correctamente
        $this->actingAs($admin)->get('/admin/subastas')->assertOk()->assertSee('Revisión de subastas');
        $this->actingAs($admin)->get("/admin/subastas/{$auction->id_subasta}")->assertOk()->assertSee('Mochila de montaña 50L');
    }

    public function test_administrator_can_list_and_filter_auctions_by_state_and_search(): void
    {
        $admin = $this->admin();
        $pending = $this->auction('En revisión', ['titulo' => 'Cámara Reflex Canon']);
        $accepted = $this->auction('Aceptada', ['titulo' => 'Bicicleta Trek']);
        $rejected = $this->auction('Rechazada', ['titulo' => 'Guitarra Eléctrica']);

        // Filtro por defecto (En revisión)
        $this->actingAs($admin)->get('/admin/subastas')
            ->assertOk()
            ->assertSee('Cámara Reflex Canon')
            ->assertDontSee('Bicicleta Trek')
            ->assertDontSee('Guitarra Eléctrica');

        // Filtro Aceptada
        $this->actingAs($admin)->get('/admin/subastas?estado=Aceptada')
            ->assertOk()
            ->assertSee('Bicicleta Trek')
            ->assertDontSee('Cámara Reflex Canon')
            ->assertDontSee('Guitarra Eléctrica');

        // Filtro Rechazada
        $this->actingAs($admin)->get('/admin/subastas?estado=Rechazada')
            ->assertOk()
            ->assertSee('Guitarra Eléctrica')
            ->assertDontSee('Cámara Reflex Canon')
            ->assertDontSee('Bicicleta Trek');

        // Filtro Todos
        $this->actingAs($admin)->get('/admin/subastas?estado=todos')
            ->assertOk()
            ->assertSee('Cámara Reflex Canon')
            ->assertSee('Bicicleta Trek')
            ->assertSee('Guitarra Eléctrica');

        // Búsqueda por texto
        $this->actingAs($admin)->get('/admin/subastas?estado=todos&q=Reflex')
            ->assertOk()
            ->assertSee('Cámara Reflex Canon')
            ->assertDontSee('Bicicleta Trek');
    }

    public function test_administrator_can_accept_auction_and_it_becomes_published(): void
    {
        $admin = $this->admin();
        $auction = $this->auction('En revisión', ['titulo' => 'Laptop Gamer Asus']);
        $seller = $auction->usuario;

        // Antes de aceptar, no aparece en catálogo
        $this->get('/buscar')->assertDontSee('Laptop Gamer Asus');

        // El administrador la acepta
        $response = $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Aceptada',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/admin/subastas/{$auction->id_subasta}");
        $response->assertSessionHas('status', 'La subasta fue aceptada y publicada correctamente.');

        // Ahora está aceptada en BD
        $this->assertSame('Aceptada', $auction->refresh()->estado_revision);
        $this->assertNull($auction->motivo_rechazo);

        // Ahora es visible en catálogo
        $this->get('/buscar')->assertSee('Laptop Gamer Asus');

        // Notificación al vendedor
        $notif = $seller->notificaciones()->where('id_subasta', $auction->id_subasta)->where('titulo', 'Subasta publicada')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString('Laptop Gamer Asus', $notif->contenido);
    }

    public function test_administrator_can_reject_auction_with_valid_reason(): void
    {
        $admin = $this->admin();
        $auction = $this->auction('En revisión', ['titulo' => 'Consola PlayStation 5']);
        $seller = $auction->usuario;

        // Rechazo sin motivo falla con error de validación
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Rechazada',
        ])->assertSessionHasErrors('motivo');

        // Rechazo con motivo mayor a 1000 caracteres falla
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Rechazada',
            'motivo' => str_repeat('a', 1001),
        ])->assertSessionHasErrors('motivo');

        // Rechazo con motivo válido
        $motivo = 'Las imágenes no son claras y no corresponden a fotos reales del artículo.';
        $response = $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Rechazada',
            'motivo' => $motivo,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect("/admin/subastas/{$auction->id_subasta}");
        $response->assertSessionHas('status', 'La subasta fue rechazada y se notificó al vendedor con el motivo.');

        // Estado en base de datos
        $auction->refresh();
        $this->assertSame('Rechazada', $auction->estado_revision);
        $this->assertSame($motivo, $auction->motivo_rechazo);

        // No aparece en catálogo público
        $this->get('/buscar')->assertDontSee('Consola PlayStation 5');

        // Vendedor recibe notificación con el motivo
        $notif = $seller->notificaciones()->where('id_subasta', $auction->id_subasta)->where('titulo', 'Subasta rechazada')->first();
        $this->assertNotNull($notif);
        $this->assertStringContainsString($motivo, $notif->contenido);

        // Vendedor puede ver su subasta rechazada y el motivo en mis-subastas y en detalle
        $this->actingAs($seller)->get('/mis-subastas')->assertOk()
            ->assertSee('Consola PlayStation 5')
            ->assertSee($motivo);
    }

    public function test_approval_preserves_full_duration_even_when_reference_deadline_has_passed(): void
    {
        $admin = $this->admin();
        foreach ([now()->subDays(5), now()->subHour()] as $inicio) {
            $auction = $this->auction('En revisión', [
                'fecha_inicio' => $inicio,
                'fecha_fin' => $inicio->copy()->addDays(3),
            ]);
            $duration = $auction->fecha_fin->getTimestamp() - $auction->fecha_inicio->getTimestamp();
            $this->assertSame('En revisión', $auction->estado);
            $before = DB::selectOne('select clock_timestamp() as instante')->instante;

            $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
                'decision' => 'Aceptada',
            ])->assertSessionHasNoErrors();

            $auction->refresh();
            $this->assertTrue($auction->fecha_inicio->greaterThanOrEqualTo($before));
            $this->assertSame($duration, $auction->fecha_fin->getTimestamp() - $auction->fecha_inicio->getTimestamp());
            $this->assertTrue($auction->fecha_fin->isFuture());
            $this->get('/buscar')->assertSee($auction->titulo);
            $this->assertSame(1, $auction->usuario->notificaciones()
                ->where('id_subasta', $auction->id_subasta)->where('titulo', 'Subasta publicada')->count());

            $start = $auction->fecha_inicio->toISOString();
            $end = $auction->fecha_fin->toISOString();
            $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
                'decision' => 'Aceptada',
            ])->assertSessionHasErrors('decision');
            $this->assertSame($start, $auction->refresh()->fecha_inicio->toISOString());
            $this->assertSame($end, $auction->fecha_fin->toISOString());
        }
    }

    public function test_rejection_preserves_reference_dates(): void
    {
        $admin = $this->admin();
        $auction = $this->auction();
        $start = $auction->fecha_inicio->toISOString();
        $end = $auction->fecha_fin->toISOString();
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Rechazada', 'motivo' => 'Fotos poco claras.',
        ])->assertSessionHasNoErrors();
        $this->assertSame($start, $auction->refresh()->fecha_inicio->toISOString());
        $this->assertSame($end, $auction->fecha_fin->toISOString());
    }

    public function test_cannot_resolve_already_resolved_auction(): void
    {
        $admin = $this->admin();
        $auction = $this->auction('Aceptada');

        // Intentar rechazar una subasta ya aceptada
        $this->actingAs($admin)->patch("/admin/subastas/{$auction->id_subasta}", [
            'decision' => 'Rechazada',
            'motivo' => 'Ya no está permitida',
        ])->assertSessionHasErrors('decision');

        $this->assertSame('Aceptada', $auction->refresh()->estado_revision);
    }

    public function test_administrator_navigation_includes_subastas_link(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get('/admin/subastas')->assertOk()
            ->assertSee('href="'.route('admin.subastas.index').'"', false)
            ->assertSee('Subastas');
    }
}
