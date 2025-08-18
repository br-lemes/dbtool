<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use DBTool\Traits\ExpectedTrait;
use Exception;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;

final class CatCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use ExpectedTrait;

    function testCommand(): void
    {
        $args = ['config1' => 'test-mysql'];
        $sql =
            'SELECT p.id, p.title, u.name FROM posts AS p JOIN users AS u ON p.user_id = u.id';

        $args['argument2'] = 'posts';
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('cat-posts.json');
        $this->assertEquals($expected, $actual);

        $args['argument2'] = $sql;
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->getExpectedJson('cat-query.json');
        $this->assertEquals($expected, $actual);

        $args['argument2'] = 'test-pgsql';

        $args['argument3'] = 'posts';
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('cat-compare-posts.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $args['argument3'] = $sql;
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpected('cat-compare-query.txt');
        $this->assertEquals($expected, $test->getDisplay());

        $args['argument2'] = 'user_groups';
        unset($args['argument3']);
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpectedJson('cat-user_groups.json');
        $this->assertEquals($expected, json_decode($test->getDisplay(), true));

        $args['config1'] = 'test-pgsql';
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpectedJson('cat-user_groups.json');
        $this->assertEquals($expected, json_decode($test->getDisplay(), true));

        $args['argument2'] = 'tags';
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpectedJson('cat-tags.json');
        $this->assertEquals($expected, json_decode($test->getDisplay(), true));

        $args['argument2'] = 'post_tags';
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $expected = $this->getExpectedJson('cat-post_tags.json');
        $this->assertEquals($expected, json_decode($test->getDisplay(), true));

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            sprintf(self::INVALID_COLUMN_ORDER, 'invalid'),
        );
        $args['--column-order'] = 'invalid';
        $this->exec('cat', $args);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('cat', ['']);
        $this->assertCompleteEquals('cat', ['-o', ''], self::COLUMN_ORDER);
        $this->assertCompleteContains(
            'cat',
            ['test-mysql', ''],
            array_merge(self::TEST_CONFIGS, self::TEST_TABLES),
        );
        $this->assertCompleteEquals(
            'cat',
            ['test-mysql', 'test-pgsql', ''],
            self::TEST_TABLES,
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'When argument2 is a config, argument3 is required',
        );
        $args = ['config1' => 'test-mysql', 'argument2' => 'test-pgsql'];
        $this->exec('cat', $args);
    }

    function testErrorQuery(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error executing query:');

        $this->exec('cat', [
            'config1' => 'test-mysql-fail',
            'argument2' => 'SELECT * FROM posts',
        ]);
    }

    function testErrorTable(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error querying table 'posts':");

        $this->exec('cat', [
            'config1' => 'test-mysql-fail',
            'argument2' => 'posts',
        ]);
    }
}
