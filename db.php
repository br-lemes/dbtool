#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use DBTool\DBTool;

$cliTool = new DBTool();
$cliTool->run($argv);
