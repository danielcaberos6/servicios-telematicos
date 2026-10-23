<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BusquedaGeograficaTest extends TestCase
{
    use RefreshDatabase;

    // Plaza Murillo, La Paz. Un grado de latitud mide 6371·π/180 ≈ 111.195 km en la esfera usada.
    private const CENTRO = ['lat' => -16.4955, 'lng' => -68.1336];

    private const KM_POR_GRADO = 111.19492664455873;

    private function auction(string $titulo, ?float $kmAlNorte, array $overrides = []): Subasta
    {
        return User::factory()->create()->subastas()->create([
            'titulo' => $titulo,
            'descripcion' => 'Artículo de prueba para la búsqueda por cercanía.',
            'id_categoria' => Categoria::firstOrCreate(['nombre_categoria' => 'Hogar'])->id_categoria,
            'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz, punto de prueba',
            'monto_inicial' => 100,
            'fecha_inicio' => now()->subDay(),
            'fecha_fin' => now()->addDays(3),
            'latitud' => is_null($kmAlNorte) ? null : round(self::CENTRO['lat'] + $kmAlNorte / self::KM_POR_GRADO, 6),
            'longitud' => is_null($kmAlNorte) ? null : self::CENTRO['lng'],
            ...$overrides,
        ])->refresh();
    }

    private function buscar(array $params = [])
    {
        return $this->get('/buscar?'.http_build_query($params));
    }

    private function distancia(Subasta $subasta): float
    {
        return DB::selectOne('select distancia_km(?, ?, ?, ?) as km', [self::CENTRO['lat'], self::CENTRO['lng'], $subasta->latitud, $subasta->longitud])->km;
    }

    public function test_haversine_distance_matches_known_values(): void
    {
        $km = fn (...$p) => DB::selectOne('select distancia_km(?, ?, ?, ?) as km', $p)->km;

        $this->assertEqualsWithDelta(0, $km(-16.5, -68.15, -16.5, -68.15), 1e-9);
        $this->assertEqualsWithDelta(self::KM_POR_GRADO, $km(0, 0, 0, 1), 1e-6);
        $this->assertEqualsWithDelta(self::KM_POR_GRADO, $km(0, 179.5, 0, -179.5), 1e-6);
        $this->assertEqualsWithDelta(20015.09, $km(90, 0, -90, 0), 0.01);
        // La Paz (Plaza Murillo) a Santa Cruz (Plaza 24 de Septiembre).
        $this->assertEqualsWithDelta(545.2, $km(-16.4955, -68.1336, -17.7833, -63.1821), 0.1);
    }

    public function test_radius_shows_only_active_current_auctions_within_the_distance(): void
    {
        $this->auction('Cerca a un kilómetro', 1);
        $this->auction('Cerca a cuatro kilómetros', 4);
        $this->auction('Lejos a seis kilómetros', 6);
        $this->auction('Sin coordenadas antigua', null);
        $this->auction('Cerca pero vencida', 1, ['fecha_fin' => now()->subHour()]);
        $this->auction('Cerca pero futura', 1, ['fecha_inicio' => now()->addDay()]);
        $this->auction('Cerca pero cancelada', 1)->forceFill(['estado_subasta' => 'Cancelada'])->save();

        $this->buscar([...self::CENTRO, 'radio' => 5])->assertOk()
            ->assertSee('Cerca a un kilómetro')->assertSee('Cerca a cuatro kilómetros')
            ->assertDontSee('Lejos a seis kilómetros')->assertDontSee('Sin coordenadas antigua')
            ->assertDontSee('Cerca pero vencida')->assertDontSee('Cerca pero futura')->assertDontSee('Cerca pero cancelada')
            ->assertSeeInOrder(['<strong>2</strong>', 'subastas encontradas'], false)
            ->assertSee('Cercanía: hasta 5 km');
    }

    public function test_auction_exactly_at_the_radius_is_included(): void
    {
        $limite = $this->auction('Justo en el borde', 3);
        $radio = sprintf('%.17g', $this->distancia($limite));

        $this->buscar([...self::CENTRO, 'radio' => $radio])->assertSee('Justo en el borde');
        $this->buscar([...self::CENTRO, 'radio' => sprintf('%.6f', $this->distancia($limite) - 0.000001)])->assertDontSee('Justo en el borde');
    }

    public function test_clearing_the_radius_makes_auctions_without_coordinates_eligible_again(): void
    {
        $this->auction('Con mapa cercana', 1);
        $this->auction('Publicación antigua sin mapa', null);
        $this->auction('Antigua sin mapa vencida', null, ['fecha_fin' => now()->subHour()]);

        $this->buscar([...self::CENTRO, 'radio' => 50])->assertSee('Con mapa cercana')->assertDontSee('Publicación antigua sin mapa');
        $this->buscar()->assertSee('Con mapa cercana')->assertSee('Publicación antigua sin mapa')->assertDontSee('Antigua sin mapa vencida');
        $this->buscar(['q' => 'mapa', ...self::CENTRO, 'radio' => 50])
            ->assertSee('href="'.route('subastas.index', ['q' => 'mapa']).'"', false);
    }

    public function test_radius_combines_with_text_category_and_order_and_survives_pagination(): void
    {
        $categoria = Categoria::firstOrCreate(['nombre_categoria' => 'Música'])->id_categoria;
        for ($i = 1; $i <= 13; $i++) {
            $this->auction("Guitarra cercana {$i}", $i * 0.1, ['id_categoria' => $categoria]);
        }
        $this->auction('Guitarra lejana', 30, ['id_categoria' => $categoria]);
        $this->auction('Teclado cercano', 0.5, ['id_categoria' => $categoria]);
        $this->auction('Guitarra de otra categoría', 0.5);

        $params = ['q' => 'guitarra', 'categoria' => $categoria, ...self::CENTRO, 'radio' => 10, 'orden' => 'cercanas'];
        $primera = $this->buscar($params)->assertOk()->assertSeeInOrder(['<strong>13</strong>', 'subastas encontradas'], false)->assertSee('Página 1 de 2')
            ->assertSeeInOrder(['Guitarra cercana 1', 'Guitarra cercana 2', 'Guitarra cercana 3'])
            ->assertDontSee('Guitarra lejana')->assertDontSee('Teclado cercano')->assertDontSee('Guitarra de otra categoría');
        $primera->assertSee(e('/buscar?'.http_build_query([...$params, 'page' => 2])), false);

        $this->buscar([...$params, 'page' => 2])->assertOk()->assertSee('Página 2 de 2')->assertSee('Guitarra cercana 13')
            ->assertDontSee('Guitarra cercana 12');
    }

    public function test_results_show_distance_and_nearest_order_falls_back_without_radius(): void
    {
        $this->auction('Más lejos', 2.5, ['fecha_inicio' => now()->subDays(2)]);
        $this->auction('Más cerca', 1, ['fecha_inicio' => now()->subDays(3)]);

        $this->buscar([...self::CENTRO, 'radio' => 5, 'orden' => 'cercanas'])
            ->assertSeeInOrder(['Más cerca', 'a 1,0 km', 'Más lejos', 'a 2,5 km']);
        $this->buscar(['orden' => 'cercanas'])->assertOk()->assertSeeInOrder(['Más lejos', 'Más cerca'])->assertDontSee(' km</span>', false);
    }

    public function test_missing_center_or_non_positive_radius_returns_validation_errors(): void
    {
        $centro = 'Selecciona en el mapa el centro de la búsqueda por cercanía.';
        $cases = [
            [['radio' => 5], ['lat' => $centro, 'lng' => $centro]],
            [['lat' => self::CENTRO['lat'], 'radio' => 5], ['lng' => $centro]],
            [[...self::CENTRO], ['radio' => 'Indica el radio de búsqueda en kilómetros.']],
            [[...self::CENTRO, 'radio' => 0], ['radio' => 'El radio de búsqueda debe ser mayor que cero.']],
            [[...self::CENTRO, 'radio' => -3], ['radio' => 'El radio de búsqueda debe ser mayor que cero.']],
            [[...self::CENTRO, 'radio' => 'cinco'], ['radio' => 'El radio de búsqueda debe ser un número de kilómetros.']],
            [[...self::CENTRO, 'radio' => 501], ['radio' => 'El radio de búsqueda admite hasta 500 km.']],
            [['lat' => 95, 'lng' => -68, 'radio' => 5], ['lat' => 'La latitud del centro debe estar entre -90 y 90.']],
            [['lat' => -16, 'lng' => -190, 'radio' => 5], ['lng' => 'La longitud del centro debe estar entre -180 y 180.']],
        ];
        foreach ($cases as [$params, $errors]) {
            $this->buscar(['q' => 'bici', ...$params, 'page' => 3])
                ->assertRedirect('/buscar?q=bici')
                ->assertSessionHasErrors($errors);
            $this->getJson('/buscar?'.http_build_query($params))->assertUnprocessable()->assertJsonValidationErrors(array_keys($errors));
        }
    }

    public function test_invalid_radius_redirect_shows_the_errors_in_the_catalog(): void
    {
        $this->from('/buscar')->followingRedirects()->get('/buscar?'.http_build_query([...self::CENTRO, 'radio' => 0]))
            ->assertOk()
            ->assertSee('Catálogo de subastas')
            ->assertSee('El radio de búsqueda debe ser mayor que cero.')
            ->assertSee('id="radio" name="radio" type="number"', false);
    }

    public function test_reinstalling_sql_functions_keeps_the_radius_filter(): void
    {
        $this->auction('Cercana tras reinstalar', 1);
        $this->auction('Lejana tras reinstalar', 20);

        $this->artisan('subastas:instalar-funciones')->assertSuccessful();

        $this->buscar([...self::CENTRO, 'radio' => 5])->assertSee('Cercana tras reinstalar')->assertDontSee('Lejana tras reinstalar');
    }

    public function test_radius_without_matches_shows_an_empty_state(): void
    {
        $this->auction('Lejana en El Alto', 12);

        $this->buscar([...self::CENTRO, 'radio' => 2])->assertOk()
            ->assertSeeInOrder(['<strong>0</strong>', 'subastas encontradas'], false)
            ->assertSee('No encontramos coincidencias')
            ->assertSee('No hay subastas activas con punto de encuentro a 2 km o menos del centro elegido.')
            ->assertSee('Quitar filtro de cercanía');
    }
}
