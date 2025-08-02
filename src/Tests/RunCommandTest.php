<?php
declare(strict_types=1);

namespace DBTool\Tests;

final class RunCommandTest extends AbstractCommandTestCase
{
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
                'src/Tests/fixture/test-mysql.sql',
                'src/Tests/fixture/test-pgsql.sql',
            ],
        );
    }
}
