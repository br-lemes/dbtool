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

class CopyCommand extends Command
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

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $source = $input->getArgument('source');
        $destination = $input->getArgument('destination');
        $tableName = $input->getArgument('table');

        $db1 = new DatabaseConnection($source, $output);
        $db2 = new DatabaseConnection($destination, $output);
        if ($db1->type !== $db2->type) {
            $output->writeln(
                'Source and destination databases must be of the same type.',
            );
            return Command::FAILURE;
        }

        if ($db2->tableExists($tableName)) {
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

            $db2->dropTable($tableName);
        }

        $schema = $db1->getTableSchema($tableName);
        $db2->exec($schema);

        $db1->streamTableData(
            $tableName,
            fn($batch) => $db2->insertInto($tableName, $batch),
        );

        $output->writeln("Table '$tableName' copied successfully.");
        return Command::SUCCESS;
    }
}
