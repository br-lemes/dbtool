<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Exception;
use Symfony\Component\Console\Command\Command;

final class MigrateCommandTest extends AbstractCommandTestCase
{
    use GetExpectedTrait;

    function testCommand(): void
    {
        $diffArgs = ['config1' => 'test-mysql', 'config2' => 'test-pgsql'];
        $diffMysqlPgsql = $this->getExpected('diff-mysql-pgsql.txt');

        $test = $this->exec('diff', $diffArgs);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($diffMysqlPgsql, $test->getDisplay());

        $test = $this->exec('rm-all', ['config' => 'test-mysql'], ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals("[]\n", $test->getDisplay());

        $test = $this->exec('migrate', [
            'config' => 'test-mysql',
            'migration' => '20250807015232 Products',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['phinxlog', 'products'], $output);

        $test = $this->exec('migrate', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('diff', $diffArgs);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($diffMysqlPgsql, $test->getDisplay());

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Migration 'invalid' not found");
        $test = $this->exec('migrate', [
            'config' => 'test-mysql',
            'migration' => 'invalid',
        ]);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('migrate', ['']);
        $this->assertCompleteEquals(
            'migrate',
            ['test-mysql', ''],
            [
                '20250807015230 Users',
                '20250807015231 Posts',
                '20250807015232 Products',
            ],
        );
    }
}
