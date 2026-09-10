<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceLog extends Model
{
    protected $fillable = [
        'device_id',
        'connector_id',
        'direction',
        'type',
        'title',
        'content',
        'result',
        'operator_id',
        'operator_type',
        'ip',
        'occurred_at',
    ];

    protected $casts = [
        'content' => 'array',
        'occurred_at' => 'datetime',
    ];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_id');
    }

    /* ============ 日志类型常量 ============ */

    public const DIRECTION_IN = 'in';
    public const DIRECTION_OUT = 'out';
    public const DIRECTION_SYSTEM = 'system';

    public const TYPE_COMMAND = 'command';
    public const TYPE_DATA = 'data';
    public const TYPE_STATUS = 'status';
    public const TYPE_ALERT = 'alert';
    public const TYPE_EVENT = 'event';
    public const TYPE_SYNC = 'sync';

    public const RESULT_SUCCESS = 'success';
    public const RESULT_FAILED = 'failed';
    public const RESULT_PENDING = 'pending';

    public static function directionLabels(): array
    {
        return [
            self::DIRECTION_IN => '上行（设备上报）',
            self::DIRECTION_OUT => '下行（平台下发）',
            self::DIRECTION_SYSTEM => '系统',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_COMMAND => '命令下发',
            self::TYPE_DATA => '数据上报',
            self::TYPE_STATUS => '状态变化',
            self::TYPE_ALERT => '告警',
            self::TYPE_EVENT => '事件',
            self::TYPE_SYNC => '设备同步',
        ];
    }

    public static function resultLabels(): array
    {
        return [
            self::RESULT_SUCCESS => '成功',
            self::RESULT_FAILED => '失败',
            self::RESULT_PENDING => '处理中',
        ];
    }
}
