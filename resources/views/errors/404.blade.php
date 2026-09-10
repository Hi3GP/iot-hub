<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>页面未找到 - IoT Hub</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html, body {
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Microsoft YaHei", sans-serif;
        }
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #0f172a 100%);
            color: #e2e8f0;
        }
        .container {
            text-align: center;
            max-width: 520px;
            padding: 40px 24px;
        }
        .code {
            font-size: 120px;
            font-weight: 800;
            line-height: 1;
            background: linear-gradient(180deg, #38bdf8 0%, #0ea5e9 50%, #0369a1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
            letter-spacing: -4px;
        }
        .title {
            font-size: 22px;
            font-weight: 600;
            color: #f1f5f9;
            margin-bottom: 8px;
        }
        .message {
            font-size: 14px;
            color: #94a3b8;
            margin-bottom: 8px;
            line-height: 1.6;
        }
        .redirect-hint {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 28px;
        }
        .redirect-hint .countdown {
            color: #38bdf8;
            font-weight: 600;
        }
        .actions {
            display: flex;
            gap: 12px;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            border: none;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0ea5e9, #0284c7);
            color: #fff;
            box-shadow: 0 2px 8px rgba(14, 165, 233, 0.3);
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #0284c7, #0369a1);
            box-shadow: 0 4px 16px rgba(14, 165, 233, 0.4);
            transform: translateY(-1px);
        }
        .btn-secondary {
            background: rgba(148, 163, 184, 0.15);
            color: #cbd5e1;
            border: 1px solid rgba(148, 163, 184, 0.3);
        }
        .btn-secondary:hover {
            background: rgba(148, 163, 184, 0.25);
            border-color: rgba(148, 163, 184, 0.5);
        }
        .progress {
            width: 100%;
            height: 3px;
            background: rgba(148, 163, 184, 0.15);
            border-radius: 2px;
            margin-top: 32px;
            overflow: hidden;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #38bdf8, #0ea5e9);
            border-radius: 2px;
            width: 100%;
            transition: width 5s linear;
        }
        .icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 20px;
            opacity: 0.8;
        }
        @media (max-width: 480px) {
            .code { font-size: 80px; }
            .container { padding: 24px 16px; }
        }
    </style>
</head>
<body>
    <div class="container">
        <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/>
            <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
            <line x1="12" y1="17" x2="12.01" y2="17"/>
        </svg>

        <div class="code">404</div>
        <div class="title">页面未找到</div>
        <p class="message">您访问的页面可能已被删除、移动或暂时不可用</p>
        <p class="redirect-hint"><span class="countdown" id="timer">5</span> 秒后自动返回首页</p>

        <div class="actions">
            <a href="{{ admin_url() }}" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                    <polyline points="9 22 9 12 15 12 15 22"/>
                </svg>
                返回首页
            </a>
            <button onclick="history.back()" class="btn btn-secondary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="19" y1="12" x2="5" y2="12"/>
                    <polyline points="12 19 5 12 12 5"/>
                </svg>
                返回上一页
            </button>
        </div>

        <div class="progress">
            <div class="progress-bar" id="progressBar"></div>
        </div>
    </div>

    <script>
        var seconds = 5;
        var timerEl = document.getElementById('timer');
        var progressBar = document.getElementById('progressBar');

        // 进度条动画
        setTimeout(function() {
            progressBar.style.width = '0%';
        }, 50);

        // 倒计时
        var interval = setInterval(function() {
            seconds--;
            if (timerEl) timerEl.textContent = seconds;
            if (seconds <= 0) {
                clearInterval(interval);
                window.location.href = '{{ admin_url() }}';
            }
        }, 1000);

        // 用户手动跳转时停止倒计时
        document.querySelectorAll('.btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                clearInterval(interval);
            });
        });
    </script>
</body>
</html>
