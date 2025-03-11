<?php
declare(strict_types=1);

namespace DBTool\Database;

use Symfony\Component\Console\Output\OutputInterface;

abstract class AbstractServerDriver extends AbstractDatabaseDriver
{
    use UtilitiesTrait;

    protected int $defaultPort;

    function __construct(array $config, int $defaultPort)
    {
        $this->defaultPort = $defaultPort;

        $required = ['host', 'database', 'username'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($config[$field])) {
                $missing[] = $field;
            }
        }
        if (!empty($missing)) {
            $this->error(
                'Missing required server configuration: ' .
                    implode(', ', $missing),
            );
        }

        parent::__construct($config);

        $this->config['host'] = $this->sanitize(
            $this->config['host'],
            '/^[a-zA-Z0-9.-]+$/',
            'host',
        );
        $this->config['port'] = $this->config['port'] ?? $this->defaultPort;
        $this->config['database'] = $this->sanitize(
            $this->config['database'],
            '/^[a-zA-Z0-9_]+$/',
            'database',
        );
        $this->config['username'] = $this->sanitize(
            $this->config['username'],
            '/^[a-zA-Z0-9_]+$/',
            'username',
        );
    }

    abstract function dropTable(string $table): void;
    abstract function getColumns(string $table): array;
    abstract function getKeys(string $table): array;
    abstract function getTableData(string $table): array;
    abstract function getTableSchema(string $table): string;
    abstract function getTables(): array;
    abstract function insertInto(string $table, array $data): void;
    abstract function streamTableData(string $table, callable $callback): void;
    abstract function tableExists(string $table): bool;
}
