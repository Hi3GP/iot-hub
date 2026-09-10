<?php

namespace App\IoT;

/**
 * 统一物模型 —— 跨平台的标准能力定义。
 *
 * 联动引擎只认这里的统一属性码，各连接器负责平台码 ↔ 统一码的映射。
 * 新平台接入时在映射表里补一列即可参与跨平台联动。
 */
class ThingModel
{
    /** 支持的标准属性码 => 描述 */
    public const PROPERTIES = [
        'switch' => ['name' => '开关', 'type' => 'bool'],
        'defence' => ['name' => '布防状态', 'type' => 'bool'],
        'brightness' => ['name' => '亮度', 'type' => 'int', 'unit' => '%'],
        'temperature' => ['name' => '温度', 'type' => 'float', 'unit' => '℃'],
        'humidity' => ['name' => '湿度', 'type' => 'float', 'unit' => '%'],
        'battery' => ['name' => '电量', 'type' => 'int', 'unit' => '%'],
        'motion' => ['name' => '人体感应', 'type' => 'bool'],
        'door_state' => ['name' => '门磁状态', 'type' => 'bool'],
        'water_leak' => ['name' => '水浸', 'type' => 'bool'],
        'smoke' => ['name' => '烟雾', 'type' => 'bool'],
        'alarm' => ['name' => '报警', 'type' => 'bool'],
        'meter_reading' => ['name' => '表读数', 'type' => 'float'],
        'signal' => ['name' => '信号强度', 'type' => 'int'],
    ];

    /** 支持的标准事件 */
    public const EVENTS = [
        'motion_detected' => '移动侦测',
        'door_open' => '门开',
        'door_close' => '门关',
        'alarm_triggered' => '报警触发',
        'offline' => '设备离线',
        'online' => '设备上线',
        'low_battery' => '低电量',
    ];

    /** 支持的设备品类 */
    public const CATEGORIES = [
        'camera' => '摄像头',
        'door_lock' => '门锁',
        'access_control' => '门禁',
        'switch' => '开关/插座',
        'light' => '照明',
        'sensor_env' => '环境传感器',
        'sensor_security' => '安防传感器',
        'meter' => '计量表',
        'alarm_device' => '报警设备',
        'gateway' => '网关',
        'other' => '其他',
    ];

    /** 统一属性码 → 各平台属性码映射 */
    public const PLATFORM_MAPPINGS = [
        'ezviz' => [
            'defence' => 'defence', // 0撤防/1布防
        ],
        'lifesmart' => [
            'switch' => 'io', // data:0/1
            'brightness' => 'dim',
        ],
        'ctwing' => [], // NB-IoT 产品各按物模型配置，运行时从 settings 读取
        'mihome' => [],
    ];

    public static function propertyName(string $code): string
    {
        return self::PROPERTIES[$code]['name'] ?? $code;
    }

    public static function categoryName(?string $category): string
    {
        return self::CATEGORIES[$category] ?? $category ?? '未知';
    }

    /**
     * 属性类型定义（自定义设备单元可用的类型）。
     * - bool: 布尔型，实际取值可按产品配置（如天翼云 1/0、LifeSmart 0/1、文本 open/close）
     * - enum: 枚举型，options 为 [{label, value}]
     * - json: 任意 JSON 结构（平台透传状态）
     */
    public const PROPERTY_TYPES = [
        'bool' => '布尔（开/关类）',
        'int' => '整数',
        'float' => '小数',
        'string' => '文本',
        'enum' => '枚举（多选一）',
        'json' => 'JSON 数据',
    ];

    /**
     * 产品自定义设备单元（device_products.thing_model.properties）。
     *
     * 每项结构：{code, name, type, unit?, on_value?, off_value?, options?: [{label, value}], writable?}
     *
     * @return array<int, array{code: string, name: string, type: string, unit?: ?string, on_value?: ?string, off_value?: ?string, options?: array, writable?: bool, source: 'product'}>
     */
    public static function productProperties(?object $product): array
    {
        $properties = $product?->thing_model['properties'] ?? [];

        return collect($properties)
            ->filter(fn ($p) => filled($p['code'] ?? null) && filled($p['name'] ?? null))
            ->map(fn ($p) => [
                'code' => (string) $p['code'],
                'name' => (string) $p['name'],
                'type' => in_array($p['type'] ?? 'string', array_keys(self::PROPERTY_TYPES)) ? $p['type'] : 'string',
                'unit' => $p['unit'] ?? null,
                'on_value' => $p['on_value'] ?? '1',
                'off_value' => $p['off_value'] ?? '0',
                'options' => collect($p['options'] ?? [])
                    ->filter(fn ($o) => isset($o['value']))
                    ->map(fn ($o) => ['label' => (string) ($o['label'] ?? $o['value']), 'value' => (string) $o['value']])
                    ->values()
                    ->all(),
                'writable' => (bool) ($p['writable'] ?? true),
                'source' => 'product',
            ])
            ->values()
            ->all();
    }

    /** 标准通用设备单元（ThingModel::PROPERTIES），作为产品未定义时的兜底 */
    public static function standardProperties(): array
    {
        return collect(self::PROPERTIES)
            ->map(fn ($def, $code) => [
                'code' => (string) $code,
                'name' => (string) $def['name'],
                'type' => $def['type'] ?? 'string',
                'unit' => $def['unit'] ?? null,
                'on_value' => '1',
                'off_value' => '0',
                'options' => [],
                'writable' => ! in_array($code, ['temperature', 'humidity', 'battery', 'signal', 'meter_reading', 'motion', 'door_state', 'water_leak', 'smoke', 'alarm']),
                'source' => 'standard',
            ])
            ->values()
            ->all();
    }

    /**
     * 设备可用的设备单元 = 产品自定义优先，标准单元补充（同 code 去重）。
     *
     * @return array<string, array> code => 定义
     */
    public static function propertiesFor(?object $product): array
    {
        $merged = collect(self::standardProperties())
            ->keyBy('code')
            ->merge(collect(self::productProperties($product))->keyBy('code'))
            ->all();

        return $merged;
    }

    /** 按属性定义把"开启/关闭"意图解析为平台实际值（如 1/0） */
    public static function resolvePropertyValue(?array $property, string $mode, mixed $custom = null): mixed
    {
        if ($mode === 'on' || $mode === 'off') {
            if ($property && ($property['type'] ?? '') === 'bool') {
                $raw = $mode === 'on' ? ($property['on_value'] ?? '1') : ($property['off_value'] ?? '0');

                return self::castValue(['type' => 'string'], $raw);
            }

            return $mode === 'on';
        }

        return self::castValue($property ?? [], $custom);
    }

    /** 按属性类型把表单输入转换为存储/下发值 */
    public static function castValue(?array $property, mixed $value): mixed
    {
        $type = $property['type'] ?? null;

        return match ($type) {
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'json' => is_string($value) ? (json_decode($value, true) ?? $value) : $value,
            'bool' => match (true) {
                is_bool($value) => $value,
                in_array($value, [1, '1', 'true', 'on', 'open'], true) => true,
                in_array($value, [0, '0', 'false', 'off', 'close'], true) => false,
                default => $value,
            },
            default => $value,
        };
    }

    /** 按属性定义生成表单值选项（enum 用） */
    public static function propertyValueOptions(?array $property): array
    {
        return collect($property['options'] ?? [])
            ->mapWithKeys(fn ($o) => [(string) $o['value'] => (string) $o['label']])
            ->all();
    }
}
