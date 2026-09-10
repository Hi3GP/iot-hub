<?php

namespace App\IoT\Automation;

use App\Models\Alert;
use App\Models\Device;
use App\Models\DeviceProperty;
use App\Models\LinkageLog;
use App\Models\LinkageRule;

/**
 * 联动引擎 —— 只认统一物模型，天然支持跨平台联动。
 *
 * 例：萤石摄像头事件 → LifeSmart 开关打开；
 *     CTWing 水浸传感器 → 任意平台声光报警 + 告警。
 */
class LinkageEngine
{
    public function evaluatePropertyChange(Device $device, string $code, mixed $value): void
    {
        LinkageRule::where('enabled', true)->get()->each(function (LinkageRule $rule) use ($device, $code, $value) {
            $matched = collect($rule->triggers ?? [])->contains(
                fn (array $t) => ($t['type'] ?? '') === 'property_changed'
                    && (int) ($t['device_id'] ?? 0) === $device->id
                    && ($t['code'] ?? null) === $code
                    && $this->valueMatches($value, $t['operator'] ?? null, $t['value'] ?? null)
            );

            if ($matched) {
                $this->fire($rule, ['trigger' => 'property_changed', 'device_id' => $device->id, 'code' => $code, 'value' => $value]);
            }
        });
    }

    public function evaluateEvent(Device $device, string $eventType): void
    {
        LinkageRule::where('enabled', true)->get()->each(function (LinkageRule $rule) use ($device, $eventType) {
            $matched = collect($rule->triggers ?? [])->contains(
                fn (array $t) => ($t['type'] ?? '') === 'event'
                    && (int) ($t['device_id'] ?? 0) === $device->id
                    && ($t['event'] ?? null) === $eventType
            );

            if ($matched) {
                $this->fire($rule, ['trigger' => 'event', 'device_id' => $device->id, 'event' => $eventType]);
            }
        });
    }

    public function evaluateStatus(Device $device, string $status): void
    {
        LinkageRule::where('enabled', true)->get()->each(function (LinkageRule $rule) use ($device, $status) {
            $matched = collect($rule->triggers ?? [])->contains(
                fn (array $t) => ($t['type'] ?? '') === 'status'
                    && (int) ($t['device_id'] ?? 0) === $device->id
                    && ($t['status'] ?? null) === $status
            );

            if ($matched) {
                $this->fire($rule, ['trigger' => 'status', 'device_id' => $device->id, 'status' => $status]);
            }
        });
    }

    protected function fire(LinkageRule $rule, array $context): void
    {
        // 冷却期检查
        if ($rule->cooldown_seconds > 0 && $rule->last_triggered_at?->gt(now()->subSeconds($rule->cooldown_seconds))) {
            LinkageLog::create([
                'linkage_rule_id' => $rule->id,
                'status' => 'skipped',
                'message' => '冷却期内，跳过触发',
                'payload' => $context,
            ]);

            return;
        }

        $rule->update(['last_triggered_at' => now()]);

        // 条件判断：全部满足才执行
        foreach ($rule->conditions ?? [] as $condition) {
            if (! $this->conditionSatisfied($condition)) {
                LinkageLog::create([
                    'linkage_rule_id' => $rule->id,
                    'status' => 'skipped',
                    'message' => '条件不满足',
                    'payload' => $context,
                ]);

                return;
            }
        }

        // 执行动作
        foreach ($rule->actions ?? [] as $action) {
            try {
                $this->runAction($action, $context);
            } catch (\Throwable $e) {
                LinkageLog::create([
                    'linkage_rule_id' => $rule->id,
                    'status' => 'failed',
                    'message' => $e->getMessage(),
                    'payload' => ['action' => $action, 'context' => $context],
                ]);

                report($e);
            }
        }

        LinkageLog::create([
            'linkage_rule_id' => $rule->id,
            'status' => 'success',
            'message' => '联动执行完成',
            'payload' => $context,
        ]);
    }

    protected function runAction(array $action, array $context): void
    {
        match ($action['type'] ?? '') {
            'device_command' => $this->runDeviceCommand($action),
            'alert' => Alert::create([
                'device_id' => $action['device_id'] ?? null,
                'user_id' => isset($action['device_id'])
                    ? Device::find($action['device_id'])?->user_id
                    : ($context['rule_user_id'] ?? null),
                'level' => $action['level'] ?? 'warning',
                'type' => 'linkage',
                'title' => $action['title'] ?? '联动告警',
                'content' => $action['content'] ?? null,
                'triggered_at' => now(),
                'meta' => $context,
            ]),
            default => throw new \InvalidArgumentException('未知联动动作类型 ['.($action['type'] ?? '').']'),
        };
    }

    protected function runDeviceCommand(array $action): void
    {
        $device = Device::with('connector')->find($action['device_id'] ?? 0);

        if (! $device) {
            throw new \InvalidArgumentException('联动目标设备不存在');
        }

        app(\App\IoT\Registry\ConnectorManager::class)
            ->make($device->connector)
            ->sendCommand($device->connector, $device, $action['code'], $action['value'] ?? null);
    }

    protected function conditionSatisfied(array $condition): bool
    {
        $actual = DeviceProperty::query()
            ->where('device_id', $condition['device_id'] ?? 0)
            ->where('code', $condition['code'] ?? '')
            ->value('value');

        return $this->valueMatches($actual, $condition['operator'] ?? '=', $condition['value'] ?? null);
    }

    public function valueMatches(mixed $actual, ?string $operator, mixed $expected, ?object $product = null): bool
    {
        if ($operator === null) {
            return true; // 只要属性变化即匹配
        }

        // JSON 类设备单元（如天翼云透传状态）：深比较
        if (is_array($actual) || is_array($expected)) {
            return json_encode($this->deepNormalize($actual)) === json_encode($this->deepNormalize($expected));
        }

        $actual = $this->normalize($actual);
        $expected = $this->normalize($expected);

        return match ($operator) {
            // 宽松比较："1" 与 1 与 true 视为一致（不同平台类型口径不同）
            '=', '==' => $actual == $expected,
            '!=', '<>' => $actual != $expected,
            '>', '>=', '<', '<=' => version_compare((string) $actual, (string) $expected, $operator),
            default => false,
        };
    }

    /** JSON 递归归一：bool → 1/0、数字字符串保持原样后统一编码比较 */
    protected function deepNormalize(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_array($value)) {
            return array_map(fn ($v) => $this->deepNormalize($v), $value);
        }

        return $value;
    }

    protected function normalize(mixed $value): mixed
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_string($value) && in_array(strtolower($value), ['true', 'false'])) {
            return strtolower($value) === 'true' ? 1 : 0;
        }

        return $value;
    }
}
