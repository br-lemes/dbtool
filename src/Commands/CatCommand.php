<?php
declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Exception\InvalidArgumentException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class CatCommand extends Command
{
    private string $help = <<<HELP
    Displays table contents or compares data between databases.

    Usage examples:
      <info>cat config1 users</info>             Show all rows from users table
      <info>cat config1 "SELECT * FROM users WHERE id = 1"</info>
                                    Execute custom SQL query
      <info>cat config1 config2 users</info>
                                    Compare users table data between databases
      <info>cat config1 config2 "SELECT * FROM users ORDER BY id"</info>
                                    Compare query results between databases

    Notes:
      - When comparing tables, uses difft to highlight differences
      - SQL queries must be quoted when using spaces or special characters
      - Table names can only contain letters, numbers and underscore
    HELP;

    private DatabaseConnection $db1;
    private ?DatabaseConnection $db2;
    private ?string $tableName;
    private ?string $query;

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
        if ($input->mustSuggestArgumentValuesFor('config1')) {
            $suggestions->suggestValues($configs);
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
        $this->setName('cat')
            ->setDescription(
                'Display or compare data between databases and tables',
            )
            ->setHelp($this->help)
            ->addArgument(
                'config1',
                InputArgument::REQUIRED,
                'Configuration file for the first database',
            )
            ->addArgument(
                'argument2',
                InputArgument::REQUIRED,
                'Config for the second database or table name or SQL query',
            )
            ->addArgument(
                'argument3',
                InputArgument::OPTIONAL,
                'Table name or SQL query to execute when argument2 is a config',
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        $config1 = $input->getArgument('config1');
        $config2 = $input->getArgument('argument2');
        $argument3 = $input->getArgument('argument3');

        $path = realpath(__DIR__ . '/../../config');
        if (file_exists("$path/$config2.php")) {
            if (!$argument3) {
                throw new InvalidArgumentException(
                    'When argument2 is a config, argument3 is required',
                );
            }
            $tableOrQuery = $argument3;
        } else {
            $tableOrQuery = $config2;
            $config2 = null;
        }

        if (preg_match('/^[a-zA-Z0-9_]+$/', $tableOrQuery)) {
            $this->tableName = $tableOrQuery;
            $this->query = null;
        } else {
            $this->tableName = null;
            $this->query = $tableOrQuery;
        }

        $this->db1 = new DatabaseConnection($config1);
        $this->db2 = $config2 ? new DatabaseConnection($config2) : null;

        if ($this->db2) {
            $this->compare($output);
        } else {
            $this->display($output);
        }

        return Command::SUCCESS;
    }

    private function display(OutputInterface $output): void
    {
        if ($this->query) {
            $data = $this->db1->query($this->query);
        } else {
            $data = $this->db1->getTableData($this->tableName);
        }
        $output->writeln(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
    }

    private function compare(OutputInterface $output): void
    {
        $path = realpath(__DIR__ . '/../..');
        $a = "$path/.a.json";
        $b = "$path/.b.json";
        if ($this->query) {
            $data1 = $this->db1->query($this->query);
            $data2 = $this->db2->query($this->query);
        } else {
            $data1 = $this->db1->getTableData($this->tableName);
            $data2 = $this->db2->getTableData($this->tableName);
        }
        file_put_contents(
            $a,
            json_encode($data1, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
        file_put_contents(
            $b,
            json_encode($data2, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
        );
        $output->write(shell_exec("difft --color always $a $b"));
    }
}
