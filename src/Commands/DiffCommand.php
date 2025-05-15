<?php
declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DiffCommand extends BaseCommand
{
    private string $help = <<<HELP
    Compare database structures at different levels.

    Usage examples:
      <info>diff db1 db2</info>           Compare tables list between databases
                             Shows: '==' same, '!=' & '<>' different schema
                             Shows: '>' only in db1 and '<' only in db2
      <info>diff db1 db2 users</info>     Compare users table schema in detail
      <info>diff db1 db2 users id</info>  Compare just the id field definition

    Options:
      --column-order=<custom|native> Set column order (default: custom)
      --ignore-length=<yes|no>       Ignore CHARACTER_MAXIMUM_LENGTH for TEXT
        fields (default: yes for different database types, otherwise no)

    Notes:
      - Uses difft for colored output showing differences
      - Can compare entire databases, single tables, or specific fields
      - Useful for checking schema consistency across environments
      - When comparing different database types (e.g., MySQL to PostgreSQL),
        --ignore-length is enabled by default
    HELP;

    private DatabaseConnection $db1;
    private DatabaseConnection $db2;
    private ?string $tableName = null;
    private ?string $fieldName = null;
    private ?string $order = 'custom';
    private bool $ignoreLength = false;

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
                    array_column(
                        $db->getColumns($table, 'custom'),
                        'COLUMN_NAME',
                    ),
                ),
            );
        }
        if ($input->mustSuggestOptionValuesFor('column-order')) {
            $suggestions->suggestValues(['custom', 'native']);
        }
        if ($input->mustSuggestOptionValuesFor('ignore-length')) {
            $suggestions->suggestValues(['yes', 'no']);
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
            )
            ->addOption(
                'column-order',
                'o',
                InputOption::VALUE_REQUIRED,
                'Column order: custom or native',
                'custom',
            )
            ->addOption(
                'ignore-length',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Ignore CHARACTER_MAXIMUM_LENGTH when comparing TEXT fields',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config1 = $input->getArgument('config1');
        $config2 = $input->getArgument('config2');
        $this->db1 = new DatabaseConnection($config1, $output);
        $this->db2 = new DatabaseConnection($config2, $output);

        $this->tableName = $input->getArgument('table');
        $this->fieldName = $input->getArgument('field');
        $this->order = $input->getOption('column-order');
        $this->ignoreLength = $this->db1->type !== $this->db2->type;
        $ignoreLengthOption = $input->getOption('ignore-length');
        if ($ignoreLengthOption !== null) {
            if (!in_array($ignoreLengthOption, ['yes', 'no'])) {
                throw new InvalidArgumentException(
                    "Invalid value for ignore-length. Must be 'yes' or 'no', got '$ignoreLengthOption'.",
                );
            }
            $this->ignoreLength = $ignoreLengthOption === 'yes';
        }
        if (!in_array($this->order, ['custom', 'native'])) {
            throw new InvalidArgumentException(
                "Invalid value for column order. Must be 'custom' or 'native', got '$this->order'.",
            );
        }

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

        $columns1 = $this->normalizeColumns(
            $this->db1->getColumns($this->tableName, $this->order),
        );
        $keys1 = array_map(
            fn(array $key) => array_diff_key($key, ['KEY_NAME' => '']),
            $this->db1->getKeys($this->tableName, $this->order),
        );
        $columns2 = $this->normalizeColumns(
            $this->db2->getColumns($this->tableName, $this->order),
        );
        $keys2 = array_map(
            fn(array $key) => array_diff_key($key, ['KEY_NAME' => '']),
            $this->db2->getKeys($this->tableName, $this->order),
        );

        if ($this->fieldName) {
            $columns1 = array_values(
                array_filter(
                    $columns1,
                    fn(array $column) => $column['COLUMN_NAME'] ===
                        $this->fieldName,
                ),
            );
            $keys1 = array_values(
                array_filter(
                    $keys1,
                    fn(array $key) => $key['KEY_NAME'] === $this->fieldName ||
                        $key['COLUMN_NAME'] === $this->fieldName,
                ),
            );
            $columns2 = array_values(
                array_filter(
                    $columns2,
                    fn(array $column) => $column['COLUMN_NAME'] ===
                        $this->fieldName,
                ),
            );
            $keys2 = array_values(
                array_filter(
                    $keys2,
                    fn(array $key) => $key['KEY_NAME'] === $this->fieldName ||
                        $key['COLUMN_NAME'] === $this->fieldName,
                ),
            );
        }

        file_put_contents(
            $a,
            json_encode(
                ['columns' => $columns1, 'keys' => $keys1],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ),
        );
        file_put_contents(
            $b,
            json_encode(
                ['columns' => $columns2, 'keys' => $keys2],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE,
            ),
        );

        $output->write(shell_exec("difft --context 7 --color always $a $b"));
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
            if (isset($tables1[$key])) {
                $columns = $this->normalizeColumns(
                    $this->db1->getColumns($key, $this->order),
                );
                $keys = array_map(
                    fn(array $key) => array_diff_key($key, ['KEY_NAME' => '']),
                    $this->db1->getKeys($key, $this->order),
                );
                $json = json_encode(['columns' => $columns, 'keys' => $keys]);
                $tables1[$key] = md5($json);
            } else {
                $tables1[$key] = '';
            }
            if (isset($tables2[$key])) {
                $columns = $this->normalizeColumns(
                    $this->db2->getColumns($key, $this->order),
                );
                $keys = array_map(
                    fn(array $key) => array_diff_key($key, ['KEY_NAME' => '']),
                    $this->db2->getKeys($key, $this->order),
                );
                $json = json_encode(['columns' => $columns, 'keys' => $keys]);
                $tables2[$key] = md5($json);
            } else {
                $tables2[$key] = '';
            }
        }

        ksort($tables1);
        ksort($tables2);

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

    private function normalizeColumns(array $columns): array
    {
        if (!$this->ignoreLength) {
            return $columns;
        }
        foreach ($columns as &$column) {
            if ($column['DATA_TYPE'] !== 'TEXT') {
                continue;
            }
            $column['CHARACTER_MAXIMUM_LENGTH'] = null;
        }
        return $columns;
    }
}
