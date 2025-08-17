<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use Symfony\Component\Console\Command\Command;

final class RollbackCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;

    function testCommand(): void
    {
        $test = $this->exec('rollback', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['user_groups']);
        $this->assertEquals($expected, $actual);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('rollback', ['']);
    }
}
