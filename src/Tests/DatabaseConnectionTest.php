<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Database\DatabaseConnection;
use DBTool\Database\DatabaseDriver;
use DBTool\Traits\ConfigTrait;
use Exception;
use PDOException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class DatabaseConnectionTest extends TestCase
{
    use ConfigTrait;

    function testAssertPatternFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            "Parameter 'table' contains invalid characters.",
        );

        $db = new DatabaseConnection('test-mysql');
        $db->getColumns('invalid-table', 'custom');
    }

    function testConnectionFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error connecting to database');

        new DatabaseConnection('test-invalid');
    }

    function testExecFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error executing query:');

        $db = new DatabaseConnection('test-mysql-fail');
        $db->exec('SELECT * FROM posts');
    }

    function testGetColumnsFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            sprintf(self::ERROR_QUERY_TABLE, 'users'),
        );

        $db = new DatabaseConnection('test-mysql');
        $this->mockDriver($db, 'getColumns');
        $db->getColumns('users', 'custom');
    }

    function testGetKeysFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            sprintf(self::ERROR_QUERY_TABLE, 'users'),
        );

        $db = new DatabaseConnection('test-mysql');
        $this->mockDriver($db, 'getKeys');
        $db->getKeys('users', 'custom');
    }

    function testGetTableSchemaFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            sprintf(self::ERROR_QUERY_TABLE, 'posts'),
        );
        $db = new DatabaseConnection('test-mysql-fail');
        $db->getTableSchema('posts');
    }

    function testGetTablesFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Error querying tables:');

        $db = new DatabaseConnection('test-mysql');
        $this->mockDriver($db, 'getTables');
        $db->getTables();
    }

    function testTableExistsFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage(
            sprintf(self::ERROR_QUERY_TABLE, 'users'),
        );

        $db = new DatabaseConnection('test-mysql');
        $this->mockDriver($db, 'tableExists');
        $db->tableExists('users');
    }

    private function mockDriver(DatabaseConnection $db, string $method): void
    {
        $mockDriver = $this->createMock(DatabaseDriver::class);
        $mockDriver
            ->method($method)
            ->will($this->throwException(new PDOException()));
        $reflection = new ReflectionClass($db);
        $property = $reflection->getProperty('driver');
        $property->setAccessible(true);
        $property->setValue($db, $mockDriver);
    }
}
