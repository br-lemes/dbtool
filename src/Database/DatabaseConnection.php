<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDO;
use PDOException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseConnection
{
    private ?OutputInterface $errOutput;
    private ?OutputInterface $output;
    private PDO $pdo;
    private int $batchSize = 1000;
    private string $database;

    function __construct(string $configFile, ?OutputInterface $output = null)
    {
        $this->output = $output;
        $this->errOutput =
            $output instanceof ConsoleOutputInterface
                ? $output->getErrorOutput()
                : $output;

        $path = realpath(__DIR__ . '/../../config');
        $config = require "$path/$configFile.php";
        $this->batchSize = $config['batchSize'] ?? 1000;
        $port = $config['port'] ?? 3306;
        $host = $this->sanitize($config['host'], '/^[a-zA-Z0-9.-]+$/', 'host');
        $database = $this->sanitize(
            $config['database'],
            '/^[a-zA-Z0-9_]+$/',
            'database',
        );
        $username = $this->sanitize(
            $config['username'],
            '/^[a-zA-Z0-9_]+$/',
            'username',
        );
        $dsn = "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4";
        try {
            $this->pdo = new PDO($dsn, $username, $config['password']);
        } catch (PDOException $e) {
            $this->error('Error connecting to database', $e);
        }
        $this->database = $database;
    }

    function getTables(): array
    {
        try {
            $stmt = $this->pdo->query('SHOW TABLES');
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            $this->error('Error querying tables', $e);
        }
    }

    function getColumns(string $table): array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $sql = <<<SQL
                SELECT
                    CHARACTER_MAXIMUM_LENGTH,
                    CASE
                        WHEN COLUMN_DEFAULT LIKE '%CURRENT_TIMESTAMP%'
                            THEN NULL
                        WHEN COLUMN_DEFAULT IS NULL OR COLUMN_DEFAULT = 'NULL'
                            THEN NULL
                        ELSE COLUMN_DEFAULT
                    END AS COLUMN_DEFAULT,
                    COLUMN_KEY,
                    COLUMN_NAME,
                    DATA_TYPE,
                    DATETIME_PRECISION,
                    CASE
                        WHEN EXTRA LIKE '%AUTO_INCREMENT%'
                            THEN 'YES' ELSE 'NO'
                    END AS IS_AUTO_INCREMENT,
                    CASE
                        WHEN COLUMN_DEFAULT LIKE '%CURRENT_TIMESTAMP%'
                            THEN 'YES' ELSE 'NO'
                    END AS IS_DEFAULT_CURRENT_TIMESTAMP,
                    IS_NULLABLE,
                    CASE
                        WHEN EXTRA LIKE '%ON UPDATE CURRENT_TIMESTAMP%'
                            THEN 'YES' ELSE 'NO'
                    END AS IS_ON_UPDATE_CURRENT_TIMESTAMP,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE
                FROM
                    INFORMATION_SCHEMA.COLUMNS
                WHERE
                    TABLE_SCHEMA = '{$this->database}' AND TABLE_NAME = '$table'
            SQL;
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
        }
    }

    function getKeys(string $table): array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
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
                    SEQ_IN_INDEX AS KEY_POSITION
                FROM
                    INFORMATION_SCHEMA.STATISTICS
                WHERE
                    TABLE_SCHEMA = '{$this->database}' AND TABLE_NAME = '$table'
                ORDER BY
                    INDEX_NAME, SEQ_IN_INDEX;
            SQL;
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
        }
    }

    function tableExists(string $table): bool
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
        }
    }

    function dropTable(string $table): void
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        } catch (PDOException $e) {
            $this->error("Error dropping table '$table'", $e);
        }
    }

    function getTableSchema(string $table): string
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $stmt = $this->pdo->query("SHOW CREATE TABLE $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['Create Table'] ?? '';
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
        }
    }

    function getTableData(string $table): array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $stmt = $this->pdo->query("SELECT * FROM $table");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
        }
    }

    function streamTableData(string $table, callable $callback): void
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM `$table`");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            if ($this->output) {
                $progress = new ProgressBar($this->output, $total);
            }

            for ($offset = 0; $offset < $total; $offset += $this->batchSize) {
                $stmt = $this->pdo->query(
                    "SELECT * FROM `$table` LIMIT $this->batchSize OFFSET $offset",
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
        } catch (PDOException $e) {
            $this->error("Error streaming table '$table'", $e);
        }
    }

    function query(string $sql): array
    {
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            $this->error('Error executing query', $e);
        }
    }

    function insertInto(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $columns = array_map(fn($col) => "`$col`", array_keys($data[0]));

            $singlePlaceholders =
                '(' . implode(', ', array_fill(0, count($columns), '?')) . ')';
            $batchPlaceholders = implode(
                ', ',
                array_fill(
                    0,
                    min($this->batchSize, count($data)),
                    $singlePlaceholders,
                ),
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

            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($values);
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            $this->error("Error inserting into table '$table'", $e);
        }
    }

    private function sanitize(
        string $value,
        string $pattern,
        string $fieldName,
    ): string {
        if (!preg_match($pattern, $value)) {
            $this->error("Parameter '$fieldName' contains invalid characters.");
        }
        return $value;
    }

    private function error(string $message, ?PDOException $e = null): void
    {
        if (!$this->errOutput) {
            return;
        }
        $fullMessage = $e ? "$message: {$e->getMessage()}" : $message;
        $formattedBlock = (new FormatterHelper())->formatBlock(
            $fullMessage,
            'error',
            true,
        );
        $this->errOutput->writeln(['', $formattedBlock, '']);
        exit(Command::FAILURE);
    }
}
