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

class DiffCommand extends Command
{
    private string $help = <<<HELP
    Compare database structures at different levels.

    Usage examples:
      <info>diff db1 db2</info>           Compare tables list between databases
                             Shows: '==' same, '!=' & '<>' different schema
                             Shows: '>' only in db1 and '<' only in db2
      <info>diff db1 db2 users</info>     Compare users table schema in detail
      <info>diff db1 db2 users id</info>  Compare just the id field definition

    Notes:
      - Uses difft for colored output showing differences
      - Can compare entire databases, single tables, or specific fields
      - Useful for checking schema consistency across environments
    HELP;

    private DatabaseConnection $db1;
    private DatabaseConnection $db2;
    private ?string $tableName = null;
    private ?string $fieldName = null;

    function complete(
        CompletionInput $input,
        CompletionSuggestions $suggestions,
    ): void {
        if (
            $input->mustSuggestArgumentValuesFor('config1') ||
            $input->mustSuggestArgumentValuesFor('config2')
        ) {
            $suggestions->suggestValues(
                array_map(
                    fn(string $file): string => basename($file, '.php'),
                    glob(__DIR__ . '/../../config/*.php', GLOB_BRACE),
                ),
            );
        }
        if ($input->mustSuggestArgumentValuesFor('table')) {
            $config = $input->getArgument('config1');
            $db = new DatabaseConnection($config);
            $suggestions->suggestValues($db->getTables());
        }
        if ($input->mustSuggestArgumentValuesFor('field')) {
            $config = $input->getArgument('config1');
            $table = $input->getArgument('table');
            $db = new DatabaseConnection($config);
            $suggestions->suggestValues(
                array_filter(
                    array_column($db->getColumns($table), 'COLUMN_NAME'),
                ),
            );
        }
    }

    protected function configure(): void
    {
        $this->setName('diff')
            ->setDescription('Compare two databases or table schemas')
            ->setHelp($this->help)
            ->addArgument(
                'config1',
                InputArgument::REQUIRED,
                'Configuration file for the first database',
            )
            ->addArgument(
                'config2',
                InputArgument::REQUIRED,
                'Configuration file for the second database',
            )
            ->addArgument(
                'table',
                InputArgument::OPTIONAL,
                'Table to compare schema',
            )
            ->addArgument(
                'field',
                InputArgument::OPTIONAL,
                'Field to compare schema',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $config1 = $input->getArgument('config1');
        $config2 = $input->getArgument('config2');
        $this->db1 = new DatabaseConnection($config1, $output);
        $this->db2 = new DatabaseConnection($config2, $output);

        $this->tableName = $input->getArgument('table');
        $this->fieldName = $input->getArgument('field');

        if ($this->tableName) {
            $this->diffTables($output);
        } else {
            $this->diffDatabases($output);
        }

        return Command::SUCCESS;
    }

    private function diffTables(OutputInterface $output): void
    {
        $path = realpath(__DIR__ . '/../..');
        $a = "$path/.a.json";
        $b = "$path/.b.json";

        $columns1 = $this->db1->getColumns($this->tableName);
        $columns2 = $this->db2->getColumns($this->tableName);

        if ($this->fieldName) {
            $columns1 = array_values(
                array_filter(
                    $columns1,
                    fn(array $column) => $column['COLUMN_NAME'] ===
                        $this->fieldName,
                ),
            );
            $columns2 = array_values(
                array_filter(
                    $columns2,
                    fn(array $column) => $column['COLUMN_NAME'] ===
                        $this->fieldName,
                ),
            );
        } else {
            usort(
                $columns1,
                fn($a, $b) => $a['COLUMN_NAME'] <=> $b['COLUMN_NAME'],
            );
            usort(
                $columns2,
                fn($a, $b) => $a['COLUMN_NAME'] <=> $b['COLUMN_NAME'],
            );
        }

        file_put_contents(
            $a,
            json_encode($columns1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
        file_put_contents(
            $b,
            json_encode($columns2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $output->write(shell_exec("difft --color always $a $b"));
    }

    private function diffDatabases(OutputInterface $output): void
    {
        $path = realpath(__DIR__ . '/../..');
        $a = "$path/.a.json";
        $b = "$path/.b.json";

        $tables1 = array_fill_keys($this->db1->getTables(), '==');
        $tables2 = array_fill_keys($this->db2->getTables(), '==');

        $allKeys = array_merge(array_keys($tables1), array_keys($tables2));
        sort($allKeys);

        foreach ($allKeys as $key) {
            if (isset($tables1[$key]) && !isset($tables2[$key])) {
                $tables1[$key] = '>';
                continue;
            }
            if (!isset($tables1[$key]) && isset($tables2[$key])) {
                $tables2[$key] = '<';
                continue;
            }

            $columns1 = $this->db1->getColumns($key);
            $columns2 = $this->db2->getColumns($key);
            usort(
                $columns1,
                fn($a, $b) => $a['COLUMN_NAME'] <=> $b['COLUMN_NAME'],
            );
            usort(
                $columns2,
                fn($a, $b) => $a['COLUMN_NAME'] <=> $b['COLUMN_NAME'],
            );

            if ($columns1 != $columns2) {
                $tables1[$key] = '!=';
                $tables2[$key] = '<>';
            }
        }

        file_put_contents(
            $a,
            json_encode($tables1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
        file_put_contents(
            $b,
            json_encode($tables2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );

        $output->write(shell_exec("difft --color always $a $b"));
    }
}
