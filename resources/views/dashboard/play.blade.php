<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $device->name }} - 视频播放</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body { width: 100%; height: 100%; background: #000; overflow: hidden; font-family: -apple-system, "PingFang SC", sans-serif; }
        #player-container { width: 100%; height: 100%; }
        .overlay {
            position: absolute; inset: 0; display: flex; flex-direction: column;
            align-items: center; justify-content: center; gap: 12px; color: #cbd5e1;
            background: #000; z-index: 10; padding: 20px; text-align: center;
        }
        .overlay .msg { font-size: 13px; }
        .overlay .err { color: #f87171; font-size: 13px; max-width: 90%; word-break: break-all; }
        .overlay .detail { color: #64748b; font-size: 12px; max-width: 90%; }
        .code-bar {
            position: absolute; left: 8px; right: 8px; bottom: 8px; z-index: 20;
            display: none; gap: 6px;
        }
        .code-bar.show { display: flex; }
        .code-bar input {
            flex: 1; background: rgba(0,0,0,0.65); border: 1px solid rgba(56,189,248,0.4);
            color: #fff; border-radius: 4px; padding: 6px 10px; font-size: 13px;
        }
        .code-bar button {
            background: #0891b2; color: #fff; border: none; border-radius: 4px;
            padding: 6px 14px; font-size: 13px; cursor: pointer;
        }
        .hint { position: absolute; top: 8px; left: 10px; z-index: 20; color: rgba(255,255,255,0.75); font-size: 12px; text-shadow: 0 1px 2px #000; }
    </style>
</head>
<body>
@if ($error)
    <div class="overlay">
        <div class="err">⚠ {{ $error }}</div>
    </div>
@elseif ($stream)
    <div class="hint">{{ $device->name }}</div>
    <div id="player-container"></div>

    @if ($stream['encrypted'])
        <div class="code-bar {{ preg_match('#^ezopen://[^/@]+@#', $stream['url']) ? '' : 'show' }}" id="codeBar">
            <input type="text" id="codeInput" placeholder="输入设备视频加密验证码（安装时设置，任意字符）" autocomplete="off">
            <button onclick="submitCode()">确定</button>
        </div>
    @endif

    <div class="overlay" id="loading">
        <div class="msg">视频加载中…</div>
    </div>

    <script>
        var player = null;

        // 监听 iframe postMessage（EZUIKit iframe 通过 postMessage 通信）
        window.addEventListener('message', function (event) {
            var data = event.data;
            if (typeof data === 'string') {
                try { data = JSON.parse(data); } catch (e) { return; }
            }
            if (!data || typeof data !== 'object') return;

            console.log('[EZUIKit message]', data);

            // 播放成功（iframe 发送 type: 'handleSuccess'）
            if (data.type === 'handleSuccess' || data.code === 0 || data.code === '0') {
                hideLoading();
                return;
            }

            // 错误（iframe 发送 type: 'handleError'）
            if (data.type === 'handleError' || (data.code && data.code !== 0 && data.code !== '0')) {
                showError(getErrorMessage(data));
            }
        });

        function getErrorMessage(data) {
            var code = data.code || data.errorCode || '';
            var msg = data.msg || data.message || data.desc || '';

            // 萤石常见错误码
            var codeMap = {
                'HCUCSID-1': '设备验证码错误，请检查视频加密验证码',
                'HCUCSID-2': '设备验证码错误，请检查视频加密验证码',
                '1000': '设备未上线或验证码错误',
                '10001': '设备序列号不存在',
                '10002': 'AccessToken 过期',
                '10029': '设备视频加密已开启，请输入验证码',
                '10030': '设备视频加密已开启，请输入验证码',
                '60018': '设备不在线',
                '60019': '设备不在线',
                '60020': '设备视频加密已开启，请输入验证码',
                '60022': '设备视频加密已开启，请输入验证码',
                '9048': '免费版并发数达到上限（同一设备免费版仅支持1路预览），请关闭其他播放窗口/大屏后重试',
            };

            if (codeMap[code]) return codeMap[code] + '（错误码 ' + code + '）';
            if (msg) return msg + '（错误码 ' + code + '）';
            if (code) return '视频连接失败（错误码 ' + code + '）';
            return '视频连接失败，若为加密设备请输入验证码';
        }

        function hideLoading() {
            var l = document.getElementById('loading');
            if (l) l.style.display = 'none';
        }

        function showError(msg) {
            var l = document.getElementById('loading');
            if (!l) {
                l = document.createElement('div');
                l.className = 'overlay';
                l.id = 'loading';
                document.body.appendChild(l);
            }
            l.innerHTML = '<div class="err">⚠ ' + msg + '</div>';
            var bar = document.getElementById('codeBar');
            if (bar) bar.classList.add('show');
        }

        function startPlayer(url) {
            try {
                // 不用 SDK 的 iframe 构建，直接自己构建 iframe URL（对 ezopen URL 做 encodeURIComponent）。
                // 注意：
                // 1. 不能传 decoderVersion —— 官方 CDN 上 /js/versions/N/ 解码器路径返回
                //    {"retcode":1000,"msg":"请登录后重试"} JSON，会导致 JSPlugin 未定义，
                //    播放器永远卡在"初始化播放器..."。
                // 2. 加密设备验证码必须使用 ezopen://{code}@ 协议格式（见后端），
                //    ?code= 查询参数格式会被判定为 "ezopen协议格式有误"。
                var token = @js($stream['token']);
                var iframeUrl = 'https://open.ys7.com/ezopen/h5/iframe'
                    + '?bSupporDoubleClickFull=0'
                    + '&url=' + encodeURIComponent(url)
                    + '&autoplay=1'
                    + '&audio=1'
                    + '&accessToken=' + token
                    + '&templete=0';

                var container = document.getElementById('player-container');
                container.innerHTML = '';
                var iframe = document.createElement('iframe');
                iframe.src = iframeUrl;
                iframe.style.cssText = 'width:100%;height:100%;border:0;display:block;';
                iframe.setAttribute('allowfullscreen', '');
                iframe.setAttribute('allow', 'autoplay; fullscreen; microphone; camera');

                // iframe 加载完成 → 隐藏 loading（iframe 内 EZUIKit 播放器会自行处理加载/错误态）
                // 0.7.2 iframe 不会 postMessage handleSuccess，靠 onload 兜底移除遮罩
                iframe.onload = function () {
                    setTimeout(function () {
                        var l = document.getElementById('loading');
                        if (l) l.style.display = 'none';
                    }, 1500);
                };

                container.appendChild(iframe);

                // 仅作提示：25 秒后仍未收到任何事件，显示验证码输入栏（不覆盖 loading 文案）
                setTimeout(function () {
                    var bar = document.getElementById('codeBar');
                    if (bar) bar.classList.add('show');
                }, 25000);
            } catch (e) {
                showError(e.message || '播放器初始化失败');
            }
        }

        function submitCode() {
            var code = document.getElementById('codeInput').value.trim();
            if (!code) return;
            var base = location.pathname;
            location.href = base + '?code=' + encodeURIComponent(code);
        }

        document.getElementById('codeInput')?.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') submitCode();
        });

        function boot() {
            startPlayer(@js($stream['url']));
        }

        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            setTimeout(boot, 200);
        } else {
            window.addEventListener('DOMContentLoaded', function () { setTimeout(boot, 200); });
        }
    </script>
@endif
</body>
</html>
