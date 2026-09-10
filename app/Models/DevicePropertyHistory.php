<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DevicePropertyHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['device_id', 'code', 'value', 'reported_at'];

    protected $casts = ['reported_at' => 'datetime'];

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }
}
