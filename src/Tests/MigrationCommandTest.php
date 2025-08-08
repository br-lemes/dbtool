<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\ConstTrait;
use Symfony\Component\Console\Command\Command;

final class MigrationCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use GetExpectedTrait;

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
        $this->migrationFile = __DIR__ . 'actual-migration.php';
        $args = [
            'config' => 'test-mysql',
            'table' => 'postagens',
            '--output' => substr($this->migrationFile, 0, -4),
        ];
        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $this->assertEquals(
            sprintf(self::TABLE_DOES_NOT_EXIST, 'postagens') . "\n",
            $test->getDisplay(),
        );

        $args['table'] = 'posts';
        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($this->migrationFile);

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015231_posts.php');
        $this->assertEquals($expected, $actual);

        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);

        $args['table'] = 'products';
        $test = $this->exec('migration', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $actual = file_get_contents($this->migrationFile);
        $expected = $this->getMigration('20250807015232_products.php');
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
