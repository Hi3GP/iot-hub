<?php

use Illuminate\Support\Facades\Schedule;

// 定时同步各平台设备（每 15 分钟）
Schedule::command('iot:sync')->everyFifteenMinutes();

// 定时轮询设备状态（每 5 分钟；有推送能力的平台 webhook 实时更新）
Schedule::command('iot:refresh-status')->everyFiveMinutes();
