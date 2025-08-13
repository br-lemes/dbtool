<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Traits\ConstTrait;
use Symfony\Component\Console\Command\Command;

final class TruncateCommandTest extends AbstractCommandTestCase
{
    use ConstTrait;

    function testCommand(): void
    {
        $args = ['config1' => 'test-mysql', 'argument2' => 'posts'];
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertNotEquals('[]', $test->getDisplay());

        $args = ['config' => 'test-mysql', 'table' => 'postagens'];
        $test = $this->exec('truncate', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $this->assertEquals(
            sprintf(self::TABLE_DOES_NOT_EXIST, 'postagens') . "\n",
            $test->getDisplay(),
        );

        $args['table'] = 'posts';
        $test = $this->exec('truncate', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), self::CANCELLED . "\n");
        $this->assertTrue($cancel);

        $test = $this->exec('truncate', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());

        $args = ['config1' => 'test-mysql', 'argument2' => 'posts'];
        $test = $this->exec('cat', $args);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals('[]', trim($test->getDisplay()));
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('truncate', ['']);
        $this->assertCompleteEquals(
            'truncate',
            ['test-mysql', ''],
            self::TEST_TABLES,
        );
    }
}
