<?php

namespace App\Console\Commands;

use App\Models\Connector;
use App\IoT\Registry\ConnectorManager;
use App\IoT\Sync\DeviceSynchronizer;
use Illuminate\Console\Command;

class IotSyncCommand extends Command
{
    protected $signature = 'iot:sync {--connector= : 指定连接器ID} {--driver= : 指定驱动}';

    protected $description = '从各平台连接器同步设备到统一设备表';

    public function handle(ConnectorManager $manager, DeviceSynchronizer $synchronizer): int
    {
        $query = Connector::where('is_active', true)
            ->when($this->option('connector'), fn ($q, $v) => $q->where('id', $v))
            ->when($this->option('driver'), fn ($q, $v) => $q->where('driver', $v));

        $failed = 0;

        foreach ($query->get() as $connector) {
            $connectorConnector = $manager->make($connector);

            if (! in_array('pull', $connectorConnector->capabilities(), true)) {
                $this->line("跳过 [{$connector->name}]（{$connectorConnector->label()} 不支持拉取）");

                continue;
            }

            try {
                $stats = $synchronizer->sync($connector, $connectorConnector);
                $this->info("[{$connector->name}] 新增 {$stats['created']}，更新 {$stats['updated']}，停用 {$stats['deactivated']}");
            } catch (\Throwable $e) {
                $failed++;
                $this->error("[{$connector->name}] 同步失败: {$e->getMessage()}");
                report($e);
            }
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
