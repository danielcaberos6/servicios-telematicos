<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FuncionesSqlTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array<int, array{archivo: string, cuerpo: string}>> */
    private function definiciones(string $patron): array
    {
        $encontradas = [];
        foreach (glob(database_path('sql/*.sql')) as $ruta) {
            $sql = str_replace("\r\n", "\n", file_get_contents($ruta));
            preg_match_all($patron, $sql, $coincidencias, PREG_SET_ORDER);
            foreach ($coincidencias as $coincidencia) {
                $encontradas[strtolower($coincidencia[1])][] = ['archivo' => basename($ruta), 'cuerpo' => $coincidencia[2] ?? ''];
            }
        }

        return $encontradas;
    }

    private function funciones(): array
    {
        return $this->definiciones('/create\s+or\s+replace\s+function\s+(\w+)\s*\(.*?\$\$(.*?)\$\$;/is');
    }

    public function test_each_sql_function_and_trigger_is_defined_in_a_single_place(): void
    {
        $duplicadas = [];
        foreach ([...$this->funciones(), ...$this->definiciones('/create\s+trigger\s+(\w+)/i')] as $nombre => $sitios) {
            if (count($sitios) > 1) {
                $duplicadas[] = $nombre.' en '.implode(', ', array_column($sitios, 'archivo'));
            }
        }

        $this->assertSame([], $duplicadas, 'Una copia antigua puede pisar la versión vigente según el orden de instalación.');
    }

    public function test_installed_functions_match_the_file_that_defines_them(): void
    {
        $distintas = [];
        foreach ($this->funciones() as $nombre => [$definicion]) {
            $instaladas = DB::select("select prosrc from pg_proc where proname = ? and pronamespace = 'public'::regnamespace", [$nombre]);
            if (count($instaladas) !== 1 || $instaladas[0]->prosrc !== $definicion['cuerpo']) {
                $distintas[] = "{$nombre} ({$definicion['archivo']}): ".count($instaladas).' versión(es) instalada(s)';
            }
        }

        $this->assertSame([], $distintas, 'Tras migrar, cada función debe ser exactamente la de su archivo .sql.');
    }

    public function test_reinstalling_funciones_sql_alone_keeps_review_rules_and_triggers(): void
    {
        $vendedor = User::factory()->create();
        $subasta = $vendedor->subastas()->forceCreate([
            'titulo' => 'Solicitud sin revisar', 'descripcion' => 'Descripción de la solicitud pendiente.',
            'ubicacion' => 'La Paz centro', 'estado_articulo' => 'Usado', 'monto_inicial' => 100,
            'id_categoria' => Categoria::firstOrCreate(['nombre_categoria' => 'Prueba SQL'])->id_categoria,
            'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDays(2), 'estado_revision' => 'En revisión',
        ]);

        DB::unprepared(file_get_contents(database_path('sql/Funciones.sql')));

        $this->assertSame(0, count(DB::select("select 1 from catalogo_subastas('{}'::jsonb) where id_subasta = ?", [$subasta->id_subasta])));
        $triggers = DB::table('pg_trigger')->whereIn('tgname', ['puja_validacion', 'subasta_publicada'])->pluck('tgname')->sort()->values()->all();
        $this->assertSame(['puja_validacion', 'subasta_publicada'], $triggers);
        try {
            DB::transaction(fn () => DB::table('puja')->insert(['id_subasta' => $subasta->id_subasta, 'id_usuario' => User::factory()->create()->id_usuario, 'monto' => 150]));
            $this->fail('La base aceptó una puja en una subasta sin publicar.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('todavía no está publicada', $e->getMessage());
        }
    }
}
