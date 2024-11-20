<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDO;
use PDOException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseConnection
{
    private ?OutputInterface $errOutput;
    private PDO $pdo;

    function __construct(string $configFile, ?OutputInterface $output = null)
    {
        $this->errOutput =
            $output instanceof ConsoleOutputInterface
                ? $output->getErrorOutput()
                : $output;

        $path = realpath(__DIR__ . '/../../config');
        $config = require "$path/$configFile.php";
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
        $dsn = "mysql:host={$host};dbname={$database};charset=utf8mb4";
        try {
            $this->pdo = new PDO($dsn, $username, $config['password']);
        } catch (PDOException $e) {
            $this->error('Error connecting to database', $e);
        }
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
            $stmt = $this->pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(
                fn(array $column) => [
                    'Field' => $column['Field'],
                    'Type' => $column['Type'],
                    'Null' => $column['Null'],
                    'Key' => $column['Key'],
                    'Default' => is_string($column['Default'])
                        ? str_replace(
                            'current_timestamp()',
                            'CURRENT_TIMESTAMP',
                            $column['Default'],
                        )
                        : $column['Default'],
                    'Extra' => is_string($column['Extra'])
                        ? str_replace(
                            'current_timestamp()',
                            'CURRENT_TIMESTAMP',
                            $column['Extra'],
                        )
                        : $column['Extra'],
                ],
                $columns,
            );
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
            $columns = array_keys($data[0]);
            $placeholders = array_map(fn($col) => ":$col", $columns);
            $sql =
                "INSERT INTO `$table` (" .
                implode(', ', $columns) .
                ') VALUES (' .
                implode(', ', $placeholders) .
                ')';
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            foreach ($data as $row) {
                $stmt->execute($row);
            }
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
