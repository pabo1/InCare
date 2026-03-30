<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Automation extends Model
{
    protected $fillable = [
        'pipeline_id',
        'pipeline_stage_id',
        'event',
        'action',
        'params',
        'is_active',
    ];

    protected $casts = [
        'params'    => 'array',
        'is_active' => 'boolean',
    ];

    // События (триггеры)
    const EVENT_STAGE_CHANGED          = 'stage_changed';
    const EVENT_TASK_CREATED           = 'task_created';
    const EVENT_APPOINTMENT_REMINDER   = 'appointment_reminder';

    // Действия
    const ACTION_SEND_MESSAGE = 'send_message';
    const ACTION_CREATE_TASK  = 'create_task';
    const ACTION_CHANGE_STAGE = 'change_stage';

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }
}
