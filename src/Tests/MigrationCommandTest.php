<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use DBTool\Traits\ExpectedTrait;
use Symfony\Component\Console\Command\Command;

final class MigrationCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use ExpectedTrait;

    private string $migrationFile = '';

    function tearDown(): void
    {
        if ($this->migrationFile && file_exists($this->migrationFile)) {
            unlink($this->migrationFile);
        }
        parent::tearDown();
    }

    function testCommand(): void
    {
        $this->migrationFile = __DIR__ . '/actual-migration.php';
        $args = [
            'config' => 'test-mysql',
            'table' => 'postagens',
            '-o' => substr($this->migrationFile, 0, -4),
        ];
        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $this->assertEquals(
            sprintf(self::TABLE_DOES_NOT_EXIST, 'postagens') . "\n",
            $test->getDisplay(),
        );

        $args['table'] = 'users';
        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($this->migrationFile);

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015230_users.php');
        $this->assertEquals($expected, $actual);

        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
        $this->assertTrue($cancel);

        $args['table'] = 'posts';
        $test = $this->exec('migration', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015231_posts.php');
        $this->assertEquals($expected, $actual);

        $args['table'] = 'products';
        $test = $this->exec('migration', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015232_products.php');
        $this->assertEquals($expected, $actual);

        $args['table'] = 'user_groups';
        $test = $this->exec('migration', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015233_user_groups.php');
        $this->assertEquals($expected, $actual);

        $args['table'] = 'tags';
        $test = $this->exec('migration', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015234_tags.php');
        $this->assertEquals($expected, $actual);

        $args['table'] = 'post_tags';
        $test = $this->exec('migration', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015235_post_tags.php');
        $this->assertEquals($expected, $actual);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('migration', ['']);
        $this->assertCompleteEquals(
            'migration',
            ['test-mysql', ''],
            self::TEST_TABLES,
        );
        $this->assertCompleteContains(
            'migration',
            ['test-mysql', '-o', ''],
            ['config/', 'src/', 'vendor/'],
        );
    }
}
