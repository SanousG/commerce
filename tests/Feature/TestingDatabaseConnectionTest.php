<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TestingDatabaseConnectionTest extends TestCase
{
    public function test_testing_database_connection_is_isolated(): void
    {
        $connection = DB::connection();
        $identity = $connection->selectOne(
            'select current_database() as database, current_user as username'
        );

        $this->assertSame('pgsql', $connection->getDriverName());
        $this->assertSame('pgsql.testing', $connection->getConfig('host'));
        $this->assertSame('commerce_testing', $identity->database);
        $this->assertSame('commerce_testing', $identity->username);
    }
}
