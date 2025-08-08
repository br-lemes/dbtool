<?php
declare(strict_types=1);

namespace DBTool\Tests;

final class RollbackCommandTest extends AbstractCommandTestCase
{
    function testComplete(): void
    {
        $this->assertCompleteDatabase('rollback', ['']);
    }
}
