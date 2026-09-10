<?php

namespace App\Filament\Resources\Connectors\Pages;

use App\Filament\Resources\Connectors\ConnectorResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\EditRecord;

class EditConnector extends EditRecord
{
    protected static string $resource = ConnectorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            EditAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // 旧版 CTWing 配置（单个 product_id/master_key）→ 新版产品列表
        if (($data['driver'] ?? null) === 'ctwing') {
            $settings = $data['settings'] ?? [];

            if (empty($settings['products']) && ! empty($settings['product_id'])) {
                $settings['products'] = [[
                    'product_id' => (string) $settings['product_id'],
                    'master_key' => $settings['master_key'] ?? null,
                    'name' => $settings['name'] ?? null,
                    'category' => $settings['category'] ?? null,
                ]];
            }

            unset($settings['product_id'], $settings['master_key']);

            // 修正错误的 API 域名：天翼云开放 API 官方网关为 ag-api.ctwing.cn，
            // 其余（如把租户号/MasterKey 当域名）一律清空走默认
            if (! empty($settings['base_url'])) {
                $host = strtolower(parse_url('http://'.ltrim($settings['base_url']), PHP_URL_HOST) ?? '');
                if ($host !== 'ag-api.ctwing.cn') {
                    $settings['base_url'] = null;
                }
            }

            $data['settings'] = $settings;
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (($data['driver'] ?? null) === 'ctwing') {
            $settings = $data['settings'] ?? [];

            // 清理旧字段，避免重复
            unset($settings['product_id'], $settings['master_key']);

            $data['settings'] = $settings;
        }

        return $data;
    }
}
