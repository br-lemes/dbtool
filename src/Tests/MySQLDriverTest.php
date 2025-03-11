<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Database\MySQLDriver;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\ConsoleOutput;
use Symfony\Component\Console\Output\OutputInterface;

class MySQLDriverTest extends TestCase
{
    private OutputInterface $output;

    function setUp(): void
    {
        parent::setUp();
        $this->output = new ConsoleOutput();
    }

    function testMissingConfig(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            'Missing required server configuration: host, database, username',
        );
        new MySQLDriver([], $this->output);
    }

    function testBuildDSN(): void
    {
        $config = [
            'host' => 'localhost',
            'database' => 'test',
            'username' => 'root',
        ];
        $driver = new MySQLDriver($config, $this->output);
        $dsn = $driver->buildDSN();
        $this->assertEquals(
            'mysql:host=localhost;port=3306;dbname=test;charset=utf8mb4',
            $dsn,
        );
    }
}
