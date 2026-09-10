<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>数据大屏 - {{ $screen->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.14.1/dist/cdn.min.js"></script>
    <style>
        body { background: #0a0e1a; color: #e2e8f0; }
        .panel { background: linear-gradient(135deg, rgba(30,41,59,0.8), rgba(15,23,42,0.9)); border: 1px solid rgba(56,189,248,0.2); border-radius: 12px; }
        .stat-value { font-size: 2.5rem; font-weight: 700; line-height: 1; }
        .glow-blue { box-shadow: 0 0 20px rgba(56,189,248,0.3); }
        .glow-green { box-shadow: 0 0 20px rgba(34,197,94,0.3); }
        .glow-red { box-shadow: 0 0 20px rgba(239,68,68,0.3); }
        .glow-yellow { box-shadow: 0 0 20px rgba(234,179,8,0.3); }
        .camera-grid { display: grid; gap: 4px; }
        .camera-cell { background: #000; aspect-ratio: 16/9; position: relative; overflow: hidden; border-radius: 4px; }
        .camera-cell video { width: 100%; height: 100%; object-fit: cover; }
        .camera-label { position: absolute; bottom: 0; left: 0; right: 0; background: linear-gradient(transparent, rgba(0,0,0,0.8)); padding: 4px 8px; font-size: 12px; }
        .marquee { animation: scroll 20s linear infinite; }
        @keyframes scroll { 0% { transform: translateY(0); } 100% { transform: translateY(-50%); } }
        select.dark-select { background: #1e293b; border: 1px solid #334155; color: #e2e8f0; border-radius: 6px; padding: 6px 10px; font-size: 14px; }
    </style>
</head>
<body>
<div x-data="dashboard(@js([
    'screen_id' => $screen->id,
    'layout' => $screen->layout,
    'carousel_enabled' => $screen->carousel_enabled,
    'carousel_interval' => $screen->carousel_interval,
    'refresh_interval' => $screen->refresh_interval,
    'show_stats' => $screen->show_stats,
    'show_categories' => $screen->show_categories,
    'show_cameras' => $screen->show_cameras,
    'show_alerts' => $screen->show_alerts,
    'show_widgets' => $screen->show_widgets,
]))" x-init="init()" class="min-h-screen p-4">
    <!-- 顶部 -->
    <div class="flex items-center justify-between mb-4">
        <div>
            <h1 class="text-2xl font-bold text-cyan-400">物联网数据大屏</h1>
            <span class="text-sm text-slate-500">{{ $screen->name }}<template x-if="screenName"> · <span x-text="screenName"></span></template></span>
        </div>
        <div class="flex items-center gap-4">
            <select class="dark-select" onchange="window.location.href=this.value">
                <option value="/dashboard" {{ !$screen->id ? 'selected' : '' }}>默认大屏</option>
                @foreach($screens as $s)
                    <option value="/dashboard?screen={{ $s->id }}" {{ $screen->id === $s->id ? 'selected' : '' }}>
                        {{ $s->name }}{{ $s->is_default ? ' ★' : '' }}
                    </option>
                @endforeach
            </select>
            <a href="{{ admin_url() }}" class="text-sm text-slate-400 hover:text-cyan-400">返回后台</a>
            <span x-text="currentTime" class="text-sm text-slate-400"></span>
        </div>
    </div>

    <!-- 统计卡片 -->
    <template x-if="config.show_stats">
        <div class="grid grid-cols-5 gap-4 mb-4">
            <div class="panel p-4 text-center glow-blue">
                <div class="text-slate-400 text-sm mb-1">设备总数</div>
                <div class="stat-value text-cyan-400" x-text="stats.total">0</div>
            </div>
            <div class="panel p-4 text-center glow-green">
                <div class="text-slate-400 text-sm mb-1">在线设备</div>
                <div class="stat-value text-green-400" x-text="stats.online">0</div>
            </div>
            <div class="panel p-4 text-center">
                <div class="text-slate-400 text-sm mb-1">离线设备</div>
                <div class="stat-value text-slate-400" x-text="stats.offline">0</div>
            </div>
            <div class="panel p-4 text-center glow-yellow">
                <div class="text-slate-400 text-sm mb-1">未知状态</div>
                <div class="stat-value text-yellow-400" x-text="stats.unknown">0</div>
            </div>
            <div class="panel p-4 text-center glow-red">
                <div class="text-slate-400 text-sm mb-1">异常设备</div>
                <div class="stat-value text-red-400" x-text="stats.abnormal">0</div>
            </div>
        </div>
    </template>

    <!-- 自定义数据卡片 -->
    <template x-if="config.show_widgets && widgets.length > 0">
        <div class="grid gap-4 mb-4" :style="widgetGridStyle">
            <template x-for="(w, idx) in widgets" :key="idx">
                <div class="panel p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-slate-400 text-sm truncate" x-text="w.title"></span>
                        <span class="inline-block w-2 h-2 rounded-full shrink-0"
                              :class="w.status === 'online' ? 'bg-green-400' : 'bg-slate-500'"
                              :title="w.status === 'online' ? '设备在线' : '设备离线'"></span>
                    </div>
                    <div class="flex items-baseline gap-1">
                        <span class="text-3xl font-bold text-cyan-300 break-all" x-text="w.value"></span>
                        <span class="text-sm text-slate-500" x-text="w.unit"></span>
                    </div>
                    <div class="flex items-center justify-between mt-2 text-xs text-slate-500">
                        <span class="truncate" x-text="w.device_name"></span>
                        <span x-text="formatRelative(w.reported_at)"></span>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <div class="grid grid-cols-12 gap-4">
        <!-- 左侧：设备分类 -->
        <template x-if="config.show_categories && !config.show_cameras">
            <div class="col-span-12 panel p-4">
                <h3 class="text-lg font-semibold text-cyan-400 mb-3">设备分类</h3>
                <div class="grid grid-cols-6 gap-4">
                    <template x-for="(count, cat) in categories" :key="cat">
                        <div class="flex items-center justify-between bg-slate-800/50 px-4 py-3 rounded">
                            <span class="text-slate-300" x-text="cat || '未分类'"></span>
                            <span class="text-cyan-400 font-semibold text-xl" x-text="count"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <template x-if="config.show_categories && config.show_cameras">
            <div class="col-span-3 panel p-4">
                <h3 class="text-lg font-semibold text-cyan-400 mb-3">设备分类</h3>
                <div class="space-y-2">
                    <template x-for="(count, cat) in categories" :key="cat">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-300" x-text="cat || '未分类'"></span>
                            <span class="text-cyan-400 font-semibold" x-text="count"></span>
                        </div>
                    </template>
                </div>
            </div>
        </template>

        <!-- 中间：监控画面 -->
        <template x-if="config.show_cameras">
            <div class="panel p-4" :class="config.show_categories || config.show_alerts ? 'col-span-6' : 'col-span-12'">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-lg font-semibold text-cyan-400">实时监控</h3>
                    <div class="flex gap-2 items-center">
                        <template x-for="n in [4,8,16]" :key="n">
                            <button @click="layout=n; carouselIndex=0" :class="layout===n?'bg-cyan-600':'bg-slate-700'" class="px-3 py-1 rounded text-xs" x-text="n+'画面'"></button>
                        </template>
                        <template x-if="config.carousel_enabled">
                            <button @click="toggleCarousel()" :class="carouselRunning?'bg-red-600':'bg-slate-700'" class="px-3 py-1 rounded text-xs" x-text="carouselRunning?'停止轮播':'开始轮播'"></button>
                        </template>
                    </div>
                </div>
                <div class="camera-grid" :style="gridStyle">
                    <template x-for="cam in visibleCameras" :key="cam.id">
                        <div class="camera-cell">
                            <iframe :src="cam.playUrl" frameborder="0" allow="autoplay; fullscreen"
                                    class="w-full h-full border-0"></iframe>
                            <div class="camera-label" x-text="cam.name"></div>
                        </div>
                    </template>
                    <template x-for="i in emptySlots" :key="'empty'+i">
                        <div class="camera-cell flex items-center justify-center text-slate-600 text-xs">无信号</div>
                    </template>
                </div>
                <div class="text-xs text-slate-500 mt-2" x-text="cameras.length + ' 路在线摄像头，' + (config.carousel_interval || 10) + ' 秒轮播'"></div>
            </div>
        </template>

        <!-- 右侧：告警列表 -->
        <template x-if="config.show_alerts && config.show_cameras">
            <div class="col-span-3 panel p-4">
                <h3 class="text-lg font-semibold text-cyan-400 mb-3">最近告警</h3>
                <div class="space-y-2 max-h-96 overflow-hidden">
                    <div class="marquee">
                        <template x-for="alert in alerts" :key="alert.id">
                            <div class="p-2 rounded" :class="alert.level==='critical'?'bg-red-900/30 border border-red-700':'bg-slate-800/50'">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold" :class="alert.level==='critical'?'text-red-400':'text-yellow-400'" x-text="alert.title"></span>
                                    <span class="text-xs text-slate-500" x-text="formatTime(alert.triggered_at)"></span>
                                </div>
                                <div class="text-xs text-slate-400 mt-1" x-text="alert.content"></div>
                            </div>
                        </template>
                        <template x-for="alert in alerts" :key="'dup'+alert.id">
                            <div class="p-2 rounded mt-2" :class="alert.level==='critical'?'bg-red-900/30 border border-red-700':'bg-slate-800/50'">
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-semibold" :class="alert.level==='critical'?'text-red-400':'text-yellow-400'" x-text="alert.title"></span>
                                    <span class="text-xs text-slate-500" x-text="formatTime(alert.triggered_at)"></span>
                                </div>
                                <div class="text-xs text-slate-400 mt-1" x-text="alert.content"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <!-- 无摄像头但有告警：全屏告警 -->
        <template x-if="config.show_alerts && !config.show_cameras">
            <div class="col-span-12 panel p-4">
                <h3 class="text-lg font-semibold text-cyan-400 mb-3">最近告警</h3>
                <div class="grid grid-cols-2 gap-3">
                    <template x-for="alert in alerts" :key="alert.id">
                        <div class="p-3 rounded" :class="alert.level==='critical'?'bg-red-900/30 border border-red-700':'bg-slate-800/50'">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold" :class="alert.level==='critical'?'text-red-400':'text-yellow-400'" x-text="alert.title"></span>
                                <span class="text-xs text-slate-500" x-text="formatTime(alert.triggered_at)"></span>
                            </div>
                            <div class="text-sm text-slate-400 mt-1" x-text="alert.content"></div>
                        </div>
                    </template>
                </div>
            </div>
        </template>
    </div>
</div>

<script>
function dashboard(initial) {
    return {
        config: initial,
        screenName: '',
        stats: { total: 0, online: 0, offline: 0, unknown: 0, abnormal: 0 },
        categories: {},
        alerts: [],
        widgets: [],
        cameras: [],
        layout: initial.layout || 4,
        carouselRunning: initial.carousel_enabled,
        carouselIndex: 0,
        carouselTimer: null,
        refreshTimer: null,
        currentTime: '',

        init() {
            this.loadData();
            this.updateTime();
            setInterval(() => this.updateTime(), 1000);
        },

        updateTime() {
            this.currentTime = new Date().toLocaleString('zh-CN');
        },

        async loadData() {
            try {
                const screenParam = this.config.screen_id ? '?screen=' + this.config.screen_id : '';
                const res = await fetch('/dashboard/data' + screenParam);
                const data = await res.json();
                this.config = { ...this.config, ...data.config };
                this.layout = this.config.layout;
                this.stats = data.stats;
                this.categories = data.categories;
                this.alerts = data.alerts;
                this.widgets = data.widgets || [];
                this.cameras = data.cameras;

                this.restartTimers();
            } catch (e) { console.error(e); }
        },

        restartTimers() {
            clearInterval(this.refreshTimer);
            clearInterval(this.carouselTimer);
            this.carouselIndex = 0;

            const refreshMs = (this.config.refresh_interval || 30) * 1000;
            this.refreshTimer = setInterval(() => this.loadData(), refreshMs);

            if (this.config.carousel_enabled && this.cameras.length > this.layout) {
                const intervalMs = (this.config.carousel_interval || 10) * 1000;
                this.carouselRunning = true;
                this.carouselTimer = setInterval(() => {
                    this.carouselIndex = (this.carouselIndex + 1) % Math.ceil(this.cameras.length / this.layout);
                }, intervalMs);
            } else {
                this.carouselRunning = false;
            }
        },

        get gridStyle() {
            const cols = this.layout <= 4 ? 2 : 4;
            return 'grid-template-columns: repeat(' + cols + ', 1fr);';
        },

        get widgetGridStyle() {
            const n = this.widgets.length;
            const cols = n >= 8 ? 4 : (n >= 4 ? 4 : n);
            return 'grid-template-columns: repeat(' + Math.max(1, cols) + ', minmax(0, 1fr));';
        },

        get visibleCameras() {
            if (this.carouselRunning && this.cameras.length > this.layout) {
                const start = this.carouselIndex * this.layout;
                const visible = [];
                for (let i = 0; i < this.layout; i++) {
                    const idx = (start + i) % this.cameras.length;
                    visible.push(this.cameras[idx]);
                }
                return visible;
            }
            return this.cameras.slice(0, this.layout);
        },

        get emptySlots() {
            return Math.max(0, this.layout - this.visibleCameras.length);
        },

        toggleCarousel() {
            this.carouselRunning = !this.carouselRunning;
            if (this.carouselRunning) {
                const intervalMs = (this.config.carousel_interval || 10) * 1000;
                this.carouselTimer = setInterval(() => {
                    this.carouselIndex = (this.carouselIndex + 1) % Math.ceil(this.cameras.length / this.layout);
                }, intervalMs);
            } else {
                clearInterval(this.carouselTimer);
                this.carouselIndex = 0;
            }
        },

        formatTime(t) {
            if (!t) return '';
            return new Date(t).toLocaleString('zh-CN', { month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' });
        },

        formatRelative(t) {
            if (!t) return '无数据';
            const diff = (Date.now() - new Date(t).getTime()) / 1000;
            if (diff < 60) return Math.max(0, Math.round(diff)) + ' 秒前';
            if (diff < 3600) return Math.round(diff / 60) + ' 分钟前';
            if (diff < 86400) return Math.round(diff / 3600) + ' 小时前';
            return Math.round(diff / 86400) + ' 天前';
        }
    }
}
</script>
</body>
</html>
