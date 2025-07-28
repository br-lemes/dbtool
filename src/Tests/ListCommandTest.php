<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class ListCommandTest extends AbstractCommandTestCase
{
    function testList(): void
    {
        $lsFile = __DIR__ . '/ls.json';
        $lsPostsFile = __DIR__ . '/ls-posts.json';
        $lsPostsIdFile = __DIR__ . '/ls-posts-id.json';
        $ls = json_decode(file_get_contents($lsFile), true);
        $lsPosts = json_decode(file_get_contents($lsPostsFile), true);
        $lsPostsId = json_decode(file_get_contents($lsPostsIdFile), true);
        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($ls, json_decode($test->getDisplay()));
        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($ls, json_decode($test->getDisplay()));
        $test = $this->exec('ls', [
            'config' => 'test-mysql',
            'table' => 'posts',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($lsPosts, json_decode($test->getDisplay(), true));
        $test = $this->exec('ls', [
            'config' => 'test-mysql',
            'table' => 'posts',
            'field' => 'id',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals($lsPostsId, json_decode($test->getDisplay(), true));
    }
}
