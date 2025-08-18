<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use DBTool\Traits\ExpectedTrait;
use Symfony\Component\Console\Command\Command;

final class DumpCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;
    use ExpectedTrait;

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
            '-c' => true,
            '-o' => $dumpFile,
        ];
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $actual = file_get_contents($dumpFile);
        $this->assertEquals($this->getExpected('dump-mysql.sql'), $actual);

        $dumpFile = __DIR__ . 'dump-schema-mysql.sql';
        $this->dumpFiles[] = $dumpFile;

        unset($args['table']);
        $args['-o'] = $dumpFile;
        $args['-s'] = true;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $actual = file_get_contents($dumpFile);
        $expected = $this->getExpected('dump-schema-mysql.sql');
        $this->assertEquals($expected, $actual);

        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
        $this->assertTrue($cancel);

        unset($args['-o']);
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $expected = $this->getExpected('dump-schema-mysql.sql');
        $this->assertEquals($expected, $test->getDisplay());

        $dumpFile = __DIR__ . 'dump-pgsql.sql';
        $this->dumpFiles[] = $dumpFile;

        $args['config'] = 'test-pgsql';
        $args['table'] = 'users';
        $args['-o'] = $dumpFile;
        $args['-s'] = false;
        $test = $this->exec('dump', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertFileExists($dumpFile);

        $actual = file_get_contents($dumpFile);
        $this->assertEquals($this->getExpected('dump-pgsql.sql'), $actual);

        $dumpFile = __DIR__ . 'dump-schema-pgsql.sql';
        $this->dumpFiles[] = $dumpFile;

        unset($args['table']);
        $args['-o'] = $dumpFile;
        $args['-s'] = true;
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
