<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LinkageLog extends Model
{
    protected $fillable = ['linkage_rule_id', 'status', 'message', 'payload'];

    protected $casts = ['payload' => 'array'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(LinkageRule::class, 'linkage_rule_id');
    }
}
