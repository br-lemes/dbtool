<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use DBTool\Traits\ExpectedTrait;
use Exception;
use Symfony\Component\Console\Command\Command;

final class MoveCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use ExpectedTrait;

    function testCommand(): void
    {
        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['phinxlog', 'posts']);
        $this->assertEquals($expected, $actual);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $actual = json_decode($test->getDisplay(), true);
        $this->assertEquals(self::TEST_TABLES, $actual);

        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['phinxlog']);
        $this->assertEquals($expected, $actual);

        $test = $this->exec('mv', [
            'config1' => 'test-mysql',
            'argument2' => 'test-mariadb',
            'argument3' => 'posts',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['phinxlog']);
        $this->assertEquals($expected, $actual);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['posts']);
        $this->assertEquals($expected, $actual);

        $args = [
            'config1' => 'test-pgsql',
            'argument2' => 'test-mysql',
            'argument3' => 'users',
        ];

        $test = $this->exec('mv', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
        $this->assertTrue($cancel);

        $test = $this->exec('mv', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['phinxlog', 'users']);
        $this->assertEquals($expected, $actual);

        $args['argument3'] = 'posts';
        $test = $this->exec('mv', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $this->assertEquals(
            self::SCHEMAS_NOT_COMPATIBLE . "\n",
            $test->getDisplay(),
        );

        $test = $this->exec('mv', [
            'config1' => 'test-pgsql',
            'argument2' => 'posts',
            'argument3' => 'postagens',
        ]);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-pgsql']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['phinxlog', 'posts', 'users']);
        array_unshift($expected, 'postagens');
        $this->assertEquals($expected, $actual);

        $args = [
            'config1' => 'test-mysql',
            'argument2' => 'test-mariadb',
            'argument3' => 'users',
        ];

        $test = $this->exec('mv', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
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

        $catProducts = $this->getExpectedJson('cat-products.json');

        $test = $this->exec('cat', $catArgs);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $actual = json_decode($test->getDisplay(), true);
        $this->assertEquals($catProducts, $actual);

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['phinxlog']);
        $this->assertEquals($expected, $actual);

        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['posts', 'products', 'users']);
        $this->assertEquals($expected, $actual);

        $args = [
            'config1' => 'test-mariadb',
            'argument2' => 'posts',
            'argument3' => 'users',
        ];
        $test = $this->exec('mv', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
        $this->assertTrue($cancel);

        $test = $this->exec('mv', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $test = $this->exec('ls', ['config' => 'test-mariadb']);
        $actual = json_decode($test->getDisplay(), true);
        $expected = $this->pruneTestTables(['phinxlog', 'posts']);
        $this->assertEquals($expected, $actual);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('mv', ['']);
        $this->assertCompleteContains(
            'mv',
            ['test-mysql', ''],
            array_merge(self::TEST_CONFIGS, self::TEST_TABLES),
        );
        $this->assertCompleteEquals(
            'mv',
            ['test-mysql', 'test-pgsql', ''],
            self::TEST_TABLES,
        );
    }

    function testError(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Error renaming table 'users' to 'backup':",
        );

        $this->exec(
            'mv',
            [
                'config1' => 'test-mysql-fail',
                'argument2' => 'users',
                'argument3' => 'backup',
            ],
            ['y'],
        );
    }
}
