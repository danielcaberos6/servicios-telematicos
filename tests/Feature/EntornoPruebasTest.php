<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EntornoPruebasTest extends TestCase
{
    public function test_database_is_isolated_from_the_local_application(): void
    {
        $this->assertSame('subastaya_test', DB::selectOne('select current_database() as nombre')->nombre);
        $this->assertNull(config('database.connections.pgsql.url'));
    }
}
