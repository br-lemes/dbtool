<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use DBTool\Traits\ExpectedTrait;
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

        $catPosts = $this->getExpectedJson('cat-posts.json');
        $catQuery = $this->getExpectedJson('cat-query.json');
        $catComparePosts = $this->getExpected('cat-compare-posts.txt');
        $catCompareQuery = $this->getExpected('cat-compare-query.txt');

        $args['argument2'] = 'posts';
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($catPosts, $output);

        $args['argument2'] = $sql;
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($catQuery, $output);

        $args['argument2'] = 'test-pgsql';

        $args['argument3'] = 'posts';
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($catComparePosts, $test->getDisplay());

        $args['argument3'] = $sql;
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($catCompareQuery, $test->getDisplay());

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
}
