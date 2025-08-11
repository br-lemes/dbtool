<?php
declare(strict_types=1);

namespace DBTool\Traits;

trait ExpectedTrait
{
    protected function getExpected(string $filename): string
    {
        return file_get_contents(__DIR__ . "/../Tests/expected/$filename");
    }

    protected function getExpectedJson(string $filename): array
    {
        return json_decode($this->getExpected($filename), true);
    }

    protected function getMigration(string $filename): string
    {
        return file_get_contents(__DIR__ . "/../Tests/migration/$filename");
    }
}
