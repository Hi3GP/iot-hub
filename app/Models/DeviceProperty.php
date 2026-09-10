<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceProperty extends Model
{
    protected $fillable = ['device_id', 'code', 'value', 'reported_at'];

    protected $casts = ['reported_at' => 'datetime'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
