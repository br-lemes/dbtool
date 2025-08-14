<?php
declare(strict_types=1);

namespace DBTool\Tests;

use DBTool\Database\DatabaseConnection;
use DBTool\Traits\ConfigTrait;
use Exception;
use PHPUnit\Framework\TestCase;

class DatabaseConnectionTest extends TestCase
{
    use ConfigTrait;

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

    function testGetTableSchemaFails(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Error querying table 'posts':");

        $db = new DatabaseConnection('test-mysql-fail');
        $db->getTableSchema('posts');
    }
}
