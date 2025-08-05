<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class MigrationCommandTest extends AbstractCommandTestCase
{
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
            "Table 'postagens' does not exist.\n",
            $test->getDisplay(),
        );

        $args['table'] = 'posts';
        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($this->migrationFile);

        $expected = file_get_contents(__DIR__ . '/expected/migration.php');
        $actual = file_get_contents($this->migrationFile);
        $this->assertEquals($expected, $actual);

        $test = $this->exec('migration', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('migration', ['']);
        $this->assertCompleteEquals(
            'migration',
            ['test-mysql', ''],
            ['posts', 'products', 'users'],
        );
        $this->assertCompleteContains(
            'migration',
            ['test-mysql', '-o', ''],
            ['config/', 'src/', 'vendor/'],
        );
    }
}
