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
            ['20250807015231_posts', '20250807015232_products'],
        );
    }
}
