<?php

namespace DBTool\Commands;

use DBTool\ConfigTrait;
use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Phinx\Util\Util;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Completion\CompletionInput;
use Symfony\Component\Console\Completion\CompletionSuggestions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\OutputInterface;

class MigrateCommand extends BaseCommand
{
    use ConfigTrait;

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
            $config = $this->getConfig($config, ['paths.migrations']);
            $timestamp = str_repeat('[0-9]', 14);
            $suggestions->suggestValues(
                array_map(
                    fn(string $file): string => basename($file, '.php'),
                    glob("{$config['paths']['migrations']}/{$timestamp}_*.php"),
                ),
            );
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
        $config = $this->getConfig($input->getArgument('config'), [
            'database',
            'host',
            'paths.migrations',
            'username',
        ]);

        $config = new Config([
            'paths' => ['migrations' => $config['paths']['migrations']],
            'environments' => [
                'default_environment' => 'env',
                'env' => array_filter(
                    [
                        'adapter' => $config['driver'],
                        'host' => $config['host'],
                        'name' => $config['database'],
                        'user' => $config['username'],
                        'pass' => $config['password'],
                        'port' => $config['port'],
                        'charset' => 'utf8',
                    ],
                    'strlen',
                ),
            ],
        ]);

        $manager = new Manager($config, new StringInput(' '), $output);
        $migration = $input->getArgument('migration');
        if ($migration) {
            $migrations = $manager->getMigrations('env');
            foreach ($migrations as $m) {
                $fileName = Util::mapClassNameToFileName($m->getName());
                $fileName = $m->getVersion() . substr($fileName, 14, -4);
                if ($fileName === $migration) {
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
