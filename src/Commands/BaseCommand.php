<?php
declare(strict_types=1);

namespace DBTool\Commands;

use Exception;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class BaseCommand extends Command
{
    protected function execute(
        InputInterface $input,
        OutputInterface $output,
    ): int {
        try {
            return $this->exec($input, $output);
        } catch (Exception $e) {
            $isTest =
                defined('PHPUNIT_COMPOSER_INSTALL') ||
                defined('__PHPUNIT_PHAR__');
            if ($isTest) {
                throw $e;
            }
            // @codeCoverageIgnoreStart
            $errOutput =
                $output instanceof ConsoleOutputInterface
                    ? $output->getErrorOutput()
                    : $output;
            if (!$errOutput) {
                exit(Command::FAILURE);
            }
            $formattedBlock = (new FormatterHelper())->formatBlock(
                $e->getMessage(),
                'error',
                true,
            );
            $errOutput->writeln(['', $formattedBlock, '']);
            exit(Command::FAILURE);
            // @codeCoverageIgnoreEnd
        }
    }

    abstract function exec(InputInterface $input, OutputInterface $output): int;
}
