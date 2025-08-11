<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\ConstTrait;
use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;

final class ListCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use GetExpectedTrait;

    function testCommand(): void
    {
        $ls = $this->getExpectedJson('ls.json');
        $lsPosts = $this->getExpectedJson('ls-posts.json');
        $lsPostsId = $this->getExpectedJson('ls-posts-id.json');

        $args['config'] = 'test-pgsql';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($ls, $output);

        $args = ['config' => 'test-mysql'];
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($this::TEST_TABLES, $output);

        $args['table'] = 'posts';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($lsPosts, $output);

        $args['field'] = 'id';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($lsPostsId, $output);

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
