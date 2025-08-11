<?php
declare(strict_types=1);

namespace DBTool\Traits;

use Phinx\Config\Config;

trait PhinxConfigTrait
{
    use ConfigTrait;

    protected function getPhinxConfig(string $config, array $required): Config
    {
        $config = $this->getConfig($config, $required);
        return new Config([
            'paths' => ['migrations' => $config['paths']['migrations']],
            'environments' => [
                'default_environment' => 'env',
                'env' => array_filter(
                    [
                        'adapter' => $config['driver'],
                        'host' => $config['host'],
                        'name' => $config['database'],
                        'user' => $config['username'],
                        'pass' => $config['password'],
                        'port' => $config['port'],
                        'charset' => 'utf8',
                    ],
                    'strlen',
                ),
            ],
        ]);
    }
}
