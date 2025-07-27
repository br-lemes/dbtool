<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDO;
use PDOException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class PgSQLDriver extends AbstractServerDriver
{
    use UtilitiesTrait;

    private ?OutputInterface $output;

    function __construct(array $config, ?OutputInterface $output)
    {
        $this->output = $output;
        parent::__construct($config, 5432);
        $this->config['schema'] = $this->sanitize(
            $this->config['schema'] ?? 'public',
            '/^[a-zA-Z0-9_]+$/',
            'schema',
        );
    }

    function buildDSN(): string
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];
        return "pgsql:host=$host;port=$port;dbname=$database;options='--client_encoding=UTF8'";
    }

    function buildDumpCommand(array $options = []): string
    {
        $pgpassFile = $this->generatePgpass();
        $host = escapeshellarg($this->config['host']);
        $port = escapeshellarg((string) $this->config['port'] ?? 5432);
        $user = escapeshellarg($this->config['username']);
        $database = escapeshellarg($this->config['database']);
        $schema = escapeshellarg($this->config['schema'] ?? 'public');
        $schemaOnly = @$options['schemaOnly'] ? '-s' : '';
        $tableName = @$options['tableName']
            ? '-t ' . escapeshellarg($options['tableName'])
            : '';
        $command = 'PGPASSFILE=' . escapeshellarg($pgpassFile) . ' pg_dump ';
        $command .= "-h $host -p $port -U $user -d $database -n $schema";
        $command .= " $schemaOnly $tableName";
        return $command;
    }

    function buildRunCommand(string $script): string
    {
        $pgpassFile = $this->generatePgpass();
        $script = escapeshellarg($script);
        $host = escapeshellarg($this->config['host']);
        $port = escapeshellarg((string) $this->config['port'] ?? 5432);
        $user = escapeshellarg($this->config['username']);
        $database = escapeshellarg($this->config['database']);
        $command = 'PGPASSFILE=' . escapeshellarg($pgpassFile) . ' psql ';
        $command .= "-h $host -p $port -U $user -d $database -f $script";
        return $command;
    }

    function dropAll(): void
    {
        $schema = $this->config['schema'];
        $this->pdo->exec("DROP SCHEMA IF EXISTS \"$schema\" CASCADE");
        $this->pdo->exec("CREATE SCHEMA \"$schema\"");
    }

    function dropTable(string $table): void
    {
        $this->pdo->exec(
            "DROP TABLE IF EXISTS \"{$this->config['schema']}\".\"$table\"",
        );
    }

    function getColumns(string $table, string $order): array
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
        if ($order === 'custom') {
            $columns = $this->sortColumns($columns);
        }

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

    function getKeys(string $table, string $order): array
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
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($order === 'custom') {
            return $this->sortColumns($keys);
        }
        return $keys;
    }

    function getTableData(string $table, string $order): array
    {
        $schemaTable = "\"{$this->config['schema']}\".\"$table\"";
        $sql = "SELECT * FROM $schemaTable ORDER BY id";
        $stmt = $this->pdo->query($sql);
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($order === 'custom' && !empty($data)) {
            $keys = array_column(
                $this->getColumns($table, 'custom'),
                'COLUMN_NAME',
            );
            $sorted = [];
            foreach ($data as $row) {
                $sortedRow = [];
                foreach ($keys as $key) {
                    $sortedRow[$key] = $row[$key];
                }
                $sorted[] = $sortedRow;
            }
            return $sorted;
        }
        return $data;
    }

    function getTableSchema(string $table): string
    {
        $schemaTable = "\"{$this->config['schema']}\".\"$table\"";
        $sqlParts = ["CREATE TABLE $schemaTable ("];

        $columnsSql = <<<SQL
            SELECT
                column_name,
                data_type,
                character_maximum_length,
                numeric_precision,
                numeric_scale,
                is_nullable,
                column_default
            FROM
                information_schema.columns
            WHERE
                table_schema = :schema AND table_name = :table
            ORDER BY
                ordinal_position
        SQL;
        $stmt = $this->pdo->prepare($columnsSql);
        $stmt->execute([
            ':schema' => $this->config['schema'],
            ':table' => $table,
        ]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $columnDefs = [];
        foreach ($columns as $col) {
            $def = "    \"{$col['column_name']}\" ";

            if (strpos($col['column_default'] ?? '', 'nextval') !== false) {
                $col['data_type'] =
                    $col['data_type'] === 'bigint' ? 'bigserial' : 'serial';
                $col['column_default'] = null;
            }

            $type = $col['data_type'];
            if ($col['character_maximum_length']) {
                $type .= "({$col['character_maximum_length']})";
            } elseif ($col['numeric_precision'] && $col['numeric_scale']) {
                $type .= "({$col['numeric_precision']}, {$col['numeric_scale']})";
            }
            $def .= $type;

            if ($col['is_nullable'] === 'NO') {
                $def .= ' NOT NULL';
            }

            if ($col['column_default'] !== null) {
                $def .= " DEFAULT {$col['column_default']}";
            }

            $columnDefs[] = $def;
        }

        $constraintsSql = <<<SQL
            SELECT
                CASE
                    WHEN i.indisprimary
                        THEN 'PRIMARY KEY'
                    WHEN i.indisunique
                        THEN 'UNIQUE'
                        ELSE NULL
                END AS key_type,
                array_agg(a.attname) AS column_names
            FROM
                pg_index i
            JOIN
                pg_class t ON t.oid = i.indrelid
            JOIN
                pg_attribute a ON a.attrelid = t.oid
                    AND a.attnum = ANY(i.indkey)
            WHERE
                t.relname = :table
                    AND t.relnamespace =
                        (SELECT oid FROM pg_namespace WHERE nspname = :schema)
                    AND (i.indisprimary OR i.indisunique)
            GROUP BY
                i.indisprimary, i.indisunique
        SQL;
        $stmt = $this->pdo->prepare($constraintsSql);
        $stmt->execute([
            ':schema' => $this->config['schema'],
            ':table' => $table,
        ]);
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($keys as $key) {
            $columnNames = explode(',', trim($key['column_names'], '{}'));
            $columnsList = implode(
                ', ',
                array_map(fn($col) => "\"$col\"", $columnNames),
            );
            if ($key['key_type'] === 'PRIMARY KEY') {
                $columnDefs[] = "    CONSTRAINT \"{$table}_pkey\" {$key['key_type']} ($columnsList)";
            } elseif ($key['key_type'] === 'UNIQUE') {
                $constraintName = $table . '_' . implode('_', $columnNames);
                $columnDefs[] = "    CONSTRAINT \"$constraintName\" {$key['key_type']} ($columnsList)";
            }
        }

        $sqlParts[] = implode(",\n", $columnDefs);
        $sqlParts[] = ');';

        $indexesSql = <<<SQL
            SELECT
                ic.relname AS index_name,
                array_agg(a.attname) AS column_names
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
                    AND NOT i.indisprimary AND NOT i.indisunique
            GROUP BY
                ic.relname
        SQL;
        $stmt = $this->pdo->prepare($indexesSql);
        $stmt->execute([
            ':schema' => $this->config['schema'],
            ':table' => $table,
        ]);
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($indexes as $index) {
            $columnNames = explode(',', trim($index['column_names'], '{}'));
            $columnsList = implode(
                ', ',
                array_map(fn($col) => "\"$col\"", $columnNames),
            );
            $indexName = $table . '_' . implode('_', $columnNames);
            $sqlParts[] = "CREATE INDEX \"$indexName\" ON $schemaTable ($columnsList);";
        }

        return implode("\n", $sqlParts);
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
        $batchSize = $this->config['batchSize'];
        $destColumns = array_column(
            $this->getColumns($table, 'native'),
            'COLUMN_NAME',
        );
        $columns = array_map(fn($col) => "\"$col\"", $destColumns);
        $reorderedData = array_map(function ($row) use ($destColumns) {
            $reordered = [];
            foreach ($destColumns as $col) {
                $reordered[$col] = $row[$col] ?? null;
            }
            return $reordered;
        }, $data);
        $singlePlaceholders =
            '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $batchPlaceholders = implode(
            ', ',
            array_fill(0, min($batchSize, count($data)), $singlePlaceholders),
        );

        $sql =
            "INSERT INTO \"{$this->config['schema']}\".\"$table\" (" .
            implode(', ', $columns) .
            ') VALUES ' .
            $batchPlaceholders;

        $values = [];
        foreach ($reorderedData as $row) {
            foreach ($row as $value) {
                $values[] = $value;
            }
        }
        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    function renameTable(string $from, string $to): void
    {
        $this->pdo->exec(
            "ALTER TABLE \"{$this->config['schema']}\".\"$from\" RENAME TO \"$to\"",
        );
    }

    function streamTableData(string $table, callable $callback): void
    {
        $schemaTable = "\"{$this->config['schema']}\".\"$table\"";
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM $schemaTable");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($this->output) {
            $progress = new ProgressBar($this->output, $total);
        }

        $batchSize = $this->config['batchSize'];
        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $stmt = $this->pdo->query(
                "SELECT * FROM $schemaTable LIMIT $batchSize OFFSET $offset",
            );
            $batch = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (empty($batch)) {
                break;
            }
            $callback($batch);
            if ($this->output) {
                $progress->advance(count($batch));
            }
        }
        if ($this->output) {
            $progress->finish();
            $this->output->writeln('');
        }
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

    function truncateTable(string $table): void
    {
        $this->pdo->exec(
            "TRUNCATE TABLE \"{$this->config['schema']}\".\"$table\"",
        );
    }

    private function generatePgpass(): string
    {
        $path = realpath(__DIR__ . '/../../config');
        $pgpassFile = "$path/{$this->config['configFile']}.pgpass";
        $host = $this->config['host'];
        $port = $this->config['port'] ?? 5432;
        $user = $this->config['username'];
        $password = $this->config['password'] ?? '';
        $pgpassContent = "$host:$port:{$this->config['database']}:$user:$password";
        file_put_contents($pgpassFile, $pgpassContent);
        chmod($pgpassFile, 0600);
        return $pgpassFile;
    }
}
