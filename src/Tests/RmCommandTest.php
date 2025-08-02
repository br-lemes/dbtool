<?php
declare(strict_types=1);

namespace DBTool\Tests;

use Symfony\Component\Console\Command\Command;

final class RmCommandTest extends AbstractCommandTestCase
{
    function testCommand(): void
    {
        $args = ['config' => 'test-mysql', 'table' => 'uses'];
        $test = $this->exec('rm', $args);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());

        $args['table'] = 'posts';
        $test = $this->exec('rm', $args, ['n']);
        $this->assertEquals(Command::FAILURE, $test->getStatusCode());
        $cancel = str_ends_with($test->getDisplay(), "Operation cancelled.\n");
        $this->assertTrue($cancel);

        $test = $this->exec('rm', $args, ['y']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $test = $this->exec('ls', ['config' => 'test-mysql']);
        $this->assertEquals(Command::SUCCESS, $test->getStatusCode());
        $this->assertEquals(['users'], json_decode($test->getDisplay(), true));
    }

    function testComplete(): void
    {
        $this->assertCompleteDatabase('rm', ['']);
        $this->assertCompleteEquals(
            'rm',
            ['test-mysql', ''],
            ['posts', 'users'],
        );
    }
}
