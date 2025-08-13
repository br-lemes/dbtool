<?php
declare(strict_types=1);

namespace DBTool\Commands;

use DBTool\Database\DatabaseConnection;
use DBTool\Traits\ConstTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class RmAllCommand extends BaseCommand
{
    use ConstTrait;

    private string $help = <<<HELP
    Removes all tables from a database.

    Usage examples:
      <info>rm-all config1</info>  Remove all tables from the database specified

    Notes:
      - Prompts for confirmation before removing tables
      - Permanently deletes all tables and their data
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
    }

    protected function configure(): void
    {
        $this->setName('rm-all')
            ->setDescription('Remove all tables from a database')
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getArgument('config');
        $db = new DatabaseConnection($config, $output);

        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new ConfirmationQuestion(
            'Are you sure you want to remove all tables from the database? (y/N) ',
            false,
        );

        if (!$helper->ask($input, $output, $question)) {
            $output->writeln(self::CANCELLED);
            return Command::FAILURE;
        }

        $db->dropAll();
        $output->writeln('All tables removed successfully.');
        return Command::SUCCESS;
    }
}
