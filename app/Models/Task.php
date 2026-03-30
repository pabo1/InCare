<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Task extends Model
{
    protected $fillable = [
        'user_id',
        'taskable_type',
        'taskable_id',
        'title',
        'description',
        'type',
        'status',
        'due_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    const TYPE_CALL         = 'call';
    const TYPE_REACTIVATION = 'reactivation';
    const TYPE_REMIND       = 'remind';

    const STATUS_PENDING   = 'pending';
    const STATUS_DONE      = 'done';
    const STATUS_CANCELLED = 'cancelled';

    public function taskable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
