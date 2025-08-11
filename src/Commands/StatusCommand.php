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
use Symfony\Component\Console\Output\OutputInterface;

class StatusCommand extends BaseCommand
{
    use PhinxConfigTrait;

    private string $help = 'Show migration status';

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
        $this->setName('status')
            ->setDescription($this->help)
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
        $config = $this->getPhinxConfig($config, self::REQUIRED_PHINX);
        $manager = new Manager($config, new StringInput(' '), $output);
        $manager->printStatus('env');
        return Command::SUCCESS;
    }
}
