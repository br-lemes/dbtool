<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDO;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class PgSQLDriver extends AbstractServerDriver
{
    use UtilitiesTrait;

    function __construct(array $config, ?OutputInterface $errOutput)
    {
        parent::__construct($config, 5432, $errOutput);
        $this->config['schema'] = $this->sanitize(
            $this->config['schema'] ?? 'public',
            '/^[a-zA-Z0-9_]+$/',
            'schema',
        );
    }

    function buildDSN(array $config): string
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];
        return "pgsql:host=$host;port=$port;dbname=$database;options='--client_encoding=UTF8'";
    }

    function dropTable(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS $this->config[schema].$table");
    }

    function getColumns(string $table): array
    {
        $sql = <<<SQL
            SELECT
                character_maximum_length AS "CHARACTER_MAXIMUM_LENGTH",
                CASE
                    WHEN column_default = 'CURRENT_TIMESTAMP'
                        THEN NULL
                    WHEN column_default LIKE '%nextval%'
                        THEN NULL
                        ELSE column_default
                END AS "COLUMN_DEFAULT",
                column_name AS "COLUMN_NAME",
                data_type AS "DATA_TYPE",
                CASE
                    WHEN column_default LIKE '%nextval%'
                        THEN 'YES'
                        ELSE 'NO'
                END AS "IS_AUTO_INCREMENT",
                CASE
                    WHEN column_default = 'CURRENT_TIMESTAMP'
                        THEN 'YES'
                        ELSE 'NO'
                END AS "IS_DEFAULT_TIMESTAMP",
                is_nullable AS "IS_NULLABLE",
                CASE
                    WHEN column_name IN ('refresh_at', 'updated_at')
                        THEN 'YES'
                        ELSE 'NO'
                END AS "IS_UPDATE_TIMESTAMP",
                CASE
                    WHEN data_type IN ('decimal', 'numeric')
                        THEN numeric_precision
                        ELSE NULL
                END AS "NUMERIC_PRECISION",
                CASE
                    WHEN data_type IN ('decimal', 'numeric')
                        THEN numeric_scale
                        ELSE NULL
                END AS "NUMERIC_SCALE"
            FROM
                information_schema.columns
            WHERE
                table_schema = :schema AND table_name = :table
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':schema' => $this->config['schema'],
            ':table' => $table,
        ]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $columns = $this->sortColumns($columns);

        $typeMap = [
            'bigint' => 'BIGINT',
            'character varying' => 'VARCHAR',
            'character' => 'CHAR',
            'date' => 'DATE',
            'double precision' => 'DOUBLE PRECISION',
            'integer' => 'INTEGER',
            'mediumint' => 'INTEGER',
            'numeric' => 'NUMERIC',
            'real' => 'REAL',
            'smallint' => 'SMALLINT',
            'text' => 'TEXT',
            'time with time zone' => 'TIME',
            'time without time zone' => 'TIME',
            'timestamp with time zone' => 'TIMESTAMP',
            'timestamp without time zone' => 'TIMESTAMP',
            'tinyint' => 'SMALLINT',
        ];
        foreach ($columns as &$col) {
            if (!isset($col['DATA_TYPE'])) {
                continue;
            }
            $col['DATA_TYPE'] =
                $typeMap[strtolower($col['DATA_TYPE'])] ?? $col['DATA_TYPE'];
        }
        return $columns;
    }

    function getKeys(string $table): array
    {
        $sql = <<<SQL
            SELECT
                CASE
                    WHEN i.indisprimary
                        THEN 'PRIMARY'
                    WHEN i.indisunique
                        THEN 'UNIQUE'
                        ELSE 'INDEX'
                END AS "KEY_TYPE",
                ic.relname AS "KEY_NAME",
                a.attname AS "COLUMN_NAME",
                CASE
                    WHEN array_length(i.indkey, 1) > 1
                        THEN 'YES'
                        ELSE 'NO'
                END AS "IS_COMPOSITE",
                array_position(i.indkey, a.attnum) + 1 AS "KEY_POSITION"
            FROM
                pg_index i
            JOIN
                pg_class t ON t.oid = i.indrelid
            JOIN
                pg_class ic ON ic.oid = i.indexrelid
            JOIN
                pg_attribute a ON a.attrelid = t.oid
                    AND a.attnum = ANY(i.indkey)
            WHERE
                t.relname = :table
                    AND t.relnamespace =
                        (SELECT oid FROM pg_namespace WHERE nspname = :schema)
            ORDER BY
                ic.relname, array_position(i.indkey, a.attnum)
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':schema' => $this->config['schema'],
            ':table' => $table,
        ]);
        return $this->sortColumns($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    function getTableData(string $table): array
    {
        $sql = "SELECT * FROM $this->config[schema].$table ORDER BY id";
        $stmt = $this->pdo->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    function getTableSchema(string $table): string
    {
        throw new RuntimeException('Not implemented for PostgreSQL');
    }

    function getTables(): array
    {
        $sql = <<<SQL
            SELECT table_name
            FROM information_schema.tables
            WHERE table_schema = :schema
            ORDER BY table_name
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':schema' => $this->config['schema']]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    function insertInto(string $table, array $data): void
    {
        throw new RuntimeException('Not implemented for PostgreSQL');
    }

    function streamTableData(string $table, callable $callback): void
    {
        throw new RuntimeException('Not implemented for PostgreSQL');
    }

    function tableExists(string $table): bool
    {
        $sql = <<<SQL
            SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = :schema AND table_name = :table
            )
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':schema' => $this->config['schema'],
            ':table' => $table,
        ]);
        return $stmt->fetchColumn() === true;
    }
}
