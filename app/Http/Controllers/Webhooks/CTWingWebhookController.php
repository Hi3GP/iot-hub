<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Models\DeviceLog;
use App\IoT\Support\DeviceLogService;
use App\IoT\Support\DeviceStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 天翼云 CTWing HTTP 消息推送回调
 * 支持消息类型：设备数据变化 / 指令响应 / 事件上报 / 上下线通知
 * 平台要求：收到后固定返回 HTTP 200（异步处理由队列/服务层承接）
 *
 * 自定义设备上报格式（自研门禁板）：
 *   开机:     {"v":1,"sid":1000,"mid":xxx}
 *   开锁成功: {"v":1,"sw":1,"sid":2000,"mid":xxx}
 *   超时关闭: {"v":1,"sw":0,"sid":2000,"mid":xxx}
 */
class CTWingWebhookController extends Controller
{
    public function __invoke(Request $request, DeviceStateService $states)
    {
        $payload = $request->json()->all();

        Log::channel('iot')->info('ctwing push', ['payload' => $payload]);

        $deviceId = (string) ($payload['deviceId'] ?? '');
        $messageType = (string) ($payload['messageType'] ?? '');

        if (! $deviceId || ! $channel = Device::where('external_id', $deviceId)->first()) {
            return response('ok');
        }

        // WIFI/4G 双通道合并产品：数据/事件/告警归属主通道（WIFI 优先），
        // 但通道行各自的在线状态仍单独更新，用于双通道监测
        $merged = $channel->isMergedChannelProduct();
        $device = $merged ? $channel->primaryChannel() : $channel;

        // 上下线通知
        if (str_contains(strtolower($messageType), 'online')) {
            $online = ! str_contains(strtolower($messageType), 'offline');

            if ($merged) {
                // 1. 记录该通道自身在线状态（仅更新，不产生事件/告警）
                $states->markChannelStatus($channel, $online);
                // 2. 主设备聚合状态：任一通道在线即在线（聚合状态变化才产生事件/日志/告警）
                $states->reportStatus($device->fresh(), $device->fresh()->anyChannelOnline());
            } else {
                $states->reportStatus($channel, $online);
            }

            return response('ok');
        }

        // 非上下线消息：合并产品有推送即视为上报通道在线
        if ($merged) {
            $states->markChannelStatus($channel, true);
            if ($device->fresh()->status !== 'online') {
                $states->reportStatus($device->fresh(), true);
            }
        }

        // 指令响应（SENT=平台已发送 / DELIVERED=设备已确认收到）
        if ($messageType === 'commandResponse') {
            $this->handleCommandResponse($device, $channel, $payload, $states);

            return response('ok');
        }

        // 提取设备数据（兼容字符串/对象/数组）
        $deviceData = $this->extractDeviceData($payload);

        if ($deviceData === null) {
            // 未知消息类型原样留痕，避免丢失
            $states->recordEvent($device, $messageType ?: 'push', $payload);

            return response('ok');
        }

        // 存储原始数据
        $states->reportProperties($device, ['raw_payload' => $deviceData]);

        // 解析自定义协议（内部记录归一化事件/日志）
        $this->parseCustomProtocol($device, $deviceData, $states, $channel);

        return response('ok');
    }

