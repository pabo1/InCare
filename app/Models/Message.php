<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Message extends Model
{
    protected $fillable = [
        'messageable_type',
        'messageable_id',
        'user_id',
        'channel',
        'direction',
        'body',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    const CHANNEL_TELEGRAM  = 'telegram';
    const CHANNEL_INSTAGRAM = 'instagram';
    const CHANNEL_SIPUNI    = 'sipuni';
    const CHANNEL_INTERNAL  = 'internal';

    const DIRECTION_IN  = 'in';
    const DIRECTION_OUT = 'out';

    public function messageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
