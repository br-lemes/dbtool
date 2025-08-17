<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use DBTool\Traits\ExpectedTrait;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;

final class ListCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use ExpectedTrait;

    function testCommand(): void
    {
        $args['config'] = 'test-pgsql';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('ls.json');
        $this->assertEquals($expected, $actual);

        $args = ['config' => 'test-mysql'];
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $this->assertEquals($this::TEST_TABLES, $actual);

        $args['table'] = 'posts';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('ls-posts.json');
        $this->assertEquals($expected, $actual);

        $args['field'] = 'id';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('ls-posts-id.json');
        $this->assertEquals($expected, $actual);

        $args = ['config' => 'test-pgsql', 'table' => 'users'];
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('ls-pgsql-users-custom.json');
        $this->assertEquals($expected, $actual);

        $args['-o'] = 'native';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('ls-pgsql-users-native.json');
        $this->assertEquals($expected, $actual);

        $args['config'] = 'test-mysql';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('ls-mysql-users-native.json');
        $this->assertEquals($expected, $actual);

        unset($args['-o']);
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('ls-mysql-users-custom.json');
        $this->assertEquals($expected, $actual);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(self::INVALID_COLUMN_ORDER, 'invalid'),
        );

        $args = ['config' => 'test-pgsql', '--column-order' => 'invalid'];
        $this->exec('ls', $args);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('ls', ['']);
        $this->assertCompleteEquals('ls', ['-o', ''], self::COLUMN_ORDER);
        $this->assertCompleteEquals(
            'ls',
            ['test-mysql', ''],
            self::TEST_TABLES,
        );
        $this->assertCompleteEquals(
            'ls',
            ['test-mysql', 'posts', ''],
            self::TEST_POST_COLUMNS,
        );
    }
}
