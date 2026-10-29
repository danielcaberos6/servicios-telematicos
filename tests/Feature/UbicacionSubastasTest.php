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

class UbicacionSubastasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'imagenes' => [UploadedFile::fake()->image('articulo.jpg')],
            'titulo' => 'Bicicleta de montaña aro 29',
            'descripcion' => 'Bicicleta en buen estado, con frenos de disco y cambios revisados.',
            'id_categoria' => Categoria::firstOrCreate(['nombre_categoria' => 'Deportes'])->id_categoria,
            'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz, Plaza Abaroa, frente a la fuente',
            'monto_inicial' => '850.00',
            'fecha_fin' => now()->addDays(5)->format('Y-m-d\TH:i'),
        ], $overrides);
    }

    private function auction(?User $user = null, array $overrides = []): Subasta
    {
        return ($user ?? User::factory()->create())->subastas()->forceCreate([
            ...array_diff_key($this->payload(), ['imagenes' => true]), 'fecha_inicio' => now()->subDay(), 'estado_revision' => 'Aceptada', ...$overrides,
        ])->refresh();
    }

    public function test_publication_form_offers_a_leaflet_selector_next_to_the_textual_location(): void
    {
        $this->actingAs(User::factory()->create())->get('/subastas/crear')->assertOk()
            ->assertSee('data-mapa-selector', false)
            ->assertSee('https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', false)
            ->assertSee('integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="', false)
            ->assertSee('js/mapas.js', false)
            ->assertSee('name="ubicacion"', false)
            ->assertSee('id="latitud" name="latitud" value=""', false)
            ->assertSee('El mapa no está disponible en este momento');
    }

    public function test_selected_marker_is_saved_with_the_textual_meeting_point(): void
    {
        $this->actingAs(User::factory()->create())->post('/subastas', $this->payload([
            'latitud' => '-16.510417', 'longitud' => '-68.126389',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('subasta', [
            'ubicacion' => 'La Paz, Plaza Abaroa, frente a la fuente', 'latitud' => '-16.510417', 'longitud' => '-68.126389',
        ]);
    }

    public function test_boundary_coordinates_are_accepted(): void
    {
        $owner = User::factory()->create();
        foreach ([['90', '-180'], ['-90', '180'], ['0', '0']] as [$lat, $lng]) {
            $this->actingAs($owner)->post('/subastas', $this->payload(['latitud' => $lat, 'longitud' => $lng]))
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(3, Subasta::count());
        $this->assertDatabaseHas('subasta', ['latitud' => 0, 'longitud' => 0]);
    }

    public function test_server_rejects_incomplete_or_out_of_range_pairs(): void
    {
        $owner = User::factory()->create();
        $cases = [
            [['latitud' => '90.000001', 'longitud' => '-68.1'], ['latitud' => 'El campo latitud debe estar entre -90 y 90.']],
            [['latitud' => '-91', 'longitud' => '-68.1'], ['latitud' => 'El campo latitud debe estar entre -90 y 90.']],
            [['latitud' => '-16.5', 'longitud' => '180.5'], ['longitud' => 'El campo longitud debe estar entre -180 y 180.']],
            [['latitud' => '-16.5', 'longitud' => '-181'], ['longitud' => 'El campo longitud debe estar entre -180 y 180.']],
            [['latitud' => '-16.5'], ['longitud' => 'Falta la longitud del punto de encuentro. Vuelve a marcarlo en el mapa o quita las coordenadas.']],
            [['latitud' => '-16.5', 'longitud' => ''], ['longitud']],
            [['longitud' => '-68.1'], ['latitud' => 'Falta la latitud del punto de encuentro. Vuelve a marcarlo en el mapa o quita las coordenadas.']],
            [['latitud' => 'abc', 'longitud' => '-68.1'], ['latitud']],
        ];
        foreach ($cases as [$coordinates, $errors]) {
            $this->actingAs($owner)->post('/subastas', $this->payload($coordinates))->assertSessionHasErrors($errors);
        }
        $this->assertDatabaseCount('subasta', 0);
    }

    public function test_publication_without_coordinates_keeps_only_the_textual_location(): void
    {
        $this->actingAs(User::factory()->create())->post('/subastas', $this->payload(['latitud' => '', 'longitud' => '']))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('subasta', ['ubicacion' => 'La Paz, Plaza Abaroa, frente a la fuente', 'latitud' => null, 'longitud' => null]);
    }

    public function test_owner_can_move_or_remove_the_marker_when_editing(): void
    {
        $owner = User::factory()->create();
        $auction = $this->auction($owner, ['latitud' => -16.5, 'longitud' => -68.15]);

        $this->actingAs($owner)->get("/subastas/{$auction->id_subasta}/editar")->assertOk()
            ->assertSee('id="latitud" name="latitud" value="-16.500000"', false)
            ->assertSee('Coordenadas registradas: -16.500000, -68.150000');

        $this->put("/subastas/{$auction->id_subasta}", $this->payload(['latitud' => '-17.783333', 'longitud' => '-63.182222']))
            ->assertSessionHasNoErrors();
        $this->assertEquals(['-17.783333', '-63.182222'], [(string) $auction->refresh()->latitud, (string) $auction->longitud]);

        $this->put("/subastas/{$auction->id_subasta}", $this->payload(['latitud' => '', 'longitud' => '']))->assertSessionHasNoErrors();
        $this->assertNull($auction->refresh()->latitud);
        $this->assertNull($auction->longitud);
    }

    public function test_invalid_pair_keeps_previous_coordinates_when_editing(): void
    {
        $owner = User::factory()->create();
        $auction = $this->auction($owner, ['latitud' => -16.5, 'longitud' => -68.15]);

        $this->actingAs($owner)->put("/subastas/{$auction->id_subasta}", $this->payload(['latitud' => '-95', 'longitud' => '-68.15']))
            ->assertSessionHasErrors('latitud');
        $this->assertEquals('-16.500000', (string) $auction->refresh()->latitud);
    }

    public function test_detail_shows_leaflet_map_with_marker_and_textual_location(): void
    {
        $auction = $this->auction(null, ['latitud' => -16.510417, 'longitud' => -68.126389]);

        $this->get("/subastas/{$auction->id_subasta}")->assertOk()
            ->assertSee('data-mapa-detalle', false)
            ->assertSee('data-lat="-16.510417"', false)
            ->assertSee('data-lng="-68.126389"', false)
            ->assertSee('https://unpkg.com/leaflet@1.9.4/dist/leaflet.css', false)
            ->assertSee('js/mapas.js', false)
            ->assertSee('La Paz, Plaza Abaroa, frente a la fuente')
            ->assertSee('No pudimos cargar el mapa. Usa la dirección escrita para coordinar la entrega.')
            ->assertDontSee('Esta publicación no tiene ubicación en el mapa');
    }

    public function test_detail_of_old_publication_without_coordinates_keeps_text_and_article_readable(): void
    {
        $auction = $this->auction(null, ['latitud' => null, 'longitud' => null, 'modelo_puja' => 1]);

        $this->actingAs(User::factory()->create())->get("/subastas/{$auction->id_subasta}")->assertOk()
            ->assertSee('La Paz, Plaza Abaroa, frente a la fuente')
            ->assertSee('Esta publicación no tiene ubicación en el mapa. Usa la dirección escrita para coordinar la entrega.')
            ->assertSee('Bicicleta en buen estado, con frenos de disco y cambios revisados.')
            ->assertSee('Hacer oferta')
            ->assertDontSee('data-mapa-detalle', false)
            ->assertDontSee('leaflet.js', false);
    }

    public function test_detail_map_escapes_the_meeting_point_text(): void
    {
        $auction = $this->auction(null, ['ubicacion' => 'Plaza "Murillo" <script>alert(1)</script>', 'latitud' => -16.4955, 'longitud' => -68.1336]);

        $this->get("/subastas/{$auction->id_subasta}")->assertOk()
            ->assertSee('data-titulo="Plaza &quot;Murillo&quot; &lt;script&gt;alert(1)&lt;/script&gt;"', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_database_rejects_incomplete_or_out_of_range_pairs(): void
    {
        $auction = $this->auction();
        foreach ([[95, -68], [-16, 200], [-16, null]] as [$lat, $lng]) {
            try {
                DB::transaction(fn () => DB::table('subasta')->where('id_subasta', $auction->id_subasta)->update(['latitud' => $lat, 'longitud' => $lng]));
                $this->fail('La base de datos aceptó un par de coordenadas inválido.');
            } catch (QueryException $exception) {
                $this->assertStringContainsString('subasta_coordenadas_validas', $exception->getMessage());
            }
        }
    }
}
