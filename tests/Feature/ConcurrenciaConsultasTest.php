<?php

namespace Tests\Feature;

use App\Models\Categoria;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrenciaConsultasTest extends TestCase
{
    use DatabaseTruncation;

    protected function beforeTruncatingDatabase(): void
    {
        RefreshDatabaseState::$migrated = false;
        $this->beforeApplicationDestroyed(function () {
            RefreshDatabaseState::$migrated = false;
        });
    }

    public function test_two_connections_cannot_duplicate_a_question_answer_or_notification(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $s = $owner->subastas()->forceCreate([
            'titulo' => 'Consultas concurrentes', 'descripcion' => 'Artículo para probar dos conexiones.', 'ubicacion' => 'La Paz',
            'estado_articulo' => 'Usado', 'monto_inicial' => 100, 'id_categoria' => Categoria::firstOrFail()->id_categoria,
            'modelo_puja' => 1, 'estado_revision' => 'Aceptada', 'fecha_inicio' => now()->subHour(), 'fecha_fin' => now()->addDay(),
        ]);
        $config = config('database.connections.pgsql');
        $this->assertSame('subastaya_test', $config['database']);
        $runner = <<<'PHP'
            $c=json_decode(stream_get_contents(STDIN),true);
            $pdo=new PDO($c['dsn'],$c['user'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
            if($pdo->query('select current_database()')->fetchColumn()!=='subastaya_test'){exit(2);}
            $pdo->exec("set application_name='hu24_concurrencia'; set statement_timeout='8s'");
            try { $stmt=$pdo->prepare($c['sql']); $stmt->execute($c['args']); echo 'ok'; }
            catch(PDOException $e){echo $e->getCode();}
            PHP;
        $clave = (string) Str::uuid();
        foreach (['pregunta', 'respuesta'] as $fase) {
            $sql = $fase === 'pregunta' ? 'select preguntar_articulo(?,?,?,?::uuid)' : 'select responder_articulo(?,?,?)';
            $args = $fase === 'pregunta' ? [$user->id_usuario, $s->id_subasta, '¿Incluye accesorios?', $clave]
                : [$owner->id_usuario, DB::table('consulta_articulo')->value('id_consulta'), 'Sí, incluye sus accesorios.'];
            $procesos = [];
            DB::beginTransaction();
            try {
                DB::table('subasta')->where('id_subasta', $s->id_subasta)->lockForUpdate()->first();
                for ($i = 0; $i < 2; $i++) {
                    $process = new Process([PHP_BINARY, '-r', $runner]);
                    $process->setInput(json_encode(['dsn' => 'pgsql:host='.$config['host'].';port='.$config['port'].';dbname=subastaya_test', 'user' => $config['username'], 'password' => $config['password'], 'sql' => $sql, 'args' => $args], JSON_THROW_ON_ERROR));
                    $process->setTimeout(12)->start();
                    $procesos[] = $process;
                }
                $limite = microtime(true) + 4;
                do {
                    DB::select('select pg_stat_clear_snapshot()');
                    $esperando = DB::selectOne("select count(*) as total from pg_stat_activity where datname='subastaya_test' and application_name='hu24_concurrencia' and wait_event_type='Lock'")->total;
                    if ($esperando < 2) {
                        usleep(20000);
                    }
                } while ($esperando < 2 && microtime(true) < $limite);
                $this->assertSame(2, (int) $esperando);
                DB::commit();
                foreach ($procesos as $process) {
                    $this->assertSame(0, $process->wait());
                    $this->assertSame('ok', $process->getOutput());
                }
            } finally {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }
                foreach ($procesos as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
            }
        }
        $this->assertDatabaseCount('consulta_articulo', 1);
        $this->assertSame(1, DB::table('consulta_articulo')->whereNotNull('respuesta')->count());
        foreach (['Nueva pregunta sobre tu artículo', 'Respondieron tu pregunta'] as $titulo) {
            $this->assertSame(1, DB::table('notificacion')->where('titulo', $titulo)->count());
        }
    }
}
