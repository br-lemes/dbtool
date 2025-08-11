<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class RollbackCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $test = $this->exec('rollback', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['phinxlog', 'posts', 'users'], $output);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('rollback', ['']);
    }
}
