<?php
declare(strict_types=1);

namespace DBTool\Traits;

trait ConfigTrait
{
    use AssertPatternTrait;
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

        $config = $config + ['batchSize' => 1000, 'driver' => 'mysql'];
        $reqSet = array_flip($required);
        switch ($config['driver']) {
            case 'mysql':
                $config = $config + ['password' => null, 'port' => 3306];
                $reqSet = $reqSet + [
                    'database' => true,
                    'host' => true,
                    'username' => true,
                ];
                break;
            case 'pgsql':
                $config = $config + [
                    'password' => null,
                    'port' => 5432,
                    'schema' => 'public',
                ];
                $reqSet = $reqSet + [
                    'database' => true,
                    'host' => true,
                    'schema' => true,
                    'username' => true,
                ];
                break;
            default:
                $this->error(
                    sprintf(self::UNSUPPORTED_DRIVER, $config['driver']),
                );
        }

        $required = array_keys($reqSet);
        $missing = $this->getMissing($config, $required);
        if (!empty($missing)) {
            $missing = implode(', ', $missing);
            $this->error(sprintf(self::MISSING_REQUIRED, $missing));
        }

        if (isset($reqSet['host'])) {
            $this->assertPattern($config['host'], '/^[a-zA-Z0-9.-]+$/', 'host');
        }

        $pattern = '/^[a-zA-Z0-9_]+$/';

        if (isset($reqSet['database'])) {
            $this->assertPattern($config['database'], $pattern, 'database');
        }
        if (isset($reqSet['username'])) {
            $this->assertPattern($config['username'], $pattern, 'username');
        }
        if (isset($reqSet['schema'])) {
            $this->assertPattern($config['schema'], $pattern, 'schema');
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
