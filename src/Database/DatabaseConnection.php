<?php

declare(strict_types=1);

namespace DBTool\Database;

use PDO;
use PDOException;

class DatabaseConnection
{
    private PDO $pdo;

    function __construct(string $configFile)
    {
        $path = realpath(__DIR__ . '/../../config');
        $config = require "$path/$configFile";
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
            echo "Erro ao conectar ao banco de dados: {$e->getMessage()}\n";
            exit(1);
        }
    }

    function getTables(): array
    {
        try {
            $stmt = $this->pdo->query('SHOW TABLES');
            return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            echo "Erro ao consultar as tabelas: {$e->getMessage()}\n";
            exit(1);
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
            echo "Erro ao consultar a tabela '$table': {$e->getMessage()}\n";
            exit(1);
        }
    }

    function tableExists(string $table): bool
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $stmt = $this->pdo->query("SHOW TABLES LIKE '$table'");
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            echo "Erro ao consultar a tabela '$table': {$e->getMessage()}\n";
            exit(1);
        }
    }

    function dropTable(string $table): void
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $this->pdo->exec("DROP TABLE IF EXISTS $table");
        } catch (PDOException $e) {
            echo "Erro ao excluir a tabela '$table': {$e->getMessage()}\n";
            exit(1);
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
            echo "Erro ao consultar a tabela '$table': {$e->getMessage()}\n";
            exit(1);
        }
    }

    function getTableData(string $table): array
    {
        try {
            $table = $this->sanitize($table, '/^[a-zA-Z0-9_]+$/', 'table');
            $stmt = $this->pdo->query("SELECT * FROM $table");
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            echo "Erro ao consultar a tabela '$table': {$e->getMessage()}\n";
            exit(1);
        }
    }

    function query(string $sql): array
    {
        try {
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            echo "Erro ao executar a consulta: {$e->getMessage()}\n";
            exit(1);
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
            $sql = "INSERT INTO `$table` (" .
                implode(', ', $columns) .
                ") VALUES (" .
                implode(', ', $placeholders) .
                ")";
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare($sql);
            foreach ($data as $row) {
                $stmt->execute($row);
            }
            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            echo "Erro ao inserir na tabela '$table': {$e->getMessage()}\n";
            exit(1);
        }
    }

    private function sanitize(
        string $value,
        string $pattern,
        string $fieldName
    ): string {
        if (!preg_match($pattern, $value)) {
            echo "Parâmetro '$fieldName' contém caracteres inválidos.\n";
            exit(1);
        }
        return $value;
    }
}
