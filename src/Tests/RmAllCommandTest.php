<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class RmAllCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $test = $this->exec('rm-all', ['config' => 'test-mysql'], ['n']);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('rm-all', ['']);
    }
}
