<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use App\Services\IdentidadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrenciaAdministrativaTest extends TestCase
{
    private array $usuarios = [];

    protected function setUp(): void
    {
        parent::setUp();
        Artisan::call('migrate', ['--force' => true]);
    }

    protected function tearDown(): void
    {
        try {
            DB::table('notificacion')->whereIn('id_usuario', $this->usuarios)->delete();
            DB::table('verificacion_identidad')->whereIn('id_usuario', $this->usuarios)->delete();
            DB::table('decision_usuario')->whereIn('id_usuario', $this->usuarios)->delete();
            DB::table('subasta')->whereIn('id_usuario', $this->usuarios)->delete();
            DB::table('usuario_rol')->whereIn('id_usuario', $this->usuarios)->delete();
            DB::table('imagen')->whereIn('id_usuario', $this->usuarios)->delete();
            DB::table('aceptacion_normas')->whereIn('id_usuario', $this->usuarios)->delete();
            DB::table('usuario')->whereIn('id_usuario', $this->usuarios)->delete();
        } finally {
            parent::tearDown();
        }
    }

    private function cuenta(bool $admin = false): User
    {
        $user = User::factory()->create();
        $this->usuarios[] = $user->id_usuario;
        if ($admin) {
            DB::table('usuario_rol')->insert(['id_usuario' => $user->id_usuario, 'id_rol' => DB::table('rol')->where('nombre_rol', 'Administrador')->value('id_rol')]);
        }

        return $user;
    }

    private function competir(string $sql, array $argumentos): array
    {
        $config = config('database.connections.pgsql');
        $codigo = <<<'PHP'
            $pdo = new PDO(getenv('RACE_DSN'), getenv('RACE_USER'), getenv('RACE_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            if ($pdo->query('select current_database()')->fetchColumn() !== 'subastaya_test') { exit(2); }
            try {
                $query = $pdo->prepare(getenv('RACE_SQL'));
                $query->execute(json_decode(getenv('RACE_ARGS'), true));
                echo 'ok';
            } catch (PDOException $exception) {
                echo 'denied:'.$exception->getCode();
            }
            PHP;
        $procesos = [];
        DB::select('select pg_advisory_lock(8009,1)');
        try {
            foreach ($argumentos as $args) {
                $proceso = new Process([PHP_BINARY, '-r', $codigo], null, [
                    'RACE_DSN' => 'pgsql:host='.$config['host'].';port='.$config['port'].';dbname=subastaya_test',
                    'RACE_USER' => $config['username'], 'RACE_PASSWORD' => $config['password'],
                    'RACE_SQL' => $sql, 'RACE_ARGS' => json_encode($args, JSON_THROW_ON_ERROR),
                ]);
                $proceso->setTimeout(10);
                $proceso->start();
                $procesos[] = $proceso;
            }
            $limite = microtime(true) + 5;
            do {
                $esperando = DB::selectOne("select count(*) as cantidad from pg_locks where locktype='advisory' and classid=8009 and objid=1 and not granted and database=(select oid from pg_database where datname=current_database())")->cantidad;
                if ($esperando >= count($procesos)) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $limite);
            $this->assertSame(count($procesos), (int) $esperando, 'Ambos procesos deben estar esperando el mismo bloqueo.');
        } finally {
            DB::select('select pg_advisory_unlock(8009,1)');
        }
        $resultados = [];
        foreach ($procesos as $proceso) {
            $this->assertSame(0, $proceso->wait());
            $resultados[] = $proceso->getOutput();
        }

        return $resultados;
    }

    public function test_simultaneous_role_removals_cannot_remove_the_last_active_admin(): void
    {
        $uno = $this->cuenta(true);
        $dos = $this->cuenta(true);
        $resultados = $this->competir('select gestionar_cuenta(?,?,?,?::boolean)', [
            [$uno->id_usuario, $dos->id_usuario, 'administrador', 'false'],
            [$dos->id_usuario, $uno->id_usuario, 'administrador', 'false'],
        ]);
        $this->assertCount(1, array_filter($resultados, fn ($r) => $r === 'ok'));
        $this->assertSame(1, (int) $uno->esAdministrador() + (int) $dos->esAdministrador());
        $this->assertSame(1, DB::table('decision_usuario')->whereIn('id_usuario', $this->usuarios)->count());
    }

    public function test_simultaneous_identity_decisions_create_only_one_result_and_notification(): void
    {
        Storage::fake('identidad');
        $uno = $this->cuenta(true);
        $dos = $this->cuenta(true);
        $titular = $this->cuenta();
        $titular->forceFill(['nombres' => 'Usuario', 'apellidos' => 'Prueba'])->save();
        $titular->imagen()->create(['ruta' => 'perfiles/prueba.png', 'tamano' => 100]);
        $id = app(IdentidadService::class)->solicitar($titular->id_usuario, $titular->refresh()->version_identidad, UploadedFile::fake()->image('carnet.png'));
        $resultados = $this->competir('select resolver_verificacion(?,?,?,?)', [
            [$uno->id_usuario, $id, 'Aprobada', null], [$dos->id_usuario, $id, 'Rechazada', 'No coincide'],
        ]);
        $this->assertCount(1, array_filter($resultados, fn ($r) => $r === 'ok'));
        $this->assertSame(1, DB::table('notificacion')->where('id_verificacion', $id)->whereIn('titulo', ['Identidad verificada', 'Verificación de identidad rechazada'])->count());
        $estado = DB::table('verificacion_identidad')->where('id_verificacion', $id)->value('estado');
        $this->assertSame($estado === 'Aprobada', $titular->refresh()->identidad_verificada);
    }

    public function test_simultaneous_auction_decisions_create_only_one_result_and_notification(): void
    {
        $uno = $this->cuenta(true);
        $dos = $this->cuenta(true);
        $vendedor = $this->cuenta();
        $categoria = Categoria::firstOrCreate(['nombre_categoria' => 'Deporte']);
        $subasta = $vendedor->subastas()->forceCreate([
            'titulo' => 'Bicicleta de montaña',
            'descripcion' => 'Bicicleta en excelente estado.',
            'id_categoria' => $categoria->id_categoria,
            'estado_articulo' => 'Usado',
            'ubicacion' => 'La Paz, Plaza Avaroa',
            'monto_inicial' => '500.00',
            'fecha_inicio' => now()->subHour(),
            'fecha_fin' => now()->addDays(5),
            'estado_subasta' => 'Activa',
            'estado_revision' => 'En revisión',
        ]);

        $resultados = $this->competir('select resolver_revision_subasta(?,?,?,?)', [
            [$uno->id_usuario, $subasta->id_subasta, 'Aceptada', null],
            [$dos->id_usuario, $subasta->id_subasta, 'Rechazada', 'Fotos inadecuadas'],
        ]);
        $this->assertCount(1, array_filter($resultados, fn ($r) => $r === 'ok'));
        $this->assertSame(1, DB::table('notificacion')->where('id_subasta', $subasta->id_subasta)->whereIn('titulo', ['Subasta publicada', 'Subasta rechazada'])->count());
        $this->assertNotSame('En revisión', $subasta->refresh()->estado_revision);
        $expectedReviewer = $subasta->estado_revision === 'Aceptada' ? $uno : $dos;
        $this->assertEquals($expectedReviewer->id_usuario, $subasta->id_revisor);
        $this->assertNotNull($subasta->fecha_decision);
    }
}
