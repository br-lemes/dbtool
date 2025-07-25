<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDOException;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseConnection
{
    use UtilitiesTrait;

    public string $type;

    private DatabaseDriver $driver;

    private const DRIVERS = [
        'mysql' => MySQLDriver::class,
        'pgsql' => PgSQLDriver::class,
    ];

    function __construct(string $configFile, ?OutputInterface $output = null)
    {
        $path = realpath(__DIR__ . '/../../config');
        $config = require "$path/$configFile.php";

        $driver = $config['driver'] ?? 'mysql';
        if (!array_key_exists($driver, self::DRIVERS)) {
            $this->error("Unsupported driver: $driver");
        }

        $config['configFile'] = $configFile;
        $this->driver = new (self::DRIVERS[$driver])($config, $output);
        try {
            $this->driver->connect();
        } catch (PDOException $e) {
            $this->error('Error connecting to database', $e);
        }
        $this->type = $driver;
    }

    function buildDumpCommand(array $options = []): string
    {
        return $this->driver->buildDumpCommand($options);
    }

    function buildRunCommand(string $script): string
    {
        return $this->driver->buildRunCommand($script);
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

    function exec(string $sql): int|false
    {
        try {
            return $this->driver->exec($sql);
        } catch (PDOException $e) {
            $this->error('Error executing query', $e);
            return false;
        }
    }

    function getColumns(string $table, string $order): ?array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->getColumns($table, $order);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function getKeys(string $table, string $order): ?array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->getKeys($table, $order);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function getTableData(string $table, string $order): ?array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            return $this->driver->getTableData($table, $order);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
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

    function getTables(): ?array
    {
        try {
            return $this->driver->getTables();
        } catch (PDOException $e) {
            $this->error('Error querying tables', $e);
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

    function query(string $sql): ?array
    {
        try {
            return $this->driver->query($sql);
        } catch (PDOException $e) {
            $this->error('Error executing query', $e);
            return null;
        }
    }

    function renameTable(string $from, string $to): void
    {
        try {
            $from = $this->sanitize($from, '/^[a-zA-Z0-9_]+$/', 'table');
            $to = $this->sanitize($to, '/^[a-zA-Z0-9_]+$/', 'table');
            $this->driver->renameTable($from, $to);
        } catch (PDOException $e) {
            $this->error("Error renaming table '$from' to '$to'", $e);
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

    function truncateTable(string $table): void
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $this->driver->truncateTable($table);
        } catch (PDOException $e) {
            $this->error("Error truncating table '$table'", $e);
        }
    }
}
