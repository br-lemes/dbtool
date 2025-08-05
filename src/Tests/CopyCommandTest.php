<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class CopyCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['products', 'users'], $output);

        $test = $this->exec('cp', [
            'source' => 'test-mysql',
            'destination' => 'test-mariadb',
            'table' => 'posts',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'products', 'users'], $output);

        $args = ['config1' => 'test-pgsql', 'argument2' => 'posts'];
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertNotEquals('[]', $test->getDisplay());

        $args = ['config' => 'test-pgsql', 'table' => 'posts'];
        $test = $this->exec('truncate', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $args = ['config1' => 'test-pgsql', 'argument2' => 'posts'];
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals('[]', trim($test->getDisplay()));

        $args = [
            'source' => 'test-mariadb',
            'destination' => 'test-pgsql',
            'table' => 'posts',
        ];
        $test = $this->exec('cp', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('cp', $args, ['n']);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);

        $catPosts = __DIR__ . '/expected/cat-posts.json';
        $catPosts = file_get_contents($catPosts);
        $catPosts = json_decode($catPosts, true);

        $args = ['config1' => 'test-pgsql', 'argument2' => 'posts'];
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($catPosts, $output);

        $args = [
            'source' => 'test-mariadb',
            'destination' => 'test-mysql',
            'table' => 'posts',
        ];
        $test = $this->exec('cp', $args, ['n']);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('cp', ['']);
        $this->assertCompleteDatabase('cp', ['test-mysql', '']);
        $this->assertCompleteContains(
            'cp',
            ['test-mysql', 'test-pgsql', ''],
            ['posts', 'products', 'users'],
        );
    }
}
