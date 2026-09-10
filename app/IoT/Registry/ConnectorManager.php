<?php

namespace App\IoT\Registry;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\Models\Connector;
use InvalidArgumentException;

/**
 * 连接器注册中心 —— 平台扩展的唯一入口。
 *
 * 已内置驱动：local / ezviz / ctwing / lifesmart / mihome
 * 扩展新平台：ConnectorManager::register('xxx', XxxConnector::class)
 * 建议在 AppServiceProvider 中注册自定义驱动。
 */
class ConnectorManager
{
    /** @var array<string, class-string<DeviceConnector>> */
    protected array $drivers = [];

    public function __construct()
    {
        $this->drivers = [
            'local' => \App\IoT\Connectors\Local\LocalConnector::class,
            'ezviz' => \App\IoT\Connectors\Ezviz\EzvizConnector::class,
            'ctwing' => \App\IoT\Connectors\CTWing\CTWingConnector::class,
            'aqara' => \App\IoT\Connectors\Aqara\AqaraConnector::class,
            'lifesmart' => \App\IoT\Connectors\LifeSmart\LifeSmartConnector::class,
            'mihome' => \App\IoT\Connectors\MiHome\MiHomeConnector::class,
        ];
    }

    public function register(string $driver, string $class): void
    {
        if (! is_subclass_of($class, DeviceConnector::class)) {
            throw new InvalidArgumentException("[$class] 必须实现 DeviceConnector 接口");
        }

        $this->drivers[$driver] = $class;
    }

    /** @return array<string, class-string<DeviceConnector>> */
    public function drivers(): array
    {
        return $this->drivers;
    }

    public function has(string $driver): bool
    {
        return isset($this->drivers[$driver]);
    }

    public function make(Connector $connector): DeviceConnector
    {
        $class = $this->drivers[$connector->driver] ?? null;

        if (! $class) {
            throw new InvalidArgumentException("未知的连接器驱动 [{$connector->driver}]");
        }

        return app($class);
    }
}
