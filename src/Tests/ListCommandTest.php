<?php
declare(strict_types=1);

namespace DBTool\Tests;

use InvalidArgumentException;
use Symfony\Component\Console\Command\Command;

final class ListCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $ls = __DIR__ . '/expected/ls.json';
        $lsPosts = __DIR__ . '/expected/ls-posts.json';
        $lsPostsId = __DIR__ . '/expected/ls-posts-id.json';
        $ls = file_get_contents($ls);
        $lsPosts = file_get_contents($lsPosts);
        $lsPostsId = file_get_contents($lsPostsId);
        $ls = json_decode($ls, true);
        $lsPosts = json_decode($lsPosts, true);
        $lsPostsId = json_decode($lsPostsId, true);

        $args['config'] = 'test-pgsql';
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($ls, $output);

        $args = ['config' => 'test-mysql'];
        $test = $this->exec('ls', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($ls, $output);

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
            "Invalid value for column order. Must be 'custom' or 'native', got 'invalid'.",
        );

        $args = ['config' => 'test-pgsql', '--column-order' => 'invalid'];
        $this->exec('ls', $args);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('ls', ['']);
        $this->assertCompleteEquals('ls', ['-o', ''], ['custom', 'native']);
        $this->assertCompleteEquals(
            'ls',
            ['test-mysql', ''],
            ['posts', 'users'],
        );
        $this->assertCompleteEquals(
            'ls',
            ['test-mysql', 'posts', ''],
            ['id', 'user_id', 'content', 'publish_date', 'title', 'created_at'],
        );
    }
}
