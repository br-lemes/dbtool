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

class RemoveCommand extends BaseCommand
{
    private string $help = <<<HELP
    Removes a table from a database.

    Usage examples:
      <info>rm config1 users</info>     Remove users table
      <info>rm config1 products</info>  Remove products table

    Notes:
      - Prompts for confirmation if table exists
      - Permanently deletes the table and all its data
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
        $this->setName('rm')
            ->setDescription('Remove a table from a database')
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            )
            ->addArgument(
                'table',
                InputArgument::REQUIRED,
                'Name of the table to remove',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getArgument('config');
        $tableName = $input->getArgument('table');

        $db = new DatabaseConnection($config, $output);

        if (!$db->tableExists($tableName)) {
            $output->writeln("Table '$tableName' does not exist.");
            return Command::FAILURE;
        }

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            "Are you sure you want to remove table '$tableName'? (y/N) ",
            false,
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln('Operation cancelled.');
            return Command::FAILURE;
        }

        $db->dropTable($tableName);
        $output->writeln("Table '$tableName' removed successfully.");
        return Command::SUCCESS;
    }
}
