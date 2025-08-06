<?php
declare(strict_types=1);

namespace DBTool;

trait ConstTrait
{
    const COLUMN_ORDER = ['custom', 'native'];
    const INVALID_COLUMN_ORDER =
        'Invalid value for column order.' .
        " Must be 'custom' or 'native', got '%s'.";
    const INVALID_IGNORE_LENGTH =
        'Invalid value for ignore-length.' .
        " Must be 'yes' or 'no', got '%s'.";
    const SCHEMAS_NOT_COMPATIBLE = 'Table schemas are not compatible (column names differ).';
    const TABLE_DOES_NOT_EXIST = "Table '%s' does not exist.";

    const TEST_CONFIGS = ['test-mariadb', 'test-mysql', 'test-pgsql'];
    const TEST_POST_COLUMNS = [
        'id',
        'user_id',
        'content',
        'publish_date',
        'title',
        'created_at',
    ];
    const TEST_TABLES = ['posts', 'products', 'users'];
}
