<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ExpectedTrait;
use Symfony\Component\Console\Command\Command;

final class StatusCommandTest extends AbstractCommandTestCase
{
    use ExpectedTrait;

    function testCommand(): void
    {
        $test = $this->exec('status', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('status-ok.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $test = $this->exec('rm-all', ['config' => 'test-mysql'], ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('status', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('status-no.txt');
        $this->assertEquals($expected, $test->getDisplay());
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('status', ['']);
    }
}
