<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDO;

abstract class AbstractDatabaseDriver implements DatabaseDriver
{
    use UtilitiesTrait;

    protected array $config;
    protected PDO $pdo;

    function __construct(array $config)
    {
        $this->config = $config + [
            'batchSize' => 1000,
            'password' => null,
            'username' => null,
        ];
    }

    function configureConnection(): void
    {
        // pode ser sobrescrito por drivers específicos se necessário
    }

    function connect(): void
    {
        $this->pdo = new PDO(
            $this->buildDSN(),
            $this->config['username'],
            $this->config['password'],
        );
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->configureConnection();
    }

    function exec(string $sql): int|false
    {
        return $this->pdo->exec($sql);
    }

    function query(string $sql): array
    {
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    protected function sortColumns(array $columns): array
    {
        $id = [];
        $related = [];
        $others = [];
        $special = [];
        $specialOrder = [
            'created_at',
            'refresh_at',
            'updated_at',
            'key_id',
            'token_id',
        ];

        foreach ($columns as $col) {
            $name = $col['COLUMN_NAME'];

            if ($name === 'id') {
                $id[] = $col;
                continue;
            }

            if (in_array($name, $specialOrder)) {
                $special[$name] = $col;
                continue;
            }

            if (str_ends_with($name, '_id')) {
                $related[] = $col;
                continue;
            }

            $others[] = $col;
        }

        usort($related, fn($a, $b) => $a['COLUMN_NAME'] <=> $b['COLUMN_NAME']);
        usort($others, fn($a, $b) => $a['COLUMN_NAME'] <=> $b['COLUMN_NAME']);

        $sortedSpecial = [];
        foreach ($specialOrder as $name) {
            if (isset($special[$name])) {
                $sortedSpecial[] = $special[$name];
            }
        }

        return array_merge($id, $related, $others, $sortedSpecial);
    }

    abstract function buildDSN(): string;
    abstract function buildDumpCommand(array $options = []): string;
    abstract function buildRunCommand(string $script): string;
    abstract function dropAll(): void;
    abstract function dropTable(string $table): void;
    abstract function getColumns(string $table, string $order): array;
    abstract function getKeys(string $table, string $order): array;
    abstract function getTableData(string $table, string $order): array;
    abstract function getTableSchema(string $table): string;
    abstract function getTables(): array;
    abstract function insertInto(string $table, array $data): void;
    abstract function renameTable(string $from, string $to): void;
    abstract function streamTableData(string $table, callable $callback): void;
    abstract function tableExists(string $table): bool;
    abstract function truncateTable(string $table): void;
}
