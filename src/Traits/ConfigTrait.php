<?php
declare(strict_types=1);

namespace DBTool\Traits;

trait ConfigTrait
{
    use ConstTrait;
    use ErrorTrait;

    protected function getConfig(string $config, array $required = []): array
    {
        $path = realpath(__DIR__ . '/../../config');
        $file = "$path/$config.php";
        $config = @include $file;

        if (!$config || !is_array($config)) {
            $this->error(sprintf(self::FAILED_CONFIG, $file));
        }

        $config['driver'] = $config['driver'] ?? 'mysql';
        if (!array_key_exists($config['driver'], self::DRIVERS)) {
            $this->error(sprintf(self::UNSUPPORTED_DRIVER, $config['driver']));
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
            $missing = implode(', ', $missing);
            $this->error(sprintf(self::MISSING_REQUIRED, $missing));
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
