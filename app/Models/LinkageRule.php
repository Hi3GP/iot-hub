<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LinkageRule extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'name', 'description', 'enabled', 'triggers', 'conditions',
        'actions', 'cooldown_seconds', 'last_triggered_at', 'user_id',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'triggers' => 'array',
        'conditions' => 'array',
        'actions' => 'array',
        'last_triggered_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LinkageLog::class);
    }
}
