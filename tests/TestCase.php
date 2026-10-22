<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    public function createApplication()
    {
        $app = parent::createApplication();

        // Docker puede conservar DB_DATABASE en $_SERVER aunque PHPUnit cambie $_ENV.
        // Fijar la conexión antes de que RefreshDatabase ejecute cualquier migración.
        $app['config']->set('database.default', 'pgsql');
        $app['config']->set('database.connections.pgsql.database', 'subastaya_test');
        $app['config']->set('database.connections.pgsql.url', null);
        $app['db']->purge('pgsql');
        $database = $app['db']->connection()->selectOne('select current_database() as nombre')->nombre;
        if ($database !== 'subastaya_test') {
            throw new \RuntimeException('Se detuvieron las pruebas: la conexión no apunta a subastaya_test.');
        }

        return $app;
    }
}
