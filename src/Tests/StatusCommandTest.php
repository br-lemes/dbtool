<?php
declare(strict_types=1);

namespace DBTool\Tests;

final class StatusCommandTest extends AbstractCommandTestCase
{
    function testComplete(): void
    {
        $this->assertCompleteDatabase('status', ['']);
    }
}
