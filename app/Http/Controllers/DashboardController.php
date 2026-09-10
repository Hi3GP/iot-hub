<?php

namespace App\Http\Controllers;

use App\IoT\Registry\ConnectorManager;
use App\IoT\ThingModel;
use App\Models\Alert;
use App\Models\DashboardScreen;
use App\Models\Device;
use App\Models\DeviceProperty;
use App\Models\Space;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * 数据大屏主页
     */
    public function index(Request $request)
    {
        $screen = $this->resolveScreen($request);

        $screens = DashboardScreen::query()
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        $parks = Space::where('type', 'park')->orderBy('sort')->get(['id', 'name']);

        return view('dashboard.index', [
            'screen' => $screen,
            'screens' => $screens,
            'parks' => $parks,
        ]);
    }

    /**
     * 大屏数据接口（JSON）
     */
    public function data(Request $request, ConnectorManager $connectors)
    {
        $screen = $this->resolveScreen($request);

        $spaceIds = $this->getSpaceIds($screen->space_id);

        $deviceQuery = Device::query()
            ->when($spaceIds, fn ($q) => $q->whereIn('space_id', $spaceIds));

        // 设备状态统计
        $total = (clone $deviceQuery)->count();
        $online = (clone $deviceQuery)->where('status', 'online')->count();
        $offline = (clone $deviceQuery)->where('status', 'offline')->count();
        $unknown = (clone $deviceQuery)->where('status', 'unknown')->count();

        $abnormal = Alert::query()
            ->where('status', 'active')
            ->when($spaceIds, fn ($q) => $q->whereHas('device', fn ($d) => $d->whereIn('space_id', $spaceIds)))
            ->distinct('device_id')
            ->count('device_id');

        // 设备分类统计
        $categories = (clone $deviceQuery)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->pluck('count', 'category');

        // 最近告警
        $alerts = $screen->show_alerts ? Alert::query()
            ->with('device')
            ->when($spaceIds, fn ($q) => $q->whereHas('device', fn ($d) => $d->whereIn('space_id', $spaceIds)))
            ->latest('triggered_at')
            ->limit(10)
            ->get(['id', 'device_id', 'level', 'title', 'content', 'status', 'triggered_at'])
            : collect();

        // 摄像头：优先使用配置中指定的摄像头；否则取园区在线摄像头
        // 通道设备（NVR 下挂）可直播；有下挂通道的 NVR 主设备自动排除
        $cameraQuery = Device::query()
            ->with('connector')
            ->where('status', 'online')
            ->viewableCameras();

        $selected = $screen->camera_device_ids;
        if (! empty($selected)) {
            $cameraQuery->whereIn('id', $selected);
        } elseif ($spaceIds) {
            $cameraQuery->whereIn('space_id', $spaceIds);
        }

        $cameras = $screen->show_cameras
            ? $cameraQuery->get(['id', 'name', 'external_id', 'connector_id'])
            : collect();

        $cameraPayload = $cameras->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'playUrl' => '/dashboard/camera/'.$c->id.'/play',
        ]);

        $widgets = $screen->show_widgets
            ? $this->buildWidgets($screen)
            : collect();

        return response()->json([
            'config' => [
                'layout' => $screen->layout,
                'carousel_enabled' => $screen->carousel_enabled,
                'carousel_interval' => $screen->carousel_interval,
                'refresh_interval' => $screen->refresh_interval,
                'show_stats' => $screen->show_stats,
                'show_categories' => $screen->show_categories,
                'show_cameras' => $screen->show_cameras,
                'show_alerts' => $screen->show_alerts,
                'show_widgets' => $screen->show_widgets,
            ],
            'stats' => [
                'total' => $total,
                'online' => $online,
                'offline' => $offline,
                'unknown' => $unknown,
                'abnormal' => $abnormal,
            ],
            'categories' => $categories,
            'alerts' => $alerts,
            'widgets' => $widgets,
            'cameras' => $cameraPayload,
        ]);
    }

    /**
     * 解析自定义数据卡片：拉取指定设备的属性当前值或在线状态
     */
    protected function buildWidgets(DashboardScreen $screen): \Illuminate\Support\Collection
    {
        $configs = $screen->widgets ?? [];

        $deviceIds = collect($configs)->pluck('device_id')->filter()->unique()->all();
        $devices = Device::with(['product', 'connector'])
            ->withTrashed()
            ->whereIn('id', $deviceIds)
            ->get()
            ->keyBy('id');

        $propertyValues = DeviceProperty::query()
            ->whereIn('device_id', $deviceIds)
            ->get()
            ->groupBy(fn ($p) => $p->device_id.':'.$p->code);

        return collect($configs)->map(function (array $w) use ($devices, $propertyValues) {
            $device = $devices->get($w['device_id'] ?? null);
            $title = $w['title'] ?? '未命名卡片';
            $type = $w['type'] ?? 'property';

            if (! $device) {
                return [
                    'title' => $title,
                    'device_name' => '设备不存在',
                    'value' => '--',
                    'unit' => '',
                    'status' => 'off',
                    'reported_at' => null,
                ];
            }

            if ($type === 'status') {
                $online = $device->status === 'online';

                return [
                    'title' => $title,
                    'device_name' => $device->name,
                    'value' => $online ? '在线' : ($device->status === 'offline' ? '离线' : '未知'),
                    'unit' => '',
                    'status' => $online ? 'online' : 'off',
                    'reported_at' => $device->status_updated_at?->toIso8601String(),
                ];
            }

            // 属性卡片
            $code = $w['code'] ?? '';
            $prop = $propertyValues->get($device->id.':'.$code)?->first();

            $def = ThingModel::propertiesFor($device->product)[$code] ?? [];
            $raw = $prop?->value;
            $display = $this->formatPropertyValue($def, $raw);

            return [
                'title' => $title,
                'device_name' => $device->name,
                'value' => $display,
                'unit' => $def['unit'] ?? '',
                'status' => $device->status === 'online' ? 'online' : 'off',
                'reported_at' => $prop?->reported_at?->toIso8601String(),
            ];
        });
    }

    /**
     * 按物模型定义格式化属性值（布尔→开启/关闭，枚举→标签）
     */
    protected function formatPropertyValue(array $def, mixed $raw): string
    {
        if ($raw === null || $raw === '') {
            return '--';
        }

        $type = $def['type'] ?? 'string';

        if ($type === 'bool') {
            $on = $def['on_value'] ?? '1';
            $off = $def['off_value'] ?? '0';

            if ((string) $raw === (string) $on || $raw === true || $raw === 1 || $raw === '1') {
                return '开启';
            }
            if ((string) $raw === (string) $off || $raw === false || $raw === 0 || $raw === '0') {
                return '关闭';
            }

            return (string) $raw;
        }

        if ($type === 'enum' && ! empty($def['options'])) {
            foreach ($def['options'] as $opt) {
                if ((string) ($opt['value'] ?? '') === (string) $raw) {
                    return $opt['label'] ?? (string) $raw;
                }
            }
        }

        return (string) $raw;
    }

    /**
     * 摄像头直播流地址（重定向到萤石云 HLS 地址）
     */
    public function camera(int $id, ConnectorManager $connectors)
    {
        $device = Device::findOrFail($id);

        if (! $device->connector) {
            abort(404);
        }

        $cacheKey = "camera_live:{$device->id}";
        $url = Cache::get($cacheKey);

        if (! $url) {
            try {
                $driver = $connectors->make($device->connector);
                if (method_exists($driver, 'liveUrl')) {
                    $url = $driver->liveUrl($device->connector, $device);
                    Cache::put($cacheKey, $url, 300);
                }
            } catch (\Throwable $e) {
                abort(503, '直播地址获取失败');
            }
        }

        if (! $url) {
            abort(404, '无可用直播流');
        }

        return redirect()->away($url);
    }

    /**
     * 独立视频播放页（供 iframe 嵌入：大屏、视频弹窗、设备详情）
     */
    public function play(int $id, Request $request, ConnectorManager $connectors)
    {
        $device = Device::with('connector')->findOrFail($id);

        $stream = null;
        $error = null;

        try {
            $driver = $connectors->make($device->connector);

            if (method_exists($driver, 'streamInfo')) {
                $stream = $driver->streamInfo($device->connector, $device);

                // 运行时通过 ?code= 传入验证码（不保存，仅本次播放）
                if ($request->filled('code')) {
                    $code = $request->query('code');
                    // 重建为 ezopen://{code}@ 协议格式（官方 iframe 播放器不支持 ?code= 查询参数）
                    $stream['url'] = 'ezopen://'.rawurlencode($code)
                        .'@open.ys7.com/'.$stream['serial'].'/'.$stream['channel'].'.live';
                    $stream['encrypted'] = true;
                }
            } else {
                $error = '该设备不支持视频播放';
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return view('dashboard.play', [
            'device' => $device,
            'stream' => $stream,
            'error' => $error,
        ]);
    }

    /**
     * 摄像头流信息（EZUIKit 播放参数，支持加密设备输入验证码）
     */
    public function stream(int $id, ConnectorManager $connectors)
    {
        $device = Device::findOrFail($id);

        if (! $device->connector) {
            abort(404, '设备未绑定连接器');
        }

        try {
            $driver = $connectors->make($device->connector);

            if (method_exists($driver, 'streamInfo')) {
                return response()->json([
                    'ok' => true,
                    'type' => 'ezopen',
                    'name' => $device->name,
                ] + $driver->streamInfo($device->connector, $device));
            }

            // 回退：HLS 直链
            if (method_exists($driver, 'liveUrl')) {
                return response()->json([
                    'ok' => true,
                    'type' => 'hls',
                    'name' => $device->name,
                    'url' => $driver->liveUrl($device->connector, $device),
                ]);
            }
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 503);
        }

        abort(404, '该设备不支持视频');
    }

    /**
     * 解析当前大屏配置：?screen=id 优先，其次默认大屏
     */
    protected function resolveScreen(Request $request): DashboardScreen
    {
        if ($id = $request->query('screen')) {
            return DashboardScreen::find($id) ?? DashboardScreen::default();
        }

        return DashboardScreen::default();
    }

    /**
     * 获取园区及其所有子区域 ID
     */
    protected function getSpaceIds(?int $parkId): ?array
    {
        if (! $parkId) {
            return null;
        }

        $ids = [$parkId];
        $children = Space::where('parent_id', $parkId)->pluck('id')->all();
        $ids = array_merge($ids, $children);

        if ($children) {
            $grandchildren = Space::whereIn('parent_id', $children)->pluck('id')->all();
            $ids = array_merge($ids, $grandchildren);
        }

        return $ids;
    }
}
