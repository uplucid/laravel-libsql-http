<?php

namespace Uplucid\LibSql\Connections;

use Closure;
use GuzzleHttp\Client;
use Illuminate\Database\Connection as DatabaseConnection;
use Uplucid\LibSql\Query\Builder as QueryBuilder;
use Uplucid\LibSql\Schema\Builder as SchemaBuilder;

class LibSqlConnection extends DatabaseConnection
{
    protected Client $client;
    protected ?string $baton = null;
    protected ?string $baseUrl = null;

    public function __construct(Client $client, string $database, string $prefix = '', array $config = [])
    {
        $this->client = $client;
        $this->database = $database;
        $this->prefix = $prefix;
        $this->config = $config;

        $this->useDefaultPostProcessor();
        $this->useDefaultQueryGrammar();
    }

    protected function getDefaultQueryGrammar(): \Illuminate\Database\Query\Grammars\Grammar
    {
        return new \Uplucid\LibSql\Query\Grammars\Grammar();
    }

    protected function getDefaultSchemaGrammar(): \Illuminate\Database\Schema\Grammars\Grammar
    {
        return new \Uplucid\LibSql\Schema\Grammars\Grammar();
    }

    public function getDriverName(): string
    {
        return 'libsql';
    }

    public function select($query, $bindings = [], $useReadPool = false)
    {
        $result = $this->execute($query, $bindings);
        
        if (empty($result) || !isset($result[0]['cols'])) {
            return [];
        }

        return $this->parseResults($result);
    }

    public function affectingStatement($query, $bindings = [])
    {
        $result = $this->execute($query, $bindings);
        
        if (empty($result)) {
            return 0;
        }

        $firstResult = $result[0] ?? [];
        
        if (isset($firstResult['affected_row_count'])) {
            return (int) $firstResult['affected_row_count'];
        }

        if (isset($firstResult['affected_rows'])) {
            return (int) $firstResult['affected_rows'];
        }

        return 0;
    }

    public function statement($query, $bindings = [])
    {
        $this->execute($query, $bindings);
        return true;
    }

    protected function execute(string $sql, array $bindings = []): array
    {
        $stmt = [
            'sql' => $sql,
            'args' => array_values($bindings),
        ];

        $payload = [
            'requests' => [
                [
                    'type' => 'execute',
                    'stmt' => $stmt,
                ]
            ],
        ];

        if ($this->baton !== null) {
            $payload['baton'] = $this->baton;
        }

        try {
            $response = $this->client->post('/v2/pipeline', [
                'json' => $payload,
            ]);

            $data = json_decode($response->getBody()->getContents(), true);

            if (isset($data['baton'])) {
                $this->baton = $data['baton'];
            }

            if (isset($data['base_url'])) {
                $this->baseUrl = $data['base_url'];
            }

            if (isset($data['results']) && is_array($data['results'])) {
                $results = [];
                foreach ($data['results'] as $result) {
                    if ($result['type'] === 'ok') {
                        $results[] = $result['response'];
                    } else {
                        throw new \Exception($result['error']['message'] ?? 'Unknown error');
                    }
                }
                return $results;
            }

            return [];

        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $message = $e->getMessage();
            if ($e->hasResponse()) {
                $response = $e->getResponse();
                $body = json_decode($response->getBody()->getContents(), true);
                $message = $body['error']['message'] ?? $message;
            }
            throw new \Exception('LibSQL error: ' . $message);
        }
    }

    protected function parseResults(array $results): array
    {
        if (empty($results)) {
            return [];
        }

        $response = $results[0] ?? [];
        
        if (!isset($response['cols']) || !isset($response['rows'])) {
            return [];
        }

        $columns = $response['cols'];
        $rows = $response['rows'];

        $output = [];
        foreach ($rows as $row) {
            $parsedRow = [];
            foreach ($row as $index => $value) {
                $columnName = $columns[$index] ?? $index;
                $parsedRow[$columnName] = $this->parseValue($value);
            }
            $output[] = (object) $parsedRow;
        }

        return $output;
    }

    protected function mixed($value)
    {
        return $this->parseValue($value);
    }

    protected function parseValue($value)
    {
        if (is_array($value) && isset($value['type'])) {
            switch ($value['type']) {
                case 'null':
                    return null;
                case 'integer':
                    return (int) ($value['value'] ?? $value);
                case 'real':
                    return (float) ($value['value'] ?? $value);
                case 'text':
                    return (string) ($value['value'] ?? $value);
                case 'blob':
                    return base64_decode($value['base64'] ?? '');
            }
        }

        if (is_string($value) && is_numeric($value)) {
            if (strpos($value, '.') === false) {
                return (int) $value;
            }
            return (float) $value;
        }

        return $value;
    }

    public function transaction(Closure $callback, $attempts = 1)
    {
        $this->beginTransaction();
        
        try {
            $result = $callback($this);
            $this->commit();
            return $result;
        } catch (\Exception $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction()
    {
        $this->execute('BEGIN');
    }

    public function commit()
    {
        $this->execute('COMMIT');
    }

    public function rollBack()
    {
        $this->execute('ROLLBACK');
    }

    public function disconnect()
    {
        if ($this->baton !== null) {
            try {
                $this->client->post('/v2/pipeline', [
                    'json' => [
                        'baton' => $this->baton,
                        'requests' => [
                            ['type' => 'close']
                        ]
                    ],
                ]);
            } catch (\Exception $e) {
            }
            $this->baton = null;
        }
    }

    public function getQueryBuilder(): QueryBuilder
    {
        return new QueryBuilder(
            $this,
            $this->getQueryGrammar(),
            $this->getPostProcessor()
        );
    }

    public function getSchemaBuilder(): SchemaBuilder
    {
        return new SchemaBuilder($this);
    }

    public function table($table, $as = null)
    {
        return $this->getQueryBuilder()->from($table, $as);
    }
}
