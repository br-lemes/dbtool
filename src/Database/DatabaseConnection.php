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
            $sql = <<<END
                SELECT
                    COLUMN_NAME,
                    IS_NULLABLE,
                    DATA_TYPE,
                    CHARACTER_MAXIMUM_LENGTH,
                    NUMERIC_PRECISION,
                    NUMERIC_SCALE,
                    DATETIME_PRECISION,
                    COLUMN_KEY
                FROM
                    INFORMATION_SCHEMA.COLUMNS
                WHERE
                    TABLE_SCHEMA = '{$this->database}' AND TABLE_NAME = '$table'
            END;
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
