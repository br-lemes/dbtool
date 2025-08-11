<?php

namespace DBTool\Commands;

use DBTool\Traits\PhinxConfigTrait;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends BaseCommand
{
    use PhinxConfigTrait;

    private string $help = <<<HELP
    Executes Phinx migrations on a database

    Usage examples:
      <info>migrate config</info>       Execute migrations on the config database
      <info>migrate config migration_name</info>  Execute a specific migration
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
        if ($input->mustSuggestArgumentValuesFor('migration')) {
            $config = $input->getArgument('config');
            $required = ['paths.migrations'];
            $config = $this->getPhinxConfig($config, $required);
            $input = new StringInput('');
            $output = new NullOutput();
            $manager = new Manager($config, $input, $output);
            $result = [];
            foreach ($manager->getMigrations('env') as $migration) {
                $result[] = "{$migration->getVersion()} {$migration->getName()}";
            }
            $suggestions->suggestValues($result);
        }
    }

    protected function configure(): void
    {
        $this->setName('migrate')
            ->setDescription('Executes Phinx migrations')
            ->setHelp($this->help)
            ->addArgument(
                'config',
                InputArgument::REQUIRED,
                'Configuration file with database credentials',
            )
            ->addArgument(
                'migration',
                InputArgument::OPTIONAL,
                'The name of the migration to run',
            );
    }

    function exec(InputInterface $input, OutputInterface $output): int
    {
        $config = $input->getArgument('config');
        $config = $this->getPhinxConfig($config, self::REQUIRED_PHINX);
        $manager = new Manager($config, new StringInput(' '), $output);
        $migration = $input->getArgument('migration');
        if ($migration) {
            $migrations = $manager->getMigrations('env');
            foreach ($migrations as $m) {
                if ("{$m->getVersion()} {$m->getName()}" === $migration) {
                    $manager->executeMigration('env', $m);
                    return Command::SUCCESS;
                }
            }
            $this->error("Migration '$migration' not found");
        }
        $manager->migrate('env');
        return Command::SUCCESS;
    }
}
