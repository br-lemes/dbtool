<?php
declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;
use DBTool\Traits\ConstTrait;
use DBTool\Traits\HasCompatibleSchemaTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class MoveCommand extends BaseCommand
{
    use ConstTrait;
    use HasCompatibleSchemaTrait;

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
        - Requires identical column names in destination table
        - Prompts to clear destination data
        - Copies data without altering destination schema, then drops source
    HELP;

    private DatabaseConnection $db1;
    private ?DatabaseConnection $db2;
    private InputInterface $input;
    private OutputInterface $output;

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
        $this->input = $input;
        $this->output = $output;

        $config1 = $input->getArgument('config1');
        $argument2 = $input->getArgument('argument2');
        $argument3 = $input->getArgument('argument3');

        $this->db1 = new DatabaseConnection($config1, $output);
        $path = realpath(__DIR__ . '/../../config');

        if (!file_exists("$path/$argument2.php")) {
            return $this->rename($argument2, $argument3);
        }

        $this->db2 = new DatabaseConnection($argument2, $output);

        if ($this->db1->type === $this->db2->type) {
            return $this->moveSameType($argument3);
        }

        return $this->moveDiffType($argument3);
    }

    private function move(string $table): int
    {
        $this->db1->streamTableData(
            $table,
            fn($batch) => $this->db2->insertInto($table, $batch),
        );
        $this->db1->dropTable($table);

        $this->output->writeln(
            "Table '$table' moved successfully to destination database.",
        );
        return Command::SUCCESS;
    }

    private function moveDiffType(string $table): int
    {
        if (!$this->hasCompatibleSchema($table)) {
            $this->output->writeln(self::SCHEMAS_NOT_COMPATIBLE);
            return Command::FAILURE;
        }
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "Table '$table' may be incompatible. " .
                'Do you want to clear it? (y/N) ',
            false,
        );

        if (!$helper->ask($this->input, $this->output, $question)) {
            $this->output->writeln('Operation cancelled.');
            return Command::FAILURE;
        }
        $this->db2->truncateTable($table);
        return $this->move($table);
    }

    private function moveSameType(string $table): int
    {
        if ($this->db2->tableExists($table)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Table '$table' already exists in destination. " .
                    'Do you want to overwrite it? (y/N) ',
                false,
            );

            if (!$helper->ask($this->input, $this->output, $question)) {
                $this->output->writeln(self::CANCELLED);
                return Command::FAILURE;
            }
            $this->db2->dropTable($table);
        }

        $schema = $this->db1->getTableSchema($table);
        $this->db2->exec($schema);
        return $this->move($table);
    }

    private function rename(string $oldName, string $newName): int
    {
        if ($this->db1->tableExists($newName)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "Table '$newName' already exists in the database. " .
                    'Do you want to overwrite it? (y/N) ',
                false,
            );

            if (!$helper->ask($this->input, $this->output, $question)) {
                $this->output->writeln(self::CANCELLED);
                return Command::FAILURE;
            }
            $this->db1->dropTable($newName);
        }
        $this->db1->renameTable($oldName, $newName);
        $this->output->writeln(
            "Table '$oldName' renamed to '$newName' successfully.",
        );
        return Command::SUCCESS;
    }
}
