<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\Subasta;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FiltrosApartadosTest extends TestCase
{
    use RefreshDatabase;

    private function ejemplos(): void
    {
        Storage::fake('public');
        $this->seed(DemoSeeder::class);
    }

    public function test_my_auction_sections_filter_by_state_and_never_include_another_seller(): void
    {
        $this->ejemplos();
        $user = User::where('correo', 'demo@subastaya.test')->sole();
        $this->actingAs($user);
        foreach (['revision' => 'En revisión', 'rechazadas' => 'Rechazada', 'publicadas' => 'Aceptada'] as $apartado => $estado) {
            $this->get('/mis-subastas?apartado='.$apartado)->assertOk()->assertViewHas('subastas', function ($subastas) use ($user, $estado) {
                return $subastas->count() === 1 && $subastas->every(fn ($subasta) => $subasta->id_usuario === $user->id_usuario && $subasta->estado_revision === $estado);
            });
        }
        $subasta = $user->subastas()->where('estado_revision', 'Aceptada')->sole();
        $subasta->forceFill(['fecha_fin' => now()->subMinute()])->save();
        $this->get('/mis-subastas?apartado=finalizadas')->assertOk()
            ->assertViewHas('subastas', fn ($subastas) => $subastas->pluck('id_subasta')->all() === [$subasta->id_subasta]);
        $this->getJson('/mis-subastas?apartado=incorrecto')->assertUnprocessable();
    }

    public function test_category_sections_filter_availability_and_keep_it_in_pagination(): void
    {
        $this->ejemplos();
        $this->actingAs(User::where('correo', 'admin@subastaya.test')->sole());
        $categoria = Categoria::firstOrFail();
        $categoria->forceFill(['habilitada' => false])->save();
        $this->get('/admin/categorias?estado=deshabilitadas')->assertOk()
            ->assertViewHas('categorias', fn ($categorias) => $categorias->count() === 1 && $categorias->first()->id_categoria === $categoria->id_categoria);
        $this->get('/admin/categorias?estado=habilitadas')->assertOk()
            ->assertViewHas('categorias', fn ($categorias) => $categorias->every(fn ($categoria) => $categoria->habilitada) && $categorias->url(2) === route('admin.categorias.index', ['estado' => 'habilitadas', 'page' => 2]));
        $this->getJson('/admin/categorias?estado=incorrecto')->assertUnprocessable();
    }
}
