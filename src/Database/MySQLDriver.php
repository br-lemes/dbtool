<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDO;
use PDOException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class MySQLDriver extends AbstractServerDriver
{
    function __construct(array $config, ?OutputInterface $output)
    {
        parent::__construct($config, 3306, $output);
    }

    function buildDSN(array $config): string
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];
        return "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    }

    function dropTable(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS $table");
    }

    function getColumns(string $table): array
    {
        $sql = <<<SQL
            SELECT
                CHARACTER_MAXIMUM_LENGTH,
                CASE
                    WHEN COLUMN_DEFAULT LIKE '%CURRENT_TIMESTAMP%'
                        THEN NULL
                    WHEN COLUMN_DEFAULT = 'NULL'
                        THEN NULL
                        ELSE COLUMN_DEFAULT
                END AS COLUMN_DEFAULT,
                COLUMN_NAME,
                DATA_TYPE,
                CASE
                    WHEN EXTRA LIKE '%AUTO_INCREMENT%'
                        THEN 'YES'
                        ELSE 'NO'
                END AS IS_AUTO_INCREMENT,
                CASE
                    WHEN COLUMN_DEFAULT LIKE '%CURRENT_TIMESTAMP%'
                        THEN 'YES'
                        ELSE 'NO'
                END AS IS_DEFAULT_TIMESTAMP,
                IS_NULLABLE,
                CASE
                    WHEN EXTRA LIKE '%ON UPDATE CURRENT_TIMESTAMP%'
                        THEN 'YES'
                        ELSE 'NO'
                END AS IS_UPDATE_TIMESTAMP,
                CASE
                    WHEN DATA_TYPE IN ('decimal', 'numeric')
                        THEN NUMERIC_PRECISION
                        ELSE NULL
                END AS NUMERIC_PRECISION,
                CASE
                    WHEN DATA_TYPE IN ('decimal', 'numeric')
                        THEN NUMERIC_SCALE
                        ELSE NULL
                END AS NUMERIC_SCALE
            FROM
                INFORMATION_SCHEMA.COLUMNS
            WHERE
                TABLE_SCHEMA = :database AND TABLE_NAME = :table
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':database' => $this->config['database'],
            ':table' => $table,
        ]);
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $columns = $this->sortColumns($columns);

        $typeMap = [
            'bigint' => 'BIGINT',
            'char' => 'CHAR',
            'date' => 'DATE',
            'datetime' => 'TIMESTAMP',
            'decimal' => 'NUMERIC',
            'double' => 'DOUBLE PRECISION',
            'float' => 'REAL',
            'int' => 'INTEGER',
            'longtext' => 'TEXT',
            'mediumint' => 'INTEGER',
            'mediumtext' => 'TEXT',
            'smallint' => 'SMALLINT',
            'text' => 'TEXT',
            'time' => 'TIME',
            'timestamp' => 'TIMESTAMP',
            'tinyint' => 'SMALLINT',
            'varchar' => 'VARCHAR',
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
                    WHEN INDEX_NAME = 'PRIMARY'
                        THEN 'PRIMARY'
                    WHEN NON_UNIQUE = 0
                        THEN 'UNIQUE'
                        ELSE 'INDEX'
                END AS KEY_TYPE,
                INDEX_NAME AS KEY_NAME,
                COLUMN_NAME,
                CASE
                    WHEN (
                        SELECT COUNT(*)
                            FROM INFORMATION_SCHEMA.STATISTICS s2
                            WHERE s2.TABLE_SCHEMA = s1.TABLE_SCHEMA
                                AND s2.TABLE_NAME = s1.TABLE_NAME
                                AND s2.INDEX_NAME = s1.INDEX_NAME
                    ) > 1
                        THEN 'YES'
                        ELSE 'NO'
                END AS IS_COMPOSITE,
                SEQ_IN_INDEX AS KEY_POSITION
            FROM
                INFORMATION_SCHEMA.STATISTICS s1
            WHERE
                TABLE_SCHEMA = :database AND TABLE_NAME = :table
            ORDER BY
                INDEX_NAME, SEQ_IN_INDEX
        SQL;
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':database' => $this->config['database'],
            ':table' => $table,
        ]);
        return $this->sortColumns($stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    function getTableData(string $table): array
    {
        $stmt = $this->pdo->query("SELECT * FROM $table ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    function getTableSchema(string $table): string
    {
        $stmt = $this->pdo->query("SHOW CREATE TABLE $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['Create Table'] ?? '';
    }

    function getTables(): array
    {
        $stmt = $this->pdo->query('SHOW TABLES');
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    function insertInto(string $table, array $data): void
    {
        $batchSize = $this->config['batchSize'];
        $columns = array_map(fn($col) => "`$col`", array_keys($data[0]));
        $singlePlaceholders =
            '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
        $batchPlaceholders = implode(
            ', ',
            array_fill(0, min($batchSize, count($data)), $singlePlaceholders),
        );

        $sql =
            "INSERT INTO `$table` (" .
            implode(', ', $columns) .
            ') VALUES ' .
            $batchPlaceholders;

        $values = [];
        foreach ($data as $row) {
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

    function streamTableData(string $table, callable $callback): void
    {
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM `$table`");
        $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($this->output) {
            $progress = new ProgressBar($this->output, $total);
        }

        $batchSize = $this->config['batchSize'];
        for ($offset = 0; $offset < $total; $offset += $batchSize) {
            $stmt = $this->pdo->query(
                "SELECT * FROM `$table` LIMIT $batchSize OFFSET $offset",
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
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE :table');
        $stmt->execute([':table' => $table]);
        return $stmt->rowCount() > 0;
    }
}
