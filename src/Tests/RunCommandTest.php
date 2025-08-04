<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class RunCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $bad = __DIR__ . '/fixture/test-bad.sql';
        $args = ['config' => 'test-mysql', 'script' => $bad];
        $test = $this->exec('run', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('run', ['']);
        $this->assertCompleteContains(
            'run',
            ['test-mysql', ''],
            ['config/', 'src/', 'vendor/'],
        );
        $this->assertCompleteEquals(
            'run',
            ['test-mysql', 'src/Tests/fixture/'],
            [
                'src/Tests/fixture/test-bad.sql',
                'src/Tests/fixture/test-mariadb.sql',
                'src/Tests/fixture/test-mysql.sql',
                'src/Tests/fixture/test-pgsql.sql',
            ],
        );
    }
}
