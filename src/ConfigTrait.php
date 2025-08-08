<?php
declare(strict_types=1);

namespace DBTool;

use DBTool\Database\UtilitiesTrait;

trait ConfigTrait
{
    use ConstTrait;
    use UtilitiesTrait;

    protected function getConfig(string $config, array $required = []): array
    {
        $path = realpath(__DIR__ . '/../config');
        $config = require "$path/$config.php";

        if (!is_array($config)) {
            $this->error('Invalid configuration file');
        }

        $config['driver'] = $config['driver'] ?? 'mysql';
        if (!array_key_exists($config['driver'], self::DRIVERS)) {
            $this->error("Unsupported driver: {$config['driver']}");
        }

        switch ($config['driver']) {
            case 'mysql':
                $config['port'] = $config['port'] ?? 3306;
                break;
            case 'pgsql':
                $config['port'] = $config['port'] ?? 5432;
                break;
        }

        $missing = $this->getMissing($config, $required);
        if (!empty($missing)) {
            $this->error(
                'Missing required configuration: ' . implode(', ', $missing),
            );
        }

        if (!in_array('paths.migrations', $required)) {
            return $config;
        }

        $path = $config['paths']['migrations'];
        if (!is_string($path) || !is_dir($path)) {
            $this->error('Invalid migrations path');
        }

        return $config;
    }

    private function getMissing(array $config, array $required): array
    {
        $missing = [];
        foreach ($required as $field) {
            if (strpos($field, '.') === false) {
                if (!isset($config[$field])) {
                    $missing[] = $field;
                }
                continue;
            }
            $keys = explode('.', $field);
            $temp = $config;
            $found = true;
            foreach ($keys as $key) {
                if (!isset($temp[$key])) {
                    $found = false;
                    break;
                }
                $temp = $temp[$key];
            }
            if (!$found) {
                $missing[] = $field;
            }
        }
        return $missing;
    }
}
