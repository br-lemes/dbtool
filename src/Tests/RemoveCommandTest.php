<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use Exception;
use Symfony\Component\Console\Command\Command;

final class RemoveCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;

    function testCommand(): void
    {
        $args = ['config' => 'test-mysql', 'table' => 'uses'];
        $test = $this->exec('rm', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());

        $args['table'] = 'posts';
        $test = $this->exec('rm', $args, ['n']);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
        $this->assertTrue($cancel);

        $test = $this->exec('rm', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['posts']);
        $this->assertEquals($expected, $actual);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('rm', ['']);
        $this->assertCompleteEquals(
            'rm',
            ['test-mysql', ''],
            self::TEST_TABLES,
        );
    }

    function testError(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error dropping table 'users':");

        $this->exec(
            'rm',
            ['config' => 'test-mysql-fail', 'table' => 'users'],
            ['y'],
        );
    }
}
