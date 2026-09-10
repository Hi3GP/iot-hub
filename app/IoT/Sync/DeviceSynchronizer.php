<?php

namespace App\IoT\Sync;

use App\IoT\Connectors\Contracts\DeviceConnector;
use App\IoT\Connectors\DTO\ExternalDevice;
use App\Models\Connector;
use App\Models\Device;
use App\Models\DeviceProduct;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * 设备同步器 —— 把平台侧设备归一化写入统一设备表。
 */
class DeviceSynchronizer
{
    public function sync(Connector $connector, ?DeviceConnector $deviceConnector = null): array
    {
        $deviceConnector ??= app(\App\IoT\Registry\ConnectorManager::class)->make($connector);

        $stats = ['created' => 0, 'updated' => 0, 'deactivated' => 0];

        DB::transaction(function () use ($connector, $deviceConnector, &$stats) {
            $seen = [];

            foreach ($deviceConnector->pullDevices($connector) as $external) {
                $seen[$external->externalId] = true;

                $product = $this->resolveProduct($connector, $external);

                $device = Device::updateOrCreate(
                    [
                        'connector_id' => $connector->id,
                        'external_id' => $external->externalId,
                    ],
                    [
                        'device_product_id' => $product?->id,
                        'user_id' => $connector->user_id,
                        'name' => $external->name,
                        'model' => $external->model,
                        'category' => $external->category,
                        'external_parent_id' => $external->parentId,
                        'status' => $this->mapStatus($external->online),
                        'status_updated_at' => now(),
                        'bound_type' => 'pull',
                        'is_active' => true,
                        'meta' => $external->meta ?: null,
                    ]
                );

                $device->wasRecentlyCreated ? $stats['created']++ : $stats['updated']++;
            }

            // 平台侧已删除的设备 → 标记不可用
            $stats['deactivated'] = Device::where('connector_id', $connector->id)
                ->where('bound_type', 'pull')
                ->whereNotIn('external_id', array_keys($seen) ?: ['__none__'])
                ->update(['is_active' => false, 'status' => 'offline']);

            $connector->update(['last_synced_at' => now()]);
        });

        return $stats;
    }

    protected function resolveProduct(Connector $connector, ExternalDevice $external): ?DeviceProduct
    {
        if (! $external->externalKey && ! $external->category) {
            return null;
        }

        return DeviceProduct::updateOrCreate(
            [
                'connector_id' => $connector->id,
                'external_key' => $external->externalKey ?? $external->category,
            ],
            [
                'name' => $external->meta['product_name'] ?? ($external->externalKey ?: \App\IoT\ThingModel::categoryName($external->category)),
                'category' => $external->category,
                'model' => $external->model,
            ]
        );
    }

    protected function mapStatus(?bool $online): string
    {
        return $online === null ? 'unknown' : ($online ? 'online' : 'offline');
    }
}
