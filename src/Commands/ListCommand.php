<?php

declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;

class ListCommand
{
    private DatabaseConnection $db;
    private ?string $tableName;
    private ?string $fieldName;

    function __construct(array $args)
    {
        $configFile = $args[2] ?? null;
        $this->tableName = $args[3] ?? null;
        $this->fieldName = $args[4] ?? null;

        if (!$configFile) {
            $this->showUsage($args[0]);
            exit(1);
        }

        $this->db = new DatabaseConnection($configFile);
    }

    function run(?bool $simple = false)
    {
        if ($this->tableName) {
            $columns = $this->db->getColumns($this->tableName);
            if ($simple) {
                echo implode("\n", array_column($columns, 'Field')) . "\n";
                return;
            }
            if ($this->fieldName) {
                echo json_encode(
                    array_values(array_filter(
                        $columns,
                        fn(array $col) => $col['Field'] === $this->fieldName
                    )),
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
                );
                return;
            }
            echo json_encode(
                $this->db->getColumns($this->tableName),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
            return;
        }
        if ($simple) {
            echo implode("\n", $this->db->getTables()) . "\n";
            return;
        }
        echo json_encode(
            $this->db->getTables(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
        );
    }

    private function showUsage(string $name)
    {
        $name = basename($name);
        echo <<<END
            Uso: $name ls <configFile> [<tabela>] [<campo>]
                Listar tabelas de um banco ou campos de uma tabela

            Uso: $name ll <configFile> [<tabela>] [<campo>]
                Listar em JSON tabelas ou schema de uma tabela

                <configFile>  Arquivo de configuração com credenciais do banco
                <tabela>      Tabela para mostrar o schema (opcional)
                <campo>       Campo para mostrar (opcional, requer tabela)

            END;
    }
}
