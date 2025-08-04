<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class MoveCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['users'], $output);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'users'], $output);

        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'users'], $output);

        $test = $this->exec('mv', [
            'config1' => 'test-mysql',
            'argument2' => 'test-mariadb',
            'argument3' => 'posts',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'users'], $output);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['users'], $output);

        $args = [
            'config1' => 'test-pgsql',
            'argument2' => 'test-mysql',
            'argument3' => 'users',
        ];

        $test = $this->exec('mv', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);

        $test = $this->exec('mv', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts'], $output);

        $args['argument3'] = 'posts';
        $test = $this->exec('mv', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $this->assertEquals(
            "Table schemas are not compatible (column names differ).\n",
            $test->getDisplay(),
        );

        $test = $this->exec('mv', [
            'config1' => 'test-pgsql',
            'argument2' => 'posts',
            'argument3' => 'postagens',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['postagens'], $output);

        $args = [
            'config1' => 'test-mysql',
            'argument2' => 'test-mariadb',
            'argument3' => 'users',
        ];

        $test = $this->exec('mv', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);

        $test = $this->exec('mv', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'users'], $output);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals("[]\n", $test->getDisplay());

        $test = $this->exec('mv', [
            'config1' => 'test-mariadb',
            'argument2' => 'posts',
            'argument3' => 'users',
        ]);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('mv', ['']);
        $this->assertCompleteContains(
            'mv',
            ['test-mysql', ''],
            ['test-mariadb', 'test-mysql', 'test-pgsql', 'posts', 'users'],
        );
        $this->assertCompleteEquals(
            'mv',
            ['test-mysql', 'test-pgsql', ''],
            ['posts', 'users'],
        );
    }
}
