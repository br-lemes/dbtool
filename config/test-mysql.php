<?php
declare(strict_types=1);

return [
    'host' => '127.0.0.1',
    'port' => 23306,
    'database' => 'test_db',
    'username' => 'root',
    'password' => 'root_password',
    'paths' => ['migrations' => __DIR__ . '/../src/Tests/migration'],
];
