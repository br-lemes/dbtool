<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class CatCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $args = ['config1' => 'test-mysql'];
        $sql =
            'SELECT p.id, p.title, u.name FROM posts AS p JOIN users AS u ON p.user_id = u.id';

        $catPosts = __DIR__ . '/expected/cat-posts.json';
        $catQuery = __DIR__ . '/expected/cat-query.json';
        $catComparePosts = __DIR__ . '/expected/cat-compare-posts.json';
        $catCompareQuery = __DIR__ . '/expected/cat-compare-query.json';
        $catPosts = file_get_contents($catPosts);
        $catQuery = file_get_contents($catQuery);
        $catComparePosts = file_get_contents($catComparePosts);
        $catCompareQuery = file_get_contents($catCompareQuery);
        $catPosts = json_decode($catPosts, true);
        $catQuery = json_decode($catQuery, true);
        $catComparePosts = json_decode($catComparePosts, true);
        $catCompareQuery = json_decode($catCompareQuery, true);

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
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($catComparePosts, $output);

        $args['argument3'] = $sql;
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($catCompareQuery, $output);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('cat', ['']);
        $this->assertCompleteEquals('cat', ['-o', ''], ['custom', 'native']);
        $this->assertCompleteContains(
            'cat',
            ['test-mysql', ''],
            ['test-mysql', 'test-pgsql', 'posts', 'users'],
        );
        $this->assertCompleteEquals(
            'cat',
            ['test-mysql', 'test-pgsql', ''],
            ['posts', 'users'],
        );
    }
}
