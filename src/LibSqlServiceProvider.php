<?php

namespace Uplucid\LibSql;

use Illuminate\Database\DatabaseManager;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Uplucid\LibSql\Connectors\LibSqlConnector;

class LibSqlServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        $this->app->make(DatabaseManager::class)->extend('libsql', function ($config, $name) {
            $connector = new LibSqlConnector();
            $connection = $connector->connect($config);

            return $connection;
        });
    }

    public function register(): void
    {
    }
}
