<?php
declare(strict_types=1);

namespace DBTool\Database;

interface DatabaseDriver
{
    function buildDumpCommand(array $options = []): string;
    function connect(): void;
    function dropTable(string $table): void;
    function exec(string $sql): int|false;
    function getColumns(string $table, string $order): array;
    function getKeys(string $table, string $order): array;
    function getTableData(string $table, string $order): array;
    function getTableSchema(string $table): string;
    function getTables(): array;
    function insertInto(string $table, array $data): void;
    function query(string $sql): array;
    function renameTable(string $from, string $to): void;
    function streamTableData(string $table, callable $callback): void;
    function tableExists(string $table): bool;
    function truncateTable(string $table): void;
}
