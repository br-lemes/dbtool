<?php
declare(strict_types=1);

namespace DBTool\Database;

use DBTool\Traits\ConfigTrait;
use PDOException;
use Symfony\Component\Console\Output\OutputInterface;

class DatabaseConnection
{
    use ConfigTrait;

    public string $type;

    private DatabaseDriver $driver;

    function __construct(string $configFile, ?OutputInterface $output = null)
    {
        $config = $this->getConfig($configFile);
        $config['configFile'] = $configFile;
        $driver = $config['driver'];
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

    function dropAll(): void
    {
        try {
            $this->driver->dropAll();
        } catch (PDOException $e) {
            $this->error('Error dropping all tables', $e);
        }
    }

    function dropTable(string $table): void
    {
        try {
            $this->assertTable($table);
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
            $this->assertTable($table);
            return $this->driver->getColumns($table, $order);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function getKeys(string $table, string $order): ?array
    {
        try {
            $this->assertTable($table);
            return $this->driver->getKeys($table, $order);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function getTableData(string $table, string $order): ?array
    {
        try {
            $this->assertTable($table);
            return $this->driver->getTableData($table, $order);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function getTableSchema(string $table): ?string
    {
        try {
            $this->assertTable($table);
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
            $this->assertTable($table);
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
            $this->assertTable($from);
            $this->assertTable($to);
            $this->driver->renameTable($from, $to);
        } catch (PDOException $e) {
            $this->error("Error renaming table '$from' to '$to'", $e);
        }
    }

    function streamTableData(string $table, callable $callback): void
    {
        try {
            $this->assertTable($table);
            $this->driver->streamTableData($table, $callback);
        } catch (PDOException $e) {
            $this->error("Error streaming table '$table'", $e);
        }
    }

    function tableExists(string $table): ?bool
    {
        try {
            $this->assertTable($table);
            return $this->driver->tableExists($table);
        } catch (PDOException $e) {
            $this->error("Error querying table '$table'", $e);
            return null;
        }
    }

    function truncateTable(string $table): void
    {
        try {
            $this->assertTable($table);
            $this->driver->truncateTable($table);
        } catch (PDOException $e) {
            $this->error("Error truncating table '$table'", $e);
        }
    }

    private function assertTable(string $table): void
    {
        $this->assertPattern($table, '/^[a-zA-Z0-9_]+$/', 'table');
    }
}
