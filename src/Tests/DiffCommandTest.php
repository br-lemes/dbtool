<?php
declare(strict_types=1);

namespace DBTool\Tests;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;

final class DiffCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $args = [
            'config1' => 'test-mysql',
            'config2' => 'test-pgsql',
        ];

        $expected = __DIR__ . '/expected/diff-mysql-pgsql.txt';
        $expected = file_get_contents($expected);

        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($expected, $test->getDisplay());

        $expected = __DIR__ . '/expected/diff-mysql-pgsql-no.txt';
        $expected = file_get_contents($expected);

        $test = $this->exec('diff', array_merge($args, ['-l' => 'no']));
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($expected, $test->getDisplay());

        $expected = __DIR__ . '/expected/diff-mariadb-pgsql.txt';
        $expected = file_get_contents($expected);

        $args['config1'] = 'test-mariadb';
        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($expected, $test->getDisplay());

        $expected = __DIR__ . '/expected/diff-mariadb-pgsql-users.txt';
        $expected = file_get_contents($expected);

        $args['table'] = 'users';
        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($expected, $test->getDisplay());

        $expected = __DIR__ . '/expected/diff-mariadb-pgsql-users-name.txt';
        $expected = file_get_contents($expected);

        $args['field'] = 'name';
        $test = $this->exec('diff', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($expected, $test->getDisplay());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Invalid value for column order. Must be 'custom' or 'native', got 'invalid'.",
        );
        $args['--column-order'] = 'invalid';
        $this->exec('diff', $args);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('diff', ['']);
        $this->assertCompleteDatabase('diff', ['test-mysql', '']);
        $this->assertCompleteEquals('diff', ['-o', ''], ['custom', 'native']);
        $this->assertCompleteEquals('diff', ['-l', ''], ['yes', 'no']);
        $this->assertCompleteEquals(
            'diff',
            ['test-mysql', 'test-pgsql', ''],
            ['posts', 'users'],
        );
        $this->assertCompleteEquals(
            'diff',
            ['test-mysql', 'test-pgsql', 'posts', ''],
            ['id', 'user_id', 'content', 'publish_date', 'title', 'created_at'],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            "Invalid value for ignore-length. Must be 'yes' or 'no', got 'invalid'.",
        );
        $args = [
            'config1' => 'test-mysql',
            'config2' => 'test-pgsql',
            '--ignore-length' => 'invalid',
        ];
        $this->exec('diff', $args);
    }
}
