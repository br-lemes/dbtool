<?php
declare(strict_types=1);

namespace DBTool\Database;

use PDOException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;

trait UtilitiesTrait
{
    private ?OutputInterface $output;
    private ?OutputInterface $errOutput;

    protected function error(string $message, ?PDOException $e = null): void
    {
        if (!isset($this->output)) {
            exit(Command::FAILURE);
        }
        if (!isset($this->errOutput)) {
            $this->errOutput =
                $this->output instanceof ConsoleOutputInterface
                    ? $this->output->getErrorOutput()
                    : $this->output;
        }
        if (!isset($this->errOutput)) {
            exit(Command::FAILURE);
        }
        $fullMessage = $e ? "$message: {$e->getMessage()}" : $message;
        $formattedBlock = (new FormatterHelper())->formatBlock(
            $fullMessage,
            'error',
            true,
        );
        $this->errOutput->writeln(['', $formattedBlock, '']);
        exit(Command::FAILURE);
    }

    private function sanitize(
        string $value,
        string $pattern,
        string $name,
    ): string {
        if (!preg_match($pattern, $value)) {
            $this->error("Parameter '$name' contains invalid characters.");
        }
        return $value;
    }
}
