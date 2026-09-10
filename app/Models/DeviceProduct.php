<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeviceProduct extends Model
{
    use SoftDeletes;
    protected $fillable = ['connector_id', 'external_key', 'name', 'category', 'model', 'thing_model'];

    protected $casts = ['thing_model' => 'array'];

    public function connector(): BelongsTo
    {
        return $this->belongsTo(Connector::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }
}
