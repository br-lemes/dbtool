<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class DumpCommandTest extends AbstractCommandTestCase
{
    private array $dumpFiles = [];

    function tearDown(): void
    {
        foreach ($this->dumpFiles as $dumpFile) {
            if (file_exists($dumpFile)) {
                unlink($dumpFile);
            }
        }
        parent::tearDown();
    }

    function testMySQL(): void
    {
        $dumpFile = __DIR__ . 'dump-mysql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args = [
            'config' => 'test-mysql',
            'table' => 'users',
            '--compact' => true,
            '--output' => $dumpFile,
        ];
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $expected = file_get_contents(__DIR__ . '/expected/dump-mysql.sql');
        $actual = file_get_contents($dumpFile);
        $this->assertEquals($expected, $actual);

        $dumpFile = __DIR__ . 'dump-schema-mysql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args['--output'] = $dumpFile;
        $args['--schema-only'] = true;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $expected = file_get_contents(
            __DIR__ . '/expected/dump-schema-mysql.sql',
        );
        $actual = file_get_contents($dumpFile);
        $this->assertEquals($expected, $actual);

        $dumpFile = __DIR__ . 'dump-pgsql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args['config'] = 'test-pgsql';
        $args['--output'] = $dumpFile;
        $args['--schema-only'] = false;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $expected = file_get_contents(__DIR__ . '/expected/dump-pgsql.sql');
        $actual = file_get_contents($dumpFile);
        $this->assertEquals($expected, $actual);

        $dumpFile = __DIR__ . 'dump-schema-pgsql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args['--output'] = $dumpFile;
        $args['--schema-only'] = true;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $expected = file_get_contents(
            __DIR__ . '/expected/dump-schema-pgsql.sql',
        );
        $actual = file_get_contents($dumpFile);
        $this->assertEquals($expected, $actual);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('dump', ['']);
        $this->assertCompleteContains(
            'dump',
            ['test-mysql', ''],
            ['posts', 'users'],
        );
        $this->assertCompleteContains(
            'dump',
            ['test-mysql', '-o', ''],
            ['config/', 'src/', 'vendor/'],
        );
    }
}
