<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotosSubastasTest extends TestCase
{
    use RefreshDatabase;

    private function datos(): array
    {
        return ['titulo' => 'Cámara con accesorios', 'descripcion' => 'Cámara en buen estado con todos sus accesorios.',
            'id_categoria' => Categoria::firstOrFail()->id_categoria, 'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'monto_inicial' => '100.00',
            'fecha_fin' => now()->addDays(3)->format('Y-m-d\TH:i')];
    }

    private function fotos(int $cantidad): array
    {
        return array_map(fn ($numero) => UploadedFile::fake()->image("foto-{$numero}.jpg"), range(1, $cantidad));
    }

    private function sesion(): User
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user);

        return $user;
    }

    public function test_missing_empty_and_more_than_five_photos_do_not_create_requests_or_notices(): void
    {
        $this->sesion();
        $avisos = DB::table('notificacion')->count();
        foreach ([[], ['imagenes' => []], ['imagenes' => $this->fotos(6)]] as $fotos) {
            $this->post('/subastas', [...$this->datos(), ...$fotos])->assertSessionHasErrors('imagenes');
            $this->assertDatabaseCount('subasta', 0);
            $this->assertSame($avisos, DB::table('notificacion')->count());
            $this->assertSame([], Storage::disk('public')->allFiles());
        }
    }

    public function test_one_and_five_photos_create_review_requests_and_store_every_photo(): void
    {
        $this->sesion();
        foreach ([1, 5] as $cantidad) {
            $this->post('/subastas', [...$this->datos(), 'imagenes' => $this->fotos($cantidad)])->assertSessionHasNoErrors()->assertRedirect();
            $subasta = Subasta::latest('id_subasta')->firstOrFail();
            $this->assertSame('En revisión', $subasta->estado_revision);
            $this->assertSame($cantidad, $subasta->imagenes()->count());
            foreach ($subasta->imagenes as $foto) {
                Storage::disk('public')->assertExists($foto->ruta);
            }
        }
    }

    public function test_existing_photos_can_be_kept_but_the_last_one_cannot_be_removed_without_replacement(): void
    {
        $this->sesion();
        $this->post('/subastas', [...$this->datos(), 'imagenes' => $this->fotos(1)])->assertSessionHasNoErrors();
        $subasta = Subasta::sole();
        $foto = $subasta->imagenes()->sole();
        $this->put('/subastas/'.$subasta->id_subasta, [...$this->datos(), 'imagenes' => []])->assertSessionHasNoErrors();
        $avisos = DB::table('notificacion')->count();
        $this->put('/subastas/'.$subasta->id_subasta, [...$this->datos(), 'eliminar_imagenes' => [$foto->id_imagen]])->assertSessionHasErrors('imagenes');
        $this->assertSame(1, $subasta->imagenes()->count());
        Storage::disk('public')->assertExists($foto->ruta);
        $this->assertSame($avisos, DB::table('notificacion')->count());
    }

    public function test_the_five_photo_limit_counts_retained_and_new_photos_and_allows_replacement(): void
    {
        $this->sesion();
        $this->post('/subastas', [...$this->datos(), 'imagenes' => $this->fotos(5)])->assertSessionHasNoErrors();
        $subasta = Subasta::sole();
        $foto = $subasta->imagenes()->firstOrFail();
        $this->put('/subastas/'.$subasta->id_subasta, [...$this->datos(), 'imagenes' => $this->fotos(1)])->assertSessionHasErrors('imagenes');
        $this->assertSame(5, $subasta->imagenes()->count());
        $this->put('/subastas/'.$subasta->id_subasta, [...$this->datos(), 'eliminar_imagenes' => [$foto->id_imagen], 'imagenes' => $this->fotos(1)])->assertSessionHasNoErrors();
        $this->assertSame(5, $subasta->imagenes()->count());
        Storage::disk('public')->assertMissing($foto->ruta);
    }

    public function test_legacy_rejection_without_photos_stays_unchanged_until_a_photo_is_added(): void
    {
        $user = $this->sesion();
        $subasta = $user->subastas()->forceCreate([...$this->datos(), 'fecha_inicio' => now()->subHour(), 'estado_revision' => 'Rechazada', 'motivo_rechazo' => 'Faltan fotografías.']);
        $this->put('/subastas/'.$subasta->id_subasta, [...$this->datos(), 'reenviar' => '1'])->assertSessionHasErrors('imagenes');
        $this->assertSame('Rechazada', $subasta->refresh()->estado_revision);
        $this->assertSame('Faltan fotografías.', $subasta->motivo_rechazo);
        $this->put('/subastas/'.$subasta->id_subasta, [...$this->datos(), 'reenviar' => '1', 'imagenes' => $this->fotos(1)])->assertSessionHasNoErrors();
        $this->assertSame('En revisión', $subasta->refresh()->estado_revision);
    }
}
