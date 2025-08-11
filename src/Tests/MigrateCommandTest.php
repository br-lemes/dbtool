<?php
declare(strict_types=1);

namespace DBTool\Tests;

final class MigrateCommandTest extends AbstractCommandTestCase
{
    function testComplete(): void
    {
        $this->assertCompleteDatabase('migrate', ['']);
        $this->assertCompleteEquals(
            'migrate',
            ['test-mysql', ''],
            [
                '20250807015230 Users',
                '20250807015231 Posts',
                '20250807015232 Products',
            ],
        );
    }
}
