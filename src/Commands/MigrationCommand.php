<?php
declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\ConstTrait;
use DBTool\Database\DatabaseConnection;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class MigrationCommand extends BaseCommand
{
    use ConstTrait;

    private string $help = <<<HELP
    Generates a Phinx migration file for a table.

    Usage examples:
      <info>migration config1 users</info>           Generate migration for users table

    Notes:
      - Generates a Phinx migration file with timestamped filename
      - Includes columns, timestamps, unique constraints, and indexes
    HELP;

    private DatabaseConnection $db;
    private string $className;
    private string $tableName;

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
        if ($input->mustSuggestOptionValuesFor('output')) {
            $currentValue = $input->getCompletionValue();
            $files = glob("$currentValue*");
            foreach ($files as $file) {
                if (is_dir($file)) {
                    $file .= '/';
                }
                $suggestions->suggestValue($file);
            }
        }
    }

    protected function configure(): void
    {
        $this->setName('migration')
            ->setDescription('Generate Phinx migration file for tables')
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            )
            ->addArgument(
                'table',
                InputArgument::REQUIRED,
                'Name of the table to generate migration for',
            )
            ->addOption(
                'output',
                'o',
                InputOption::VALUE_REQUIRED,
                'Output file for the migration (default: timestamp_table.php)',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getArgument('config');
        $table = $input->getArgument('table');

        $this->db = new DatabaseConnection($config, $output);

        if (!$this->db->tableExists($table)) {
            $output->writeln(sprintf(self::TABLE_DOES_NOT_EXIST, $table));
            return Command::FAILURE;
        }
        $timestamp = date('YmdHis', time());
        $this->className = implode(
            '',
            array_map('ucfirst', explode('_', $table)),
        );
        $fileName = $input->getOption('output') ?: "{$timestamp}_{$table}.php";
        if (!str_ends_with($fileName, '.php')) {
            $fileName .= '.php';
        }
        if (file_exists($fileName)) {
            /** @var QuestionHelper $helper */
            $helper = $this->getHelper('question');
            $question = new ConfirmationQuestion(
                "File '$fileName' already exists. Do you want to overwrite it? (y/N) ",
                false,
            );

            if (!$helper->ask($input, $output, $question)) {
                $output->writeln('Operation cancelled.');
                return Command::FAILURE;
            }
        }
        $this->tableName = $table;
        $content = $this->generateMigrationContent();
        if (file_put_contents($fileName, $content) === false) {
            return Command::FAILURE;
        }

        $output->writeln("Migration file created successfully: $fileName");
        return Command::SUCCESS;
    }

    private function generateHeader(): string
    {
        return <<<END
        <?php

        declare(strict_types=1);

        use Phinx\Migration\AbstractMigration;

        final class {$this->className} extends AbstractMigration
        {
            /**
             * Change Method.
             *
             * Write your reversible migrations using this method.
             *
             * More information on writing migrations is available here:
             * https://book.cakephp.org/phinx/0/en/migrations.html#the-change-method
             *
             * Remember to call "create()" or "update()" and NOT "save()" when working
             * with the Table class.
             */
            public function change(): void
            {
                \$this->table('{$this->tableName}')

        END;
    }

    private function generateColumns(): string
    {
        $ignore = ['id', 'created_at', 'refresh_at', 'updated_at'];
        $columns = $this->db->getColumns($this->tableName, 'custom');
        $columnNames = array_column($columns, 'COLUMN_NAME');
        $lines = [];
        $timestamps = $this->getTimestampDefinition($columnNames);
        foreach ($columns as $column) {
            if (in_array($column['COLUMN_NAME'], $ignore)) {
                continue;
            }
            if ($timestamps && $column['COLUMN_NAME'] === 'key_id') {
                $lines[] = $timestamps;
                $timestamps = '';
            }
            $phinxType = $this->getPhinxType($column['DATA_TYPE']);
            $line = "->addColumn('{$column['COLUMN_NAME']}', '{$phinxType}'";
            $options = $this->getColumnOptions($column);
            if (!empty($options)) {
                $line .= ', ' . $this->array_export($options);
            }
            $lines[] = $line . ')';
        }
        if ($timestamps) {
            $lines[] = $timestamps;
        }
        return '            ' . implode("\n            ", $lines) . "\n";
    }

    private function getPhinxType(string $dataType): string
    {
        $typeMap = [
            'BIGINT' => 'biginteger',
            'CHAR' => 'char',
            'DATE' => 'date',
            'TIMESTAMP' => 'timestamp',
            'NUMERIC' => 'decimal',
            'DOUBLE PRECISION' => 'double',
            'REAL' => 'float',
            'INTEGER' => 'integer',
            'SMALLINT' => 'smallinteger',
            'TEXT' => 'text',
            'TIME' => 'time',
            'VARCHAR' => 'string',
        ];
        return $typeMap[$dataType] ?? 'string';
    }

    private function getTextLimitOption(int $maxLength): ?string
    {
        switch ($maxLength) {
            case 255:
                return 'TEXT_TINY';
            case 16777215:
                return 'TEXT_MEDIUM';
            case 4294967295:
                return 'TEXT_LONG';
            default:
                return null;
        }
    }

    private function getDefaultValue(array $column): mixed
    {
        $int = ['BIGINT', 'INTEGER', 'SMALLINT'];
        $float = ['DOUBLE PRECISION', 'NUMERIC', 'REAL'];
        if (in_array($column['DATA_TYPE'], $int)) {
            return (int) $column['COLUMN_DEFAULT'];
        }
        if (in_array($column['DATA_TYPE'], $float)) {
            return (float) $column['COLUMN_DEFAULT'];
        }
        return $column['COLUMN_DEFAULT'];
    }

    private function getColumnOptions(array $column): array
    {
        $options = [];
        if ($column['CHARACTER_MAXIMUM_LENGTH']) {
            if ($column['DATA_TYPE'] === 'TEXT') {
                $limit = $this->getTextLimitOption(
                    $column['CHARACTER_MAXIMUM_LENGTH'],
                );
                if ($limit) {
                    $options['limit'] = $limit;
                }
            } else {
                $options['limit'] = $column['CHARACTER_MAXIMUM_LENGTH'];
            }
        }
        if ($column['COLUMN_DEFAULT'] !== null) {
            $options['default'] = $this->getDefaultValue($column);
        }
        if ($column['IS_NULLABLE'] === 'YES') {
            $options['null'] = true;
        }
        return $options;
    }

    private function getTimestampDefinition(array $columnNames): string
    {
        if (in_array('created_at', $columnNames)) {
            if (in_array('refresh_at', $columnNames)) {
                return "->addTimestamps('created_at', 'refresh_at')";
            }
            if (in_array('updated_at', $columnNames)) {
                return '->addTimestamps()';
            }
            return "->addTimestamps('created_at', false)";
        }
        if (in_array('updated_at', $columnNames)) {
            return "->addTimestamps(false, 'updated_at')";
        }
        return '';
    }

    private function generateIndexes(): string
    {
        $result = '';
        $keys = $this->db->getKeys($this->tableName, 'custom');
        $composite = [];
        foreach ($keys as $key) {
            if ($key['KEY_TYPE'] === 'PRIMARY') {
                continue;
            }
            if ($key['IS_COMPOSITE'] === 'YES') {
                $composite[$key['KEY_NAME']][] = $key['COLUMN_NAME'];
                $composite[$key['KEY_NAME']]['unique'] =
                    $key['KEY_TYPE'] === 'UNIQUE';
                continue;
            }
            $result .= '            ';
            $result .= "->addIndex('{$key['COLUMN_NAME']}'";
            if ($key['KEY_TYPE'] === 'UNIQUE') {
                $result .= ", ['unique' => true]";
            }
            $result .= ")\n";
        }
        foreach ($composite as $_ => $columns) {
            $unique = $columns['unique'] ? ", ['unique' => true]" : '';
            unset($columns['unique']);
            $result .= '            ';
            $result .= "->addIndex(['";
            $result .= implode("', '", $columns);
            $result .= "']$unique)\n";
        }
        return $result;
    }

    private function generateFooter(): string
    {
        return <<<END
                    ->create();
            }
        }

        END;
    }

    private function array_export(array $array): string
    {
        if (!is_array($array)) {
            return '';
        }
        $result = '[';
        $items = [];
        $isAssoc = array_keys($array) !== range(0, count($array) - 1);
        foreach ($array as $key => $value) {
            $item = $isAssoc ? (is_int($key) ? "$key => " : "'$key' => ") : '';
            if (is_array($value)) {
                $item .= $this->array_export($value);
            } else {
                $item .= var_export($value, true);
            }
            $items[] = $item;
        }
        $result .= implode(', ', $items) . ']';
        return $result;
    }
    private function generateMigrationContent(): string
    {
        return $this->generateHeader() .
            $this->generateColumns() .
            $this->generateIndexes() .
            $this->generateFooter();
    }
}
