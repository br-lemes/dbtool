<?php

namespace DBTool\Tests;

use DBTool\DBTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

abstract class AbstractCommandTestCase extends KernelTestCase
{
    protected Application $application;

    function setUp(): void
    {
        parent::setUp();

        $kernel = static::createKernel();
        $this->application = new DBTool($kernel);

        $test = $this->exec('rm-all', ['config' => 'test-mysql'], ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $test = $this->exec('run', [
            'config' => 'test-mysql',
            'script' => 'test-mysql.sql',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $test = $this->exec('rm-all', ['config' => 'test-pgsql'], ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $test = $this->exec('run', [
            'config' => 'test-pgsql',
            'script' => 'test-pgsql.sql',
        ]);
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
