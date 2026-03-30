<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StageHistory extends Model
{
    protected $table = 'stage_history';

    protected $fillable = [
        'stageable_type',
        'stageable_id',
        'pipeline_stage_id',
        'user_id',
        'entered_at',
        'left_at',
    ];

    protected $casts = [
        'entered_at' => 'datetime',
        'left_at'    => 'datetime',
    ];

    public function stageable(): MorphTo
    {
        return $this->morphTo();
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}