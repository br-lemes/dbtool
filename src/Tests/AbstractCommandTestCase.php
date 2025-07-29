<?php

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

        $test = $this->exec('rm-all', ['config' => 'test-mysql'], ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('run', [
            'config' => 'test-mysql',
            'script' => __DIR__ . '/test-mysql.sql',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('rm-all', ['config' => 'test-pgsql'], ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('run', [
            'config' => 'test-pgsql',
            'script' => __DIR__ . '/test-pgsql.sql',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
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
