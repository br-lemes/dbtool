<?php
declare(strict_types=1);

return [
    'driver' => 'pgsql',
    'host' => 'localhost',
    'port' => 15432,
    'database' => 'test_db',
    'username' => 'test_user',
    'password' => 'test_password',
    'paths' => ['migrations' => __DIR__ . '/../src/Tests/migration'],
];
