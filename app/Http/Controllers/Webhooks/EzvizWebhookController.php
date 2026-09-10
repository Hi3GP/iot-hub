<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\IoT\Support\DeviceStateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 萤石云消息推送回调
 * 推送订阅：开放平台控制台配置推送地址，或调用 /api/lapp/msg/push/subscribe
 */
class EzvizWebhookController extends Controller
{
    public function __invoke(Request $request, DeviceStateService $states)
    {
        $payload = $request->json()->all();

        Log::channel('iot')->info('ezviz push', ['payload' => $payload]);

        $serial = data_get($payload, 'body.deviceSerial') ?? data_get($payload, 'deviceSerial');
        $msgType = data_get($payload, 'head.msgType') ?? data_get($payload, 'msgType');

        if ($serial && $device = Device::where('external_id', $serial)->first()) {
            $states->recordEvent($device, (string) ($msgType ?: 'push'), $payload);
        }

        // 萤石要求收到即回 200
        return response('ok');
    }
}
