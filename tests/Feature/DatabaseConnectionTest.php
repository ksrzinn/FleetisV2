<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConnectionTest extends TestCase
{
    public function test_postgres_connection_works(): void
    {
        $driver = DB::connection()->getDriverName();
        $this->assertSame('pgsql', $driver);

        $result = DB::selectOne('select version() as v');
        $this->assertStringContainsString('PostgreSQL', $result->v);
    }
}
