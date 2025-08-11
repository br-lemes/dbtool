<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\ConstTrait;
use Symfony\Component\Console\Command\Command;

final class CopyCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use GetExpectedTrait;

    function testCommand(): void
    {
        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['products', 'users'], $output);

        $test = $this->exec('cp', [
            'source' => 'test-pgsql',
            'destination' => 'test-mariadb',
            'table' => 'posts',
        ]);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $this->assertEquals(
            self::SCHEMAS_NOT_COMPATIBLE . "\n",
            $test->getDisplay(),
        );

        $test = $this->exec('cp', [
            'source' => 'test-mysql',
            'destination' => 'test-mariadb',
            'table' => 'posts',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(self::TEST_TABLES_NO_PHINXLOG, $output);

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

        $catPosts = $this->getExpectedJson('cat-posts.json');

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

        $test = $this->exec('cp', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $args = ['config1' => 'test-mysql', 'argument2' => 'posts'];
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($catPosts, $output);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('cp', ['']);
        $this->assertCompleteDatabase('cp', ['test-mysql', '']);
        $this->assertCompleteContains(
            'cp',
            ['test-mysql', 'test-pgsql', ''],
            self::TEST_TABLES,
        );
    }
}
