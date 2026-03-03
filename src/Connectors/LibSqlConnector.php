<?php

namespace Uplucid\LibSql\Connectors;

use GuzzleHttp\Client;
use Uplucid\LibSql\Connections\LibSqlConnection;

class LibSqlConnector
{
    public function connect(array $config): LibSqlConnection
    {
        $host = $config['host'] ?? 'localhost';
        $token = $config['token'] ?? '';
        $database = $config['database'] ?? ':memory:';
        $timeout = $config['timeout'] ?? 30;

        $baseUrl = 'https://' . $host;

        $client = new Client([
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ],
        ]);

        return new LibSqlConnection(
            $client,
            $database,
            $config['prefix'] ?? '',
            $config
        );
    }
}
