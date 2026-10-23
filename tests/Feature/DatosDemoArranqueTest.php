<?php

namespace Tests\Feature;

use App\Models\Subasta;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatosDemoArranqueTest extends TestCase
{
    use RefreshDatabase;

    public function test_startup_loads_examples_without_duplicating_or_resetting_existing_data(): void
    {
        Storage::fake('public');
        $this->app['env'] = 'local';
        $this->artisan('subastas:cargar-demo')->assertSuccessful();
        $this->assertDatabaseCount('usuario', 3);
        $this->assertDatabaseCount('subasta', 9);
        $this->assertSame(0, Subasta::doesntHave('imagenes')->count());
        $this->assertSame(7, Subasta::where('estado_revision', 'Aceptada')->count());

        $admin = User::where('correo', 'admin@subastaya.test')->sole();
        $admin->forceFill(['contrasena' => 'ClaveCambiada2026!', 'activo' => false])->save();
        DB::table('usuario_rol')->where('id_usuario', $admin->id_usuario)->delete();
        $cuenta = $admin->fresh()->getAttributes();
        $subastas = DB::table('subasta')->orderBy('id_subasta')->get()->toJson();
        $avisos = DB::table('notificacion')->count();
        $imagenes = DB::table('imagen')->count();

        $this->artisan('subastas:cargar-demo')->assertSuccessful();
        $this->assertSame($cuenta, $admin->fresh()->getAttributes());
        $this->assertFalse($admin->esAdministrador());
        $this->assertSame($subastas, DB::table('subasta')->orderBy('id_subasta')->get()->toJson());
        $this->assertSame($avisos, DB::table('notificacion')->count());
        $this->assertSame($imagenes, DB::table('imagen')->count());
        $this->assertDatabaseCount('usuario', 3);
    }

    public function test_startup_skips_examples_in_production(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('subastas:cargar-demo')->assertSuccessful();
        $this->assertDatabaseCount('usuario', 0);
        $this->assertDatabaseCount('subasta', 0);
    }
}
