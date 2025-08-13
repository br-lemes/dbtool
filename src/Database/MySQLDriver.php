<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDO;
use PDOException;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\OutputInterface;

class MySQLDriver extends AbstractDatabaseDriver
{
    private ?OutputInterface $output;

    function __construct(array $config, ?OutputInterface $output)
    {
        $this->output = $output;
        parent::__construct($config);
    }

    function buildDSN(): string
    {
        $host = $this->config['host'];
        $port = $this->config['port'];
        $database = $this->config['database'];
        return "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
    }

    function buildDumpCommand(array $options = []): string
    {
        $defaultsFile = escapeshellarg($this->generateCnf());
        $compact = @$options['compact'] ? '--compact' : '';
        $schemaOnly = @$options['schemaOnly'] ? '-d' : '';
        $database = escapeshellarg($this->config['database']);
        $tableName = @$options['tableName']
            ? escapeshellarg($options['tableName'])
            : '';
        $command = $this->isMariaDB() ? 'mariadb-dump' : 'mysqldump';
        $command .= " --defaults-file=$defaultsFile";
        $command .= " $compact $schemaOnly $database $tableName";
        return $command;
    }

    function buildRunCommand(string $script): string
    {
        $defaultsFile = escapeshellarg($this->generateCnf());
        $script = escapeshellarg($script);
        $database = escapeshellarg($this->config['database']);
        $command = $this->isMariaDB() ? 'mariadb' : 'mysql';
        $command .= " --defaults-file=$defaultsFile $database < $script";
        return $command;
    }

    function dropAll(): void
    {
        $database = $this->config['database'];
        $this->pdo->exec("DROP DATABASE IF EXISTS `$database`");
        $this->pdo->exec("CREATE DATABASE `$database`");
    }

    function dropTable(string $table): void
    {
        $this->pdo->exec("DROP TABLE IF EXISTS `$table`");
    }

    function getColumns(string $table, string $order): array
    {
        $sql = <<<SQL
            SELECT
                CHARACTER_MAXIMUM_LENGTH,
                CASE
                    WHEN COLUMN_DEFAULT LIKE '%CURRENT_TIMESTAMP%'
                        THEN NULL
                    WHEN COLUMN_DEFAULT = 'NULL'
                        THEN NULL
                        ELSE CASE
                            WHEN LEFT(COLUMN_DEFAULT, 1) = "'" AND RIGHT(COLUMN_DEFAULT, 1) = "'"
                                THEN SUBSTRING(COLUMN_DEFAULT, 2, CHAR_LENGTH(COLUMN_DEFAULT) - 2)
                                ELSE COLUMN_DEFAULT
                        END
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
                        THEN CAST(NUMERIC_PRECISION AS SIGNED)
                        ELSE NULL
                END AS NUMERIC_PRECISION,
                CASE
                    WHEN DATA_TYPE IN ('decimal', 'numeric')
                        THEN CAST(NUMERIC_SCALE AS SIGNED)
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
        if ($order === 'custom') {
            $columns = $this->sortColumns($columns);
        }

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
            'tinytext' => 'TEXT',
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

    function getKeys(string $table, string $order): array
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
        $keys = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($order === 'custom') {
            return $this->sortColumns($keys);
        }
        return $keys;
    }

    function getTableData(string $table, string $order): array
    {
        $stmt = $this->pdo->query("SELECT * FROM `$table` ORDER BY id");
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
        $stmt = $this->pdo->query("SHOW CREATE TABLE `$table`");
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
        $destColumns = array_column(
            $this->getColumns($table, 'native'),
            'COLUMN_NAME',
        );
        $columns = array_map(fn($col) => "`$col`", $destColumns);
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
            "INSERT INTO `$table` (" .
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
        $this->pdo->exec("ALTER TABLE `$from` RENAME TO `$to`");
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

    function truncateTable(string $table): void
    {
        $this->pdo->exec("TRUNCATE TABLE `$table`");
    }

    private function generateCnf(): string
    {
        $path = realpath(__DIR__ . '/../../config');
        $cnfFile = "$path/{$this->config['configFile']}.cnf";
        $host = $this->config['host'];
        $port = $this->config['port'] ?? 3306;
        $user = $this->config['username'];
        $password = $this->config['password'] ?? '';
        $cnfContent = <<<END
        [client]
        host=$host
        port=$port
        user=$user
        password=$password
        END;
        file_put_contents($cnfFile, $cnfContent);
        chmod($cnfFile, 0600);
        return $cnfFile;
    }

    private function isMariaDB(): bool
    {
        return strpos(
            $this->pdo->query('SELECT VERSION()')->fetchColumn(),
            'MariaDB',
        ) !== false;
    }
}
