<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDOException;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseConnection
{
    use UtilitiesTrait;

    public string $type;

    private ?OutputInterface $output;
    private DatabaseDriver $driver;

    private const DRIVERS = [
        'mysql' => MySQLDriver::class,
        'pgsql' => PgSQLDriver::class,
    ];

    function __construct(string $configFile, ?OutputInterface $output = null)
    {
        $this->output = $output;
        $this->errOutput =
            $output instanceof ConsoleOutputInterface
                ? $output->getErrorOutput()
                : $output;

        $path = realpath(__DIR__ . '/../../config');
        $config = require "$path/$configFile.php";

        $driver = $config['driver'] ?? 'mysql';
        if (!array_key_exists($driver, self::DRIVERS)) {
            $this->error("Unsupported driver: $driver");
        }

        $this->driver = new (self::DRIVERS[$driver])($config, $this->errOutput);
        try {
            $this->driver->connect();
        } catch (PDOException $e) {
            $this->error('Error connecting to database', $e);
        }
        $this->type = $driver;
    }

    function exec(string $sql): int|false
    {
        try {
            return $this->driver->exec($sql);
        } catch (PDOException $e) {
            $this->error('Error executing query', $e);
            return false;
        }
    }

    function getTables(): ?array
    {
        try {
            return $this->driver->getTables();
        } catch (PDOException $e) {
            $this->error('Error querying tables', $e);
            return null;
        }
    }

    function getColumns(string $table): ?array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->getColumns($table);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function getKeys(string $table): ?array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->getKeys($table);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function tableExists(string $table): ?bool
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->tableExists($table);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function dropTable(string $table): void
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $this->driver->dropTable($table);
        } catch (PDOException $e) {
            $this->error("Error dropping table '$table'", $e);
        }
    }

    function getTableSchema(string $table): ?string
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->getTableSchema($table);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function getTableData(string $table): ?array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->getTableData($table);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function streamTableData(string $table, callable $callback): void
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $this->driver->streamTableData($table, $callback);
        } catch (PDOException $e) {
            $this->error("Error streaming table '$table'", $e);
        }
    }

    function query(string $sql): ?array
    {
        try {
            return $this->driver->query($sql);
        } catch (PDOException $e) {
            $this->error('Error executing query', $e);
            return null;
        }
    }

    function insertInto(string $table, array $data): void
    {
        if (empty($data)) {
            return;
        }
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $this->driver->insertInto($table, $data);
        } catch (PDOException $e) {
            $this->error("Error inserting into table '$table'", $e);
        }
    }
}
