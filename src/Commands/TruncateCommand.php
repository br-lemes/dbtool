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
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class TruncateCommand extends BaseCommand
{
    use ConstTrait;

    private string $help = <<<HELP
    Truncates all data from a table, keeping its schema intact.

    Usage examples:
      <info>truncate config1 users</info>     Clear all data from users table
      <info>truncate config1 products</info>  Clear all data from products table

    Notes:
      - Prompts for confirmation if table exists
      - Removes all data but preserves table structure and indexes
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
    }

    protected function configure(): void
    {
        $this->setName('truncate')
            ->setDescription('Truncate all data from a table')
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            )
            ->addArgument(
                'table',
                InputArgument::REQUIRED,
                'Name of the table to truncate',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getArgument('config');
        $tableName = $input->getArgument('table');

        $db = new DatabaseConnection($config, $output);

        if (!$db->tableExists($tableName)) {
            $output->writeln(sprintf(self::TABLE_DOES_NOT_EXIST, $tableName));
            return Command::FAILURE;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "Are you sure you want to truncate table '$tableName'? (y/N) ",
            false,
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Operation cancelled.');
            return Command::FAILURE;
        }

        $db->truncateTable($tableName);
        $output->writeln("Table '$tableName' truncated successfully.");
        return Command::SUCCESS;
    }
}
