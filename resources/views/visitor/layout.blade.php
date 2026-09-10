<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') - {{ \App\Models\SystemSetting::get('platform_name', '物联网中台') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", "PingFang SC", "Hiragino Sans GB", "Microsoft YaHei", sans-serif;
            background: #f1f5f9; color: #0f172a; min-height: 100vh;
        }
        .page { max-width: 520px; margin: 0 auto; min-height: 100vh; background: #f1f5f9; }
        .header {
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            color: #fff; padding: 28px 24px 36px;
        }
        .header .brand { font-size: 13px; opacity: .85; margin-bottom: 10px; }
        .header h1 { font-size: 22px; font-weight: 700; }
        .header p { font-size: 14px; opacity: .9; margin-top: 6px; }
        .card {
            background: #fff; border-radius: 16px; margin: -20px 16px 16px;
            padding: 24px 20px; box-shadow: 0 8px 30px -12px rgba(15, 23, 42, .15);
        }
        label { display: block; font-size: 14px; font-weight: 600; color: #334155; margin: 16px 0 8px; }
        label .req { color: #ef4444; margin-left: 2px; }
        input, textarea, select {
            width: 100%; border: 1.5px solid #e2e8f0; border-radius: 10px;
            padding: 13px 14px; font-size: 16px; color: #0f172a; background: #f8fafc;
            outline: none; transition: border-color .15s;
        }
        input:focus, textarea:focus { border-color: #3b82f6; background: #fff; }
        textarea { resize: none; }
        .btn {
            width: 100%; margin-top: 28px; border: none; border-radius: 12px;
            padding: 15px; font-size: 17px; font-weight: 700; color: #fff; cursor: pointer;
            background: linear-gradient(135deg, #2563eb, #3b82f6);
            box-shadow: 0 8px 20px -8px rgba(37, 99, 235, .6);
        }
        .btn:active { transform: scale(.98); }
        .errors { background: #fef2f2; border: 1px solid #fecaca; color: #b91c1c;
            border-radius: 10px; padding: 12px 14px; font-size: 14px; margin-bottom: 4px; }
        .errors ul { list-style: none; } .errors li { margin: 4px 0; }
        .tip { text-align: center; font-size: 12px; color: #94a3b8; margin: 18px 0 8px; }
        .result-icon { width: 76px; height: 76px; border-radius: 50%; margin: 0 auto 16px;
            display: flex; align-items: center; justify-content: center; font-size: 40px; }
        .result-icon.ok { background: #dcfce7; color: #16a34a; }
        .result-icon.fail { background: #fee2e2; color: #dc2626; }
        .result-title { text-align: center; font-size: 20px; font-weight: 700; }
        .result-msg { text-align: center; font-size: 14px; color: #64748b; margin-top: 10px; line-height: 1.7; }
        .info-row { display: flex; justify-content: space-between; padding: 12px 0;
            border-bottom: 1px solid #f1f5f9; font-size: 14px; }
        .info-row span:first-child { color: #94a3b8; }
        .info-row span:last-child { color: #0f172a; font-weight: 500; max-width: 60%; text-align: right; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 12px; }
        .badge.ok { background: #dcfce7; color: #16a34a; }
        .badge.fail { background: #fee2e2; color: #dc2626; }
    </style>
</head>
<body>
<div class="page">
    @yield('content')
    <p class="tip">{{ \App\Models\SystemSetting::get('footer_title', '物联网中台 · IoT Hub') }}</p>
</div>
</body>
</html>
