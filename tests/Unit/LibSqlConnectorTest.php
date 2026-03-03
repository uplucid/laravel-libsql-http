<?php

namespace Uplucid\LibSql\Tests\Unit;

use Uplucid\LibSql\Connectors\LibSqlConnector;
use Uplucid\LibSql\Tests\TestCase;

class LibSqlConnectorTest extends TestCase
{
    public function testConnectorCreatesConnectionWithCorrectConfig(): void
    {
        $connector = new LibSqlConnector();
        
        $config = [
            'host' => 'test.turso.io',
            'token' => 'test-token',
            'database' => 'testdb',
            'prefix' => 'prefix_',
            'timeout' => 60,
        ];

        $connection = $connector->connect($config);

        $this->assertNotNull($connection);
    }

    public function testConnectorUsesDefaultValues(): void
    {
        $connector = new LibSqlConnector();
        
        $config = [
            'host' => 'test.turso.io',
            'token' => 'test-token',
        ];

        $connection = $connector->connect($config);

        $this->assertNotNull($connection);
    }
}
