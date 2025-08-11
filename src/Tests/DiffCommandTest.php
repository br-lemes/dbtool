<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\ConstTrait;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;

final class DiffCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use ExpectedTrait;

    function testCommand(): void
    {
        $args = [
            'config1' => 'test-mysql',
            'config2' => 'test-pgsql',
        ];

        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('diff-mysql-pgsql.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $test = $this->exec('diff', array_merge($args, ['-l' => 'no']));
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('diff-mysql-pgsql-no.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $args['config1'] = 'test-mariadb';
        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('diff-mariadb-pgsql.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $args['table'] = 'users';
        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('diff-mariadb-pgsql-users.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $args['field'] = 'name';
        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('diff-mariadb-pgsql-users-name.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(self::INVALID_COLUMN_ORDER, 'invalid'),
        );
        $args['--column-order'] = 'invalid';
        $this->exec('diff', $args);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('diff', ['']);
        $this->assertCompleteDatabase('diff', ['test-mysql', '']);
        $this->assertCompleteEquals('diff', ['-o', ''], self::COLUMN_ORDER);
        $this->assertCompleteEquals('diff', ['-l', ''], ['yes', 'no']);
        $this->assertCompleteEquals(
            'diff',
            ['test-mysql', 'test-pgsql', ''],
            self::TEST_TABLES,
        );
        $this->assertCompleteEquals(
            'diff',
            ['test-mysql', 'test-pgsql', 'posts', ''],
            self::TEST_POST_COLUMNS,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(self::INVALID_IGNORE_LENGTH, 'invalid'),
        );
        $args = [
            'config1' => 'test-mysql',
            'config2' => 'test-pgsql',
            '--ignore-length' => 'invalid',
        ];
        $this->exec('diff', $args);
    }
}
