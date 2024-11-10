<?php

declare(strict_types=1);

namespace DBTool;

use DBTool\Commands\CatCommand;
use DBTool\Commands\ListCommand;
use DBTool\Commands\CopyCommand;
use DBTool\Commands\DiffCommand;

class DBTool
{
    function run(array $args)
    {
        $command = $args[1] ?? null;

        if (!$command) {
            $this->showUsage($args[0]);
            exit(1);
        }

        switch ($command) {
            case 'cat':
                $cmd = new CatCommand($args);
                $cmd->run();
                break;

            case 'cp':
                $cmd = new CopyCommand($args);
                $cmd->run();
                break;

            case 'diff':
                $cmd = new DiffCommand($args);
                $cmd->run();
                break;

            case 'll':
                $cmd = new ListCommand($args);
                $cmd->run();
                break;

            case 'ls':
                $cmd = new ListCommand($args);
                $cmd->run(true);
                break;

            default:
                echo "Comando não reconhecido.\n";
                $this->showUsage($args[0]);
                exit(1);
        }
    }

    private function showUsage(string $name)
    {
        $name = basename($name);
        echo <<<END
            Uso: $name <comando> [...]

            Comandos disponíveis:
                cat           Mostrar ou comparar dados de uma tabela
                cp            Copiar tabela de um banco de dados para outro
                diff          Comparar dois bancos de dados ou schema de tabelas
                ls            Listar tabelas de um banco ou campos de uma tabela
                ll            Listar em JSON tabelas ou schema de uma tabela

            END;
    }
}
