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
        $this->assertEquals(['products', 'users'], $output);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'products', 'users'], $output);

        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'products', 'users'], $output);

        $test = $this->exec('mv', [
            'config1' => 'test-mysql',
            'argument2' => 'test-mariadb',
            'argument3' => 'posts',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'products', 'users'], $output);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['products', 'users'], $output);

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
        $this->assertEquals(['posts', 'products'], $output);

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
        $this->assertEquals(['postagens', 'products'], $output);

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

        $catSql = <<<SQL
            SELECT
                id,
                description_long,
                description_medium,
                description_tiny,
                ean,
                name,
                price,
                sku,
                status,
                stock
            FROM products
        SQL;
        $catArgs = ['config1' => 'test-pgsql', 'argument2' => $catSql];
        $test = $this->exec('cat', $catArgs);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals("[]\n", $test->getDisplay());

        $args['argument2'] = 'test-pgsql';
        $args['argument3'] = 'products';
        $test = $this->exec('mv', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $catProducts = __DIR__ . '/expected/cat-products.json';
        $catProducts = file_get_contents($catProducts);
        $catProducts = json_decode($catProducts, true);

        $test = $this->exec('cat', $catArgs);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals($catProducts, $output);

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $output = json_decode($test->getDisplay(), true);
        $this->assertEquals(['posts', 'products', 'users'], $output);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals("[]\n", $test->getDisplay());

        $args = [
            'config1' => 'test-mariadb',
            'argument2' => 'posts',
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
        $this->assertEquals(['products', 'users'], $output);
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
            ['posts', 'products', 'users'],
        );
    }
}
