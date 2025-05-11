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
use Symfony\Component\Console\Question\ConfirmationQuestion;

class MoveCommand extends BaseCommand
{
    private string $help = <<<HELP
    Moves or renames a table within or across databases.

    Usage examples:
      <info>mv config1 old_table new_table</info>  Rename old_table to new_table
      <info>mv config1 config2 table</info>   Move table from config1 to config2

    Notes:
      - Within same database:
        - Prompts if destination table exists
        - Renames table, preserving schema and data
      - Across databases (same type, e.g., MySQL to MySQL):
        - Prompts if destination table exists
        - Copies schema and data, then drops source table
      - Across databases (different types, e.g., MySQL to PostgreSQL):
        - Requires identical column names and order in destination
        - Prompts to clear destination data
        - Copies data without altering destination schema, then drops source
    HELP;

    function complete(
        CompletionInput $input,
        CompletionSuggestions $suggestions,
    ): void {
        $configs = array_map(
            fn(string $file): string => basename($file, '.php'),
            glob(__DIR__ . '/../../config/*.php', GLOB_BRACE),
        );
        if ($input->mustSuggestArgumentValuesFor('config1')) {
            $suggestions->suggestValues($configs);
        } else {
            $last = [];
            $tables = [];
            $config1 = $input->getArgument('config1');
            $argument2 = $input->getArgument('argument2');
            if ($config1) {
                $db = new DatabaseConnection($config1);
                $tables = $db->getTables();
            }
            if ($argument2) {
                $path = realpath(__DIR__ . '/../../config');
                if (file_exists("$path/$argument2.php")) {
                    $last = $db->getTables();
                }
            }
        }
        if ($input->mustSuggestArgumentValuesFor('argument2')) {
            $suggestions->suggestValues(array_merge($configs, $tables));
        }
        if ($input->mustSuggestArgumentValuesFor('argument3')) {
            $suggestions->suggestValues($last);
        }
    }

    protected function configure(): void
    {
        $this->setName('mv')
            ->setDescription('Move or rename a table')
            ->setHelp($this->help)
            ->addArgument(
                'config1',
                InputArgument::REQUIRED,
                'Configuration file for the first database',
            )
            ->addArgument(
                'argument2',
                InputArgument::REQUIRED,
                'Config for the second database or table name',
            )
            ->addArgument(
                'argument3',
                InputArgument::REQUIRED,
                'Table name to move or the new name when renaming',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config1 = $input->getArgument('config1');
        $argument2 = $input->getArgument('argument2');
        $argument3 = $input->getArgument('argument3');

        $db1 = new DatabaseConnection($config1, $output);
        $path = realpath(__DIR__ . '/../../config');

        // Case 1: Rename within same database
        if (!file_exists("$path/$argument2.php")) {
            if ($db1->tableExists($argument3)) {
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    "Table '$argument3' already exists in the database. " .
                        'Do you want to overwrite it? (y/N) ',
                    false,
                );

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('Operation cancelled.');
                    return Command::FAILURE;
                }
                $db1->dropTable($argument3);
            }
            $db1->renameTable($argument2, $argument3);
            $output->writeln(
                "Table '$argument2' renamed to '$argument3' successfully.",
            );
            return Command::SUCCESS;
        }

        // Case 2: Move to another database
        $db2 = new DatabaseConnection($argument2, $output);

        if ($db1->type === $db2->type) {
            if ($db2->tableExists($argument3)) {
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    "Table '$argument3' already exists in destination. " .
                        'Do you want to overwrite it? (y/N) ',
                    false,
                );

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('Operation cancelled.');
                    return Command::FAILURE;
                }
                $db2->dropTable($argument3);
            }

            $schema = $db1->getTableSchema($argument3);
            $db2->exec($schema);
        } else {
            if (!$this->hasCompatibleSchema($db1, $db2, $argument3)) {
                $output->writeln(
                    'Table schemas are not compatible (column names or order differ).',
                );
                return Command::FAILURE;
            }
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Table '$argument3' may be incompatible. " .
                    'Do you want to clear it? (y/N) ',
                false,
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Operation cancelled.');
                return Command::FAILURE;
            }
            $db2->truncateTable($argument3);
        }

        $db1->streamTableData(
            $argument3,
            fn($batch) => $db2->insertInto($argument3, $batch),
        );
        $db1->dropTable($argument3);

        $output->writeln(
            "Table '$argument3' moved successfully to destination database.",
        );
        return Command::SUCCESS;
    }

    private function hasCompatibleSchema(
        DatabaseConnection $db1,
        DatabaseConnection $db2,
        string $table,
    ): bool {
        $columns1 = $db1->getColumns($table, 'native');
        $columns2 = $db2->getColumns($table, 'native');
        if ($columns1 === null || $columns2 === null) {
            return false;
        }
        $names1 = array_column($columns1, 'COLUMN_NAME');
        $names2 = array_column($columns2, 'COLUMN_NAME');
        return $names1 === $names2;
    }
}