    /**
     * 指令响应：平台对下发指令的回执
     * resultCode: SENT=指令已发送（平台排队/发出），DELIVERED=设备已确认收到
     */
    protected function handleCommandResponse(Device $device, Device $channel, array $payload, DeviceStateService $states): void
    {
        $result = $payload['result'] ?? [];
        $code = is_array($result) ? (string) ($result['resultCode'] ?? '') : '';
        $taskId = $payload['taskId'] ?? null;
        $detail = is_array($result) ? ($result['resultDetail'] ?? null) : null;
        $network = $channel->channelNetwork();

        $title = match ($code) {
            'DELIVERED' => '指令已送达设备',
            'SENT' => '指令已发送至平台',
            'FAILED', 'FAIL', 'TIMEOUT' => '指令下发失败',
            default => '指令响应：' . ($code !== '' ? $code : '未知'),
        };

        $failed = in_array($code, ['FAILED', 'FAIL', 'TIMEOUT'], true);

        $states->recordEvent($device, 'command_response', array_filter([
            'result_code' => $code,
            'task_id' => $taskId,
            'channel' => $network,
            'detail' => $detail,
        ], fn ($v) => $v !== null && $v !== ''));

        app(DeviceLogService::class)->log(
            $device,
            DeviceLog::DIRECTION_IN,
            DeviceLog::TYPE_COMMAND,
            $title,
            array_filter([
                'resultCode' => $code,
                'taskId' => $taskId,
                'channel' => $network,
                'resultDetail' => $detail,
            ], fn ($v) => $v !== null && $v !== ''),
            $failed ? DeviceLog::RESULT_FAILED : DeviceLog::RESULT_SUCCESS
        );
    }

    /**
     * 从推送 payload 中提取设备数据
     * 兼容 deviceData / evtContent / data / payload 字段（CTWing MQTT 推送把设备数据放在 payload 键下），
     * 可能是字符串（JSON / base64）或对象
     */
    protected function extractDeviceData(array $payload): ?array
    {
        $raw = $payload['deviceData'] ?? $payload['evtContent'] ?? $payload['data'] ?? $payload['payload'] ?? null;

        if ($raw === null) {
            return null;
        }

        if (is_array($raw)) {
            return $raw;
        }

        // 字符串可能是 JSON 或 base64
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                return $decoded;
            }

            // 尝试 base64 解码
            $b64 = base64_decode($raw, true);
            if ($b64 !== false) {
                $decoded = json_decode($b64, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * 解析自研设备的自定义上报协议
     *
     * 字段说明：
     *   v   - 协议版本
     *   sid - 服务 ID（1000=开机，2000=门锁状态）
     *   sw  - 开关状态（1=开，0=关）
     *   mid - 消息 ID
     */
    protected function parseCustomProtocol(Device $device, array $data, DeviceStateService $states, ?Device $channel = null): void
    {
        $sid = (int) ($data['sid'] ?? 0);
        $logs = app(DeviceLogService::class);
        $network = $channel?->channelNetwork();
        $meta = array_filter(['channel' => $network], fn ($v) => $v !== null);

        // sid=1000 开机上报
        if ($sid === 1000) {
            $states->reportStatus($device, true);
            $states->recordEvent($device, 'power_on', array_merge($data, $meta));

            $logs->log($device, DeviceLog::DIRECTION_IN, DeviceLog::TYPE_EVENT, '门禁设备开机上报', array_merge($data, $meta), DeviceLog::RESULT_SUCCESS);

            return;
        }

        // sid=2000 门锁状态
        if ($sid === 2000) {
            $sw = $data['sw'] ?? null;
            if ($sw === null) {
                return;
            }

            $isOpen = (bool) $sw;

            // door_lock 属性：true=已开，false=已关
            $states->reportProperties($device, [
                'door_lock' => $isOpen,
            ]);

            $states->recordEvent($device, $isOpen ? 'door_unlocked' : 'door_locked', array_merge($data, $meta));

            // 记录门禁交互日志（订阅推送的开门结果），用于审计
            $logs->log(
                $device,
                DeviceLog::DIRECTION_IN,
                DeviceLog::TYPE_EVENT,
                $isOpen ? '门禁开锁成功' : '门禁超时自动关闭',
                array_merge($data, $meta),
                DeviceLog::RESULT_SUCCESS
            );

            return;
        }

        // 其他未知 sid，原样记录
        if ($sid > 0) {
            $states->recordEvent($device, "custom_sid_{$sid}", $data);
            $logs->log($device, DeviceLog::DIRECTION_IN, DeviceLog::TYPE_EVENT, "收到自定义上报（sid={$sid}）", array_merge($data, $meta), DeviceLog::RESULT_SUCCESS);
        }
    }
}
