<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

class Device extends Model
{
    use SoftDeletes;

    /**
     * 需要合并 WIFI/4G 双通道的天翼云产品（第一批门禁裸板）。
     * 这些产品在天翼云侧同一物理设备被拆成 -WIFI / -4G 两个设备，
     * 本平台合并为一个逻辑设备展示与操作，仅对下列产品 ID 生效。
     */
    public const MERGED_CHANNEL_PRODUCT_IDS = ['16714420'];

    protected $fillable = [
        'connector_id', 'device_product_id', 'space_id', 'user_id', 'name',
        'external_id', 'external_parent_id', 'model', 'category',
        'status', 'status_updated_at', 'bound_type', 'is_active', 'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'is_active' => 'boolean',
        'status_updated_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(DeviceProduct::class, 'device_product_id');
    }

    public function space(): BelongsTo
    {
        return $this->belongsTo(Space::class);
    }

    public function properties(): HasMany
    {
        return $this->hasMany(DeviceProperty::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(DeviceEvent::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DeviceLog::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(Alert::class);
    }

    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('status', 'online');
    }

    public function scopeOffline(Builder $query): Builder
    {
        return $query->where('status', 'offline');
    }

    /**
     * 可用于直播/大屏的摄像头设备：
     * 摄像头品类 + 排除已存在下挂通道的 NVR 主设备（通道本身仍可直播）
     */
    public function scopeViewableCameras(Builder $query): Builder
    {
        return $query
            ->where(function ($q) {
                $q->where('category', 'camera')
                    ->orWhereHas('connector', fn ($c) => $c->where('driver', 'ezviz'));
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('devices as d2')
                    ->whereColumn('d2.external_parent_id', 'devices.external_id')
                    ->whereColumn('d2.connector_id', 'devices.connector_id')
                    ->whereNull('d2.deleted_at');
            });
    }

    public function isOnline(): bool
    {
        return $this->status === 'online';
    }

    public function property(string $code): mixed
    {
        return $this->properties->firstWhere('code', $code)?->value;
    }

    /* ===================== WIFI/4G 双通道合并（仅 MERGED_CHANNEL_PRODUCT_IDS） ===================== */

    /** 天翼云侧产品 ID（meta.product_id） */
    public function ctwingProductId(): ?string
    {
        $pid = $this->meta['product_id'] ?? null;

        return $pid !== null ? (string) $pid : null;
    }

    /** 是否为需要合并 WIFI/4G 双通道的产品设备 */
    public function isMergedChannelProduct(): bool
    {
        return $this->connector?->driver === 'ctwing'
            && in_array($this->ctwingProductId(), self::MERGED_CHANNEL_PRODUCT_IDS, true);
    }

    /** 通道网络类型：WIFI / 4G / null */
    public function channelNetwork(): ?string
    {
        if (preg_match('/-(WIFI|4G)$/i', (string) $this->external_id, $m)) {
            return strtoupper($m[1]);
        }

        return null;
    }

    /** 去掉 -WIFI/-4G 后缀后的合并基础 ID */
    public function channelBaseId(): ?string
    {
        if (preg_match('/^(.+)-(WIFI|4G)$/i', (string) $this->external_id, $m)) {
            return $m[1];
        }

        return null;
    }

    /**
     * 同一物理设备的全部通道（含自己），WIFI 排在前面。
     * 非合并产品只返回自身。
     *
     * @return \Illuminate\Support\Collection<int, Device>
     */
    public function channelSiblings()
    {
        if (! $this->isMergedChannelProduct() || ! $base = $this->channelBaseId()) {
            return collect([$this]);
        }

        return static::query()
            ->where('connector_id', $this->connector_id)
            ->where(function ($q) use ($base) {
                $q->where('external_id', $base.'-WIFI')
                    ->orWhere('external_id', $base.'-4G');
            })
            ->get()
            ->sortByDesc(fn (Device $d) => $d->channelNetwork() === 'WIFI' ? 1 : 0)
            ->values();
    }

    /**
     * 主通道设备：优先 WIFI，WIFI 不存在时为 4G；非合并产品返回自身。
     * 列表展示、属性/事件/日志归属、二维码与联动目标都使用主通道。
     */
    public function primaryChannel(): Device
    {
        $siblings = $this->channelSiblings();

        return $siblings->first(fn (Device $d) => $d->channelNetwork() === 'WIFI')
            ?? $siblings->first()
            ?? $this;
    }

    /** 是否为被合并隐藏的副通道（4G 且存在同基础 ID 的 WIFI 设备） */
    public function isSecondaryChannel(): bool
    {
        if (! $this->isMergedChannelProduct() || $this->channelNetwork() !== '4G') {
            return false;
        }

        return $this->channelSiblings()->contains(
            fn (Device $d) => $d->channelNetwork() === 'WIFI' && $d->id !== $this->id
        );
    }

    /** 合并设备：任一通道在线即视为在线 */
    public function anyChannelOnline(): bool
    {
        return $this->channelSiblings()->contains(fn (Device $d) => $d->status === 'online');
    }

    /**
     * 各通道在线状态（供列表/详情展示监测）
     *
     * @return array<int, array{network: ?string, online: bool, status_updated_at: mixed}>
     */
    public function channelStatuses(): array
    {
        if (! $this->isMergedChannelProduct()) {
            return [];
        }

        return $this->channelSiblings()
            ->map(fn (Device $d) => [
                'network' => $d->channelNetwork(),
                'online' => $d->status === 'online',
                'status_updated_at' => $d->status_updated_at,
            ])
            ->all();
    }

    /**
     * 列表查询：隐藏合并产品下被 WIFI 通道覆盖的 4G 副通道。
     * MySQL 5.7 兼容（LEFT/CHAR_LENGTH/CONCAT + JSON_EXTRACT）。
     */
    public function scopeWithoutSecondaryChannels(Builder $query): Builder
    {
        return $query->whereNotExists(function ($sub) {
            $sub->select(DB::raw(1))
                ->from('devices as d2')
                // JOIN 只关联内层表；外层 devices 的关联放在 whereColumn（避免在 ON 中引用外层表）
                ->join('connectors as c2', 'c2.id', '=', 'd2.connector_id')
                ->whereColumn('d2.connector_id', 'devices.connector_id')
                ->whereNull('d2.deleted_at')
                ->where('c2.driver', 'ctwing')
                ->whereRaw("devices.external_id LIKE '%-4G'");

            // 产品 ID 校验 + 兄弟 WIFI 设备匹配（MySQL 5.7 与 SQLite 函数差异做兼容）
            if (DB::connection()->getDriverName() === 'sqlite') {
                $sub->whereRaw("json_extract(devices.meta, '$.product_id') = ?", [self::MERGED_CHANNEL_PRODUCT_IDS[0]]);
                $sub->whereRaw("d2.external_id = substr(devices.external_id, 1, length(devices.external_id) - 3) || '-WIFI'");
            } else {
                $sub->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(devices.meta, '$.product_id')) = ?", [self::MERGED_CHANNEL_PRODUCT_IDS[0]]);
                $sub->whereRaw('d2.external_id = CONCAT(LEFT(devices.external_id, CHAR_LENGTH(devices.external_id) - 3), "-WIFI")');
            }
        });
    }
}
