<?php
declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class RunCommand extends BaseCommand
{
    private string $help = <<<HELP
    Execute an SQL file (including dumps restores) on a database.

    Usage examples:
    <info>run config1 script.sql</info>   Execute script.sql on the config1 database
    <info>run config1 dump.sql</info>     Execute (restore) dump.sql

    Notes:
      - Requires mysql for MySQL or psql for PostgreSQL to be installed
      - The SQL file must be compatible with the target database
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
        if ($input->mustSuggestArgumentValuesFor('script')) {
            $currentValue = $input->getCompletionValue();
            $files = glob("$currentValue*");
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $suggestions->suggestValue("$file/");
                }
                if (is_file($file) && substr($file, -4) === '.sql') {
                    $suggestions->suggestValue($file);
                }
            }
        }
    }

    protected function configure(): void
    {
        $this->setName('run')
            ->setDescription(
                'Execute an SQL file (including dumps restores) on a database',
            )
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            )
            ->addArgument(
                'script',
                InputArgument::REQUIRED,
                'Path to the SQL file to execute or restore',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getArgument('config');
        $db = new DatabaseConnection($config);
        $script = $input->getArgument('script');

        $command = $db->buildRunCommand($script);

        $output->writeln(
            "<comment>Executing: $command</comment>",
            OutputInterface::VERBOSITY_VERBOSE,
        );

        $commandOutput = [];
        $resultCode = 0;
        exec("$command 2>&1", $commandOutput, $resultCode);
        $output->writeln(implode("\n", $commandOutput));

        if ($resultCode !== 0) {
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }
}
