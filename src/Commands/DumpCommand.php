<?php
declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DumpCommand extends BaseCommand
{
    private string $help = <<<HELP
    Dumps the database or a specific table using mysqldump or pg_dump.

    Usage examples:
      <info>dump config1</info>              Dump the entire database
      <info>dump config1 users</info>        Dump only the users table
      <info>dump config1 -s</info>           Dump only the schema of the database
      <info>dump config1 users -s</info>     Dump only the schema of the users table
      <info>dump config1 -o dump.sql</info>  Dump to a specific file

    Notes:
      - Requires mysqldump for MySQL or pg_dump for PostgreSQL to be installed
      - When --schema-only is used, only the table structure is dumped
      - Output defaults to stdout unless --output is specified
      - Table names can only contain letters, numbers, and underscores
    HELP;

    function complete(
        CompletionInput $input,
        CompletionSuggestions $suggestions,
    ): void {
        if ($input->mustSuggestArgumentValuesFor('config')) {
            $suggestions->suggestValues(
                array_map(
                    fn(string $file): string => basename($file, '.php'),
                    glob(__DIR__ . '/../../config/*.php', GLOB_BRACE),
                ),
            );
        }
        if ($input->mustSuggestArgumentValuesFor('table')) {
            $config = $input->getArgument('config');
            $db = new DatabaseConnection($config);
            $suggestions->suggestValues($db->getTables());
        }
    }

    protected function configure(): void
    {
        $this->setName('dump')
            ->setDescription(
                'Dump database or table using mysqldump or pg_dump',
            )
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            )
            ->addArgument(
                'table',
                InputArgument::OPTIONAL,
                'Name of the table to dump',
            )
            ->addOption(
                'schema-only',
                's',
                InputOption::VALUE_NONE,
                'Dump only the schema without data',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file for the dump (default: stdout)',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getArgument('config');
        $db = new DatabaseConnection($config);
        $outputFile = $input->getOption('output');

        $command = $db->buildDumpCommand([
            'tableName' => $input->getArgument('table'),
            'schemaOnly' => $input->getOption('schema-only'),
        ]);

        if ($outputFile) {
            $command .= ' > ' . escapeshellarg($outputFile);
        }

        $output->writeln(
            "<comment>Executing: $command</comment>",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $commandOutput = [];
        $resultCode = 0;
        exec("$command 2>&1", $commandOutput, $resultCode);

        if ($resultCode !== 0) {
            $output->writeln(implode("\n", $commandOutput));
            return Command::FAILURE;
        }
        if ($outputFile) {
            $output->writeln("Database dumped successfully to '$outputFile'.");
        } else {
            $output->write(implode("\n", $commandOutput));
        }
        return Command::SUCCESS;
    }
}
