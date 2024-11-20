<?php
declare(strict_types=1);

namespace DBTool\Commands;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use DBTool\Database\DatabaseConnection;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;

class ListCommand extends Command
{
    private string $help = <<<HELP
    List database structures at different levels.

    Usage examples:
      <info>ls config1</info>              List all tables in the database
      <info>ls config1 users</info>        Show schema details of users table
      <info>ls config1 users email</info>  Show definition of email field

    Notes:
      - Output is in JSON format for easy parsing
      - Shows complete schema information including indexes
      - Field details include type, length, nullable and default values
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
        if ($input->mustSuggestArgumentValuesFor('field')) {
            $config = $input->getArgument('config');
            $table = $input->getArgument('table');
            $db = new DatabaseConnection($config);
            $suggestions->suggestValues(
                array_filter(array_column($db->getColumns($table), 'Field')),
            );
        }
    }

    protected function configure(): void
    {
        $this->setName('ls')
            ->setDescription('List tables of a database or fields of a table')
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            )
            ->addArgument(
                'table',
                InputArgument::OPTIONAL,
                'Table to show the schema',
            )
            ->addArgument('field', InputArgument::OPTIONAL, 'Field to show');
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $configFile = $input->getArgument('config');
        $tableName = $input->getArgument('table');
        $fieldName = $input->getArgument('field');

        $db = new DatabaseConnection($configFile, $output);

        if ($tableName) {
            $columns = $db->getColumns($tableName);

            if ($fieldName) {
                $filtered = array_values(
                    array_filter(
                        $columns,
                        fn(array $col) => $col['Field'] === $fieldName,
                    ),
                );
                $output->writeln(
                    json_encode(
                        $filtered,
                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                    ),
                );
                return Command::SUCCESS;
            }

            $output->writeln(
                json_encode(
                    $columns,
                    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
                ),
            );
            return Command::SUCCESS;
        }

        $output->writeln(
            json_encode(
                $db->getTables(),
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ),
        );
        return Command::SUCCESS;
    }
}
