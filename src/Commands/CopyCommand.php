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

class CopyCommand extends BaseCommand
{
    private string $help = <<<HELP
    Copies a table including its schema and data to another database.

    Usage examples:
      <info>cp source-db dest-db users</info>    Copy users table with all data
      <info>cp prod-db stage-db products</info>  Copy products table between env

    Notes:
      - Will prompt for confirmation if table exists in destination
      - Copies both table structure (schema) and all data
      - Source and destination can be different database types
    HELP;

    private DatabaseConnection $db1;
    private DatabaseConnection $db2;

    function complete(
        CompletionInput $input,
        CompletionSuggestions $suggestions,
    ): void {
        if (
            $input->mustSuggestArgumentValuesFor('source') ||
            $input->mustSuggestArgumentValuesFor('destination')
        ) {
            $suggestions->suggestValues(
                array_map(
                    fn(string $file): string => basename($file, '.php'),
                    glob(__DIR__ . '/../../config/*.php', GLOB_BRACE),
                ),
            );
        }
        if ($input->mustSuggestArgumentValuesFor('table')) {
            $source = $input->getArgument('source');
            $db = new DatabaseConnection($source);
            $suggestions->suggestValues($db->getTables());
        }
    }

    protected function configure(): void
    {
        $this->setName('cp')
            ->setDescription('Copy table from one database to another')
            ->setHelp($this->help)
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Configuration file of the source database',
            )
            ->addArgument(
                'destination',
                InputArgument::REQUIRED,
                'Configuration file of the destination database',
            )
            ->addArgument(
                'table',
                InputArgument::REQUIRED,
                'Name of the table to be copied',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');
        $tableName = $input->getArgument('table');

        $this->db1 = new DatabaseConnection($source, $output);
        $this->db2 = new DatabaseConnection($destination, $output);
        if ($this->db1->type === $this->db2->type) {
            if ($this->db2->tableExists($tableName)) {
                /** @var QuestionHelper $helper */
                $helper = $this->getHelper('question');
                $question = new ConfirmationQuestion(
                    "Table '$tableName' already exists in the destination. " .
                        'Do you want to overwrite it? (y/N) ',
                    false,
                );

                if (!$helper->ask($input, $output, $question)) {
                    $output->writeln('Operation cancelled.');
                    return Command::FAILURE;
                }

                $this->db2->dropTable($tableName);
            }

            $schema = $this->db1->getTableSchema($tableName);
            $this->db2->exec($schema);
        } else {
            if (!$this->hasCompatibleSchema($tableName)) {
                $output->writeln(
                    'Table schemas are not compatible (column names or order differ).',
                );
                return Command::FAILURE;
            }
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Table '$tableName' may be incompatible. " .
                    'Do you want to clear it? (y/N) ',
                false,
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Operation cancelled.');
                return Command::FAILURE;
            }

            $this->db2->truncateTable($tableName);
        }

        $this->db1->streamTableData(
            $tableName,
            fn($batch) => $this->db2->insertInto($tableName, $batch),
        );

        $output->writeln("Table '$tableName' copied successfully.");
        return Command::SUCCESS;
    }

    private function hasCompatibleSchema(string $table): bool
    {
        $columns1 = $this->db1->getColumns($table, 'native');
        $columns2 = $this->db2->getColumns($table, 'native');
        if ($columns1 === null || $columns2 === null) {
            return false;
        }
        $names1 = array_column($columns1, 'COLUMN_NAME');
        $names2 = array_column($columns2, 'COLUMN_NAME');
        return $names1 === $names2;
    }
}
