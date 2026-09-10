<?php

namespace App\Console\Commands;

use App\Models\Connector;
use App\IoT\Registry\ConnectorManager;
use App\IoT\Support\DeviceStateService;
use Illuminate\Console\Command;

class IotRefreshStatusCommand extends Command
{
    protected $signature = 'iot:refresh-status {--connector= : 指定连接器ID}';

    protected $description = '轮询刷新各平台设备在线状态与属性';

    public function handle(ConnectorManager $manager, DeviceStateService $states): int
    {
        Connector::where('is_active', true)
            ->when($this->option('connector'), fn ($q, $v) => $q->where('id', $v))
            ->get()
            ->each(function (Connector $connector) use ($manager, $states, &$failed) {
                $driver = $manager->make($connector);

                if (! in_array('status', $driver->capabilities(), true)) {
                    return;
                }

                $connector->devices()->where('is_active', true)->chunkById(100, function ($devices) use ($connector, $driver, $states, $failed) {
                    foreach ($devices as $device) {
                        try {
                            $props = $driver->fetchStatus($connector, $device);

                            $states->reportProperties($device, array_diff_key($props, ['online' => 1]));
                            $states->reportStatus($device, $props['online'] ?? null);
                        } catch (\Throwable $e) {
                            $failed++;
                            $this->warn("[{$device->name}] 状态刷新失败: {$e->getMessage()}");
                        }
                    }
                });
            });

        return self::SUCCESS;
    }
}
