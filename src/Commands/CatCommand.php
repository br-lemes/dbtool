<?php

declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;

class CatCommand
{
    private DatabaseConnection $db1;
    private ?DatabaseConnection $db2;
    private ?string $tableName;
    private ?string $query;

    function __construct(array $args)
    {
        $configFile1 = $args[2] ?? null;
        $configFile2 = $args[3] ?? null;
        if (!$configFile1 || !$configFile2) {
            $this->showUsage($args[0]);
            exit(1);
        }

        $path = realpath(__DIR__ . '/../../config');
        if (!file_exists("$path/$configFile2")) {
            $tableOrQuery = $configFile2;
            $configFile2 = null;
        } else {
            $tableOrQuery = $args[4] ?? null;
        }

        if (!$tableOrQuery) {
            $this->showUsage($args[0]);
            exit(1);
        }

        if (preg_match('/^[a-zA-Z0-9_]+$/', $tableOrQuery)) {
            $this->tableName = $tableOrQuery;
            $this->query = null;
        } else {
            $this->tableName = null;
            $this->query = $tableOrQuery;
        }

        $this->db1 = new DatabaseConnection($configFile1);
        $this->db2 = $configFile2 ? new DatabaseConnection($configFile2) : null;
    }

    function run(): void
    {
        if ($this->db2) {
            $this->compare();
        } else {
            $this->print();
        }
    }

    private function print(): void
    {
        if ($this->query) {
            $data = $this->db1->query($this->query);
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            $data = $this->db1->getTableData($this->tableName);
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
    }

    private function compare(): void
    {
        $path = realpath(__DIR__ . '/../..');
        $a = "$path/.a.json";
        $b = "$path/.b.json";
        if ($this->query) {
            $data1 = $this->db1->query($this->query);
            $data2 = $this->db2->query($this->query);
        } else {
            $data1 = $this->db1->getTableData($this->tableName);
            $data2 = $this->db2->getTableData($this->tableName);
        }
        file_put_contents($a, json_encode(
            $data1,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
        file_put_contents($b, json_encode(
            $data2,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        ));
        echo shell_exec("difft --color always $a $b");
    }

    private function showUsage(string $name)
    {
        $name = basename($name);
        echo <<<END
            Uso: $name cat <configFile1> [<configFile2>] <tabela|query>
                Mostrar ou comparar dados de uma tabela

                <configFile1>  Arquivo de configuração do primeiro banco
                <configFile2>  Arquivo de configuração do segundo (opcional)
                <tabela>       Tabela para mostrar ou comparar os dados
                <query>        Query SQL para mostrar ou comparar os dados
            END;
    }
}
