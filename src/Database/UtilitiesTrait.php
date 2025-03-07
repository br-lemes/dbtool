<?php
declare(strict_types=1);

namespace DBTool\Database;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\FormatterHelper;
use Symfony\Component\Console\Output\OutputInterface;

trait UtilitiesTrait
{
    private ?OutputInterface $errOutput;

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

    protected function error(string $message, ?\PDOException $e = null): void
    {
        if (!$this->errOutput) {
            return;
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
}
