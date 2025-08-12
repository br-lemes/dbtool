<?php
declare(strict_types=1);

namespace DBTool\Traits;

use DBTool\Database\MySQLDriver;
use DBTool\Database\PgSQLDriver;

trait ConstTrait
{
    const DRIVERS = [
        'mysql' => MySQLDriver::class,
        'pgsql' => PgSQLDriver::class,
    ];

    const COLUMN_ORDER = ['custom', 'native'];
    const FAILED_CONFIG = 'Failed to load configuration file: %s';
    const INVALID_COLUMN_ORDER =
        'Invalid value for column order.' .
        " Must be 'custom' or 'native', got '%s'.";
    const INVALID_IGNORE_LENGTH =
        'Invalid value for ignore-length.' .
        " Must be 'yes' or 'no', got '%s'.";
    const MISSING_REQUIRED = 'Missing required configuration: %s';
    const REQUIRED_PHINX = ['database', 'host', 'paths.migrations', 'username'];
    const SCHEMAS_NOT_COMPATIBLE = 'Table schemas are not compatible (column names differ).';
    const TABLE_DOES_NOT_EXIST = "Table '%s' does not exist.";
    const UNSUPPORTED_DRIVER = 'Unsupported driver: %s';

    const TEST_CONFIGS = ['test-mariadb', 'test-mysql', 'test-pgsql'];
    const TEST_POST_COLUMNS = [
        'id',
        'user_id',
        'content',
        'publish_date',
        'title',
        'created_at',
    ];
    const TEST_TABLES = ['phinxlog', 'posts', 'products', 'users'];
    const TEST_TABLES_NO_PHINXLOG = ['posts', 'products', 'users'];
}
