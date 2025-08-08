<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\ConstTrait;
use Symfony\Component\Console\Command\Command;

final class DumpCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use GetExpectedTrait;

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

    function testCommand(): void
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

        $actual = file_get_contents($dumpFile);
        $this->assertEquals($this->getExpected('dump-mysql.sql'), $actual);

        $dumpFile = __DIR__ . 'dump-schema-mysql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args['--output'] = $dumpFile;
        $args['--schema-only'] = true;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $actual = file_get_contents($dumpFile);
        $expected = $this->getExpected('dump-schema-mysql.sql');
        $this->assertEquals($expected, $actual);

        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);

        unset($args['--output']);
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $expected = $this->getExpected('dump-schema-mysql.sql');
        $this->assertEquals($expected, $test->getDisplay());

        $dumpFile = __DIR__ . 'dump-pgsql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args['config'] = 'test-pgsql';
        $args['--output'] = $dumpFile;
        $args['--schema-only'] = false;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $actual = file_get_contents($dumpFile);
        $this->assertEquals($this->getExpected('dump-pgsql.sql'), $actual);

        $dumpFile = __DIR__ . 'dump-schema-pgsql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args['--output'] = $dumpFile;
        $args['--schema-only'] = true;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $actual = file_get_contents($dumpFile);
        $expected = $this->getExpected('dump-schema-pgsql.sql');
        $this->assertEquals($expected, $actual);
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('dump', ['']);
        $this->assertCompleteEquals(
            'dump',
            ['test-mysql', ''],
            self::TEST_TABLES,
        );
        $this->assertCompleteContains(
            'dump',
            ['test-mysql', '-o', ''],
            ['config/', 'src/', 'vendor/'],
        );
    }
}
