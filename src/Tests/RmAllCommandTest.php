<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use Exception;
use Symfony\Component\Console\Command\Command;

final class RmAllCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;

    function testCommand(): void
    {
        $test = $this->exec('rm-all', ['config' => 'test-mysql'], ['n']);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
        $this->assertTrue($cancel);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('rm-all', ['']);
    }

    function testError(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error dropping all tables:');

        $this->exec('rm-all', ['config' => 'test-mysql-fail'], ['y']);
    }
}
