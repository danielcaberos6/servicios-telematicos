<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\SubastaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class GestionCategoriasTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        DB::table('usuario_rol')->insert(['id_usuario' => $user->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);

        return $user;
    }

    public function test_admin_creates_edits_and_toggles_categories_with_normalized_unique_names(): void
    {
        $this->actingAs($this->admin())->post('/admin/categorias', ['nombre_categoria' => '  Arte único  ', 'descripcion' => 'Obras de arte'])
            ->assertRedirect('/admin/categorias')->assertSessionHasNoErrors();
        $category = Categoria::where('nombre_categoria', 'Arte único')->firstOrFail();
        $this->post('/admin/categorias', ['nombre_categoria' => 'ARTE ÚNICO'])->assertSessionHasErrors('nombre_categoria');
        $this->assertSame(1, Categoria::whereRaw('lower(trim(nombre_categoria)) = ?', ['arte único'])->count());
        $this->put('/admin/categorias/'.$category->id_categoria, ['nombre_categoria' => 'Arte moderno', 'descripcion' => 'Descripción editada'])->assertSessionHasNoErrors();
        $this->patch('/admin/categorias/'.$category->id_categoria.'/estado', ['habilitada' => false])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('categoria', ['id_categoria' => $category->id_categoria, 'nombre_categoria' => 'Arte moderno', 'habilitada' => false]);
        $this->patch('/admin/categorias/'.$category->id_categoria.'/estado', ['habilitada' => true])->assertSessionHasNoErrors();
        $this->get('/subastas/crear')->assertSee('Arte moderno');
    }

    public function test_invalid_names_and_ordinary_users_cannot_modify_categories(): void
    {
        $category = Categoria::first();
        $this->actingAs(User::factory()->create())->postJson('/admin/categorias', ['nombre_categoria' => 'Ajena'])->assertForbidden();
        $this->putJson('/admin/categorias/'.$category->id_categoria, ['nombre_categoria' => 'Ajena'])->assertForbidden();
        $this->patchJson('/admin/categorias/'.$category->id_categoria.'/estado', ['habilitada' => false])->assertForbidden();
        $this->actingAs($this->admin())->post('/admin/categorias', ['nombre_categoria' => '   '])->assertSessionHasErrors('nombre_categoria');
        $this->put('/admin/categorias/'.$category->id_categoria, ['nombre_categoria' => str_repeat('x', 101)])->assertSessionHasErrors('nombre_categoria');
    }

    public function test_disabled_categories_reject_new_publications_and_keep_existing_history(): void
    {
        $owner = User::factory()->create();
        $category = Categoria::first();
        $data = ['titulo' => 'Artículo de prueba', 'descripcion' => 'Descripción extensa para la prueba.',
            'ubicacion' => 'La Paz, Plaza Abaroa', 'id_categoria' => $category->id_categoria,
            'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'fecha_fin' => now()->addDays(3)->toDateTimeString()];
        $auction = app(SubastaService::class)->crear($owner->id_usuario, $data);
        $category->forceFill(['habilitada' => false])->save();
        $this->actingAs($owner)->get('/subastas/crear')->assertDontSee('value="'.$category->id_categoria.'"', false);
        $this->post('/subastas', $data)->assertSessionHasErrors('id_categoria');
        $this->get('/subastas/'.$auction->id_subasta)->assertSee($category->nombre_categoria);
        $this->assertDatabaseHas('subasta', ['id_subasta' => $auction->id_subasta, 'id_categoria' => $category->id_categoria]);

        $this->expectException(ValidationException::class);
        app(SubastaService::class)->crear($owner->id_usuario, $data);
    }
}
