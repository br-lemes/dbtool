<?php

declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;

class CopyCommand
{
    private DatabaseConnection $db1;
    private DatabaseConnection $db2;
    private ?string $tableName;

    function __construct(array $args)
    {
        $configFile1 = $args[2] ?? null;
        $configFile2 = $args[3] ?? null;
        $this->tableName = $args[4] ?? null;

        if (!$configFile1 || !$configFile2 || !$this->tableName) {
            $this->showUsage($args[0]);
            exit(1);
        }

        $this->db1 = new DatabaseConnection($configFile1);
        $this->db2 = new DatabaseConnection($configFile2);
    }

    function run(): void
    {
        if ($this->db2->tableExists($this->tableName)) {
            echo "A tabela '$this->tableName' ja existe no destino.\n";
            echo "Deseja sobrescrever? [s/N] ";
            $handle = fopen("php://stdin", "r");
            $line = fgets($handle);

            if (!in_array(trim($line), ['S', 's', 'Y', 'y'])) {
                echo "Operação cancelada.\n";
                exit(1);
            }

            $this->db2->dropTable($this->tableName);
        }

        $schema = $this->db1->getTableSchema($this->tableName);
        $this->db2->query($schema);
        $data = $this->db1->getTableData($this->tableName);
        $this->db2->insertInto($this->tableName, $data);

        echo "Tabela '$this->tableName' copiada com sucesso.\n";
    }

    private function showUsage(string $name)
    {
        $name = basename($name);
        echo <<<END
            Uso: $name cp <configFile1> <configFile2> <tabela>
                Copiar tabela de um banco de dados para outro

                <configFile1>  Arquivo de configuração do primeiro banco
                <configFile2>  Arquivo de configuração do segundo banco
                <tabela>       Tabela para comparar o schema (opcional)
                <campo>        Campo para comparar (opcional, requer tabela)

            END;
    }
}
