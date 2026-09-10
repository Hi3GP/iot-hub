<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DashboardScreen extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'space_id', 'layout', 'carousel_enabled', 'carousel_interval',
        'refresh_interval', 'show_stats', 'show_categories', 'show_cameras',
        'show_alerts', 'show_widgets', 'camera_device_ids', 'widgets', 'is_default', 'user_id',
    ];

    protected $casts = [
        'layout' => 'integer',
        'carousel_enabled' => 'boolean',
        'carousel_interval' => 'integer',
        'refresh_interval' => 'integer',
        'show_stats' => 'boolean',
        'show_categories' => 'boolean',
        'show_cameras' => 'boolean',
        'show_alerts' => 'boolean',
        'show_widgets' => 'boolean',
        'camera_device_ids' => 'array',
        'widgets' => 'array',
        'is_default' => 'boolean',
    ];

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    protected static function booted(): void
    {
        static::saved(function (DashboardScreen $screen): void {
            if ($screen->is_default) {
                static::where('id', '!=', $screen->id)->update(['is_default' => false]);
            }
        });
    }

    /**
     * 获取默认大屏（无则返回未保存的默认配置）
     */
    public static function default(): self
    {
        return static::where('is_default', true)
            ->orderBy('id')
            ->first()
            ?? new static([
                'name' => '默认大屏',
                'layout' => 4,
                'carousel_enabled' => true,
                'carousel_interval' => 10,
                'refresh_interval' => 30,
                'show_stats' => true,
                'show_categories' => true,
                'show_cameras' => true,
                'show_alerts' => true,
                'show_widgets' => true,
                'camera_device_ids' => null,
                'widgets' => [],
            ]);
    }
}
