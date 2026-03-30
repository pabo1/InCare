<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Deal extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pipeline_id',
        'pipeline_stage_id',
        'user_id',
        'contact_id',
        'lead_id',
        'name',
        'branch',
        'appointment_at',
        'payment_status',
        'cancel_reason',
        'amount',
        'telegram_chat_id',
        'meta',
    ];

    protected $casts = [
        'appointment_at' => 'datetime',
        'amount' => 'decimal:2',
        'meta' => 'array',
    ];

    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PAID = 'paid';

    public function pipeline(): BelongsTo
    {
        return $this->belongsTo(Pipeline::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(PipelineStage::class, 'pipeline_stage_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function analyses(): BelongsToMany
    {
        return $this->belongsToMany(Analysis::class, 'deal_analyses')
            ->withPivot('price')
            ->withTimestamps();
    }

    public function tasks(): MorphMany
    {
        return $this->morphMany(Task::class, 'taskable');
    }

    public function messages(): MorphMany
    {
        return $this->morphMany(Message::class, 'messageable');
    }

    public function stageHistory(): MorphMany
    {
        return $this->morphMany(StageHistory::class, 'stageable');
    }
}
