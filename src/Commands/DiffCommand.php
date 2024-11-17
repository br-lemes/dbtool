<?php

declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;

class DiffCommand
{
    private DatabaseConnection $db1;
    private DatabaseConnection $db2;
    private ?string $tableName;
    private ?string $fieldName;

    function __construct(array $args)
    {
        $configFile1 = $args[2] ?? null;
        $configFile2 = $args[3] ?? null;
        $this->tableName = $args[4] ?? null;
        $this->fieldName = $args[5] ?? null;

        if (!$configFile1 || !$configFile2) {
            $this->showUsage($args[0]);
            exit(1);
        }

        $this->db1 = new DatabaseConnection($configFile1);
        $this->db2 = new DatabaseConnection($configFile2);
    }

    function run(): void
    {
        if ($this->tableName) {
            $this->diffTables();
        } else {
            $this->diffDatabases();
        }
    }

    private function diffTables(): void
    {
        $path = realpath(__DIR__ . '/../..');
        $a = "$path/.a.json";
        $b = "$path/.b.json";
        $columns1 = $this->db1->getColumns($this->tableName);
        $columns2 = $this->db2->getColumns($this->tableName);
        if ($this->fieldName) {
            $columns1 = array_values(array_filter(
                $columns1,
                fn(array $column) => $column['Field'] === $this->fieldName
            ));
            $columns2 = array_values(array_filter(
                $columns2,
                fn(array $column) => $column['Field'] === $this->fieldName
            ));
        } else {
            usort($columns1, fn($a, $b) => $a['Field'] <=> $b['Field']);
            usort($columns2, fn($a, $b) => $a['Field'] <=> $b['Field']);
        }
        file_put_contents($a, json_encode(
            $columns1,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
        file_put_contents($b, json_encode(
            $columns2,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
        echo shell_exec("difft --color always $a $b");
    }

    private function diffDatabases(): void
    {
        $path = realpath(__DIR__ . '/../..');
        $a = "$path/.a.json";
        $b = "$path/.b.json";
        $tables1 = array_fill_keys($this->db1->getTables(), '==');
        $tables2 = array_fill_keys($this->db2->getTables(), '==');
        $allKeys = array_merge(array_keys($tables1), array_keys($tables2));
        sort($allKeys);
        foreach ($allKeys as $key) {
            if (isset($tables1[$key]) && !isset($tables2[$key])) {
                $tables1[$key] = '>';
                continue;
            }
            if (!isset($tables1[$key]) && isset($tables2[$key])) {
                $tables2[$key] = '<';
                continue;
            }
            $columns1 = $this->db1->getColumns($key);
            $columns2 = $this->db2->getColumns($key);
            usort($columns1, fn($a, $b) => $a['Field'] <=> $b['Field']);
            usort($columns2, fn($a, $b) => $a['Field'] <=> $b['Field']);
            if ($columns1 != $columns2) {
                $tables1[$key] = '!=';
                $tables2[$key] = '<>';
            }
        }
        file_put_contents($a, json_encode(
            $tables1,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
        file_put_contents($b, json_encode(
            $tables2,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
        echo shell_exec("difft --color always $a $b");
    }

    private function showUsage(string $name): void
    {
        $name = basename($name);
        echo <<<END
            Uso: $name diff <configFile1> <configFile2> [<tabela>] [<campo>]
                Comparar dois bancos de dados ou schema de tabelas

                <configFile1>  Arquivo de configuração do primeiro banco
                <configFile2>  Arquivo de configuração do segundo banco
                <tabela>       Tabela para comparar o schema (opcional)
                <campo>        Campo para comparar (opcional, requer tabela)

            END;
    }
}
