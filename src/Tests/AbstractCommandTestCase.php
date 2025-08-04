<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\DBTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandCompletionTester;
use Symfony\Component\Console\Tester\CommandTester;

abstract class AbstractCommandTestCase extends TestCase
{
    protected Application $application;

    function setUp(): void
    {
        parent::setUp();

        $this->application = new DBTool();
        foreach (['mariadb', 'mysql', 'pgsql'] as $database) {
            $this->setupDatabases($database);
        }
    }

    private function setupDatabases(string $database): void
    {
        $test = $this->exec('rm-all', ['config' => "test-$database"], ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('run', [
            'config' => "test-$database",
            'script' => __DIR__ . "/fixture/test-$database.sql",
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
    }

    function assertCompleteContains(
        string $name,
        array $input,
        array $values,
    ): void {
        $actual = $this->complete($name, $input);
        foreach ($values as $value) {
            $this->assertContains($value, $actual);
        }
    }

    function assertCompleteDatabase(string $name, array $input): void
    {
        $this->assertCompleteContains($name, $input, [
            'test-mariadb',
            'test-mysql',
            'test-pgsql',
        ]);
    }

    function assertCompleteEquals(
        string $name,
        array $input,
        array $values,
    ): void {
        $actual = $this->complete($name, $input);
        $this->assertEquals($values, $actual);
    }

    function complete(string $name, array $input): array
    {
        $command = $this->application->find($name);
        $command->setApplication($this->application);

        $tester = new CommandCompletionTester($command);
        return $tester->complete($input);
    }

    function exec(string $name, array $args, array $inputs = []): CommandTester
    {
        $command = $this->application->find($name);
        $command->setApplication($this->application);

        $commandTester = new CommandTester($command);
        $commandTester->setInputs($inputs);
        $commandTester->execute($args);

        return $commandTester;
    }
}
