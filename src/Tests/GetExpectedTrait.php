<?php
declare(strict_types=1);

namespace DBTool\Tests;

trait GetExpectedTrait
{
    protected function getExpected(string $filename): string
    {
        return file_get_contents(__DIR__ . '/expected/' . $filename);
    }

    protected function getExpectedJson(string $filename): array
    {
        return json_decode($this->getExpected($filename), true);
    }

    protected function getMigration(string $filename): string
    {
        return file_get_contents(__DIR__ . '/migration/' . $filename);
    }
}
