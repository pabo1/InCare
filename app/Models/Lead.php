<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'pipeline_id',
        'pipeline_stage_id',
        'user_id',
        'contact_id',
        'name',
        'phone',
        'source',
        'request_type',
        'branch',
        'quality',
        'telegram_chat_id',
        'external_id',
        'meta',
        // SLA поля
        'sla_started_at',
        'sla_source',
        'sla_priority',
        'sla_note',
        'missed_call_attempts',
        'last_call_attempt_at',
        'rejection_reason',
    ];

    protected $casts = [
        'meta' => 'array',
    ];

    const SOURCE_FACEBOOK = 'facebook';
    const SOURCE_TELEGRAM = 'telegram';
    const SOURCE_INSTAGRAM = 'instagram';
    const SOURCE_SIPUNI = 'sipuni';
    const SOURCE_FORM = 'form';
    const SOURCE_WIDGET = 'site_widget';

    const REQUEST_ANALYSES = 'analyses';
    const REQUEST_DOCTOR = 'doctor';
    const REQUEST_NURSE = 'nurse';
    const REQUEST_INFO = 'info';

    // Источники SLA
    const SLA_SOURCE_WEBSITE = 'website';
    const SLA_SOURCE_CHAT = 'chat';
    const SLA_SOURCE_INCOMING_CALL = 'incoming_call';
    const SLA_SOURCE_MISSED_CALL = 'missed_call';

    // Приоритеты SLA
    const SLA_PRIORITY_LOW = 'low';
    const SLA_PRIORITY_NORMAL = 'normal';
    const SLA_PRIORITY_HIGH = 'high';

    // Причины отказа
    const REJECTION_REASON_EXPENSIVE = 'Дорого';
    const REJECTION_REASON_INCONVENIENT_TIME = 'Неудобное время';
    const REJECTION_REASON_COMPETITORS = 'Ушли к конкурентам';
    const REJECTION_REASON_SPAM = 'Спам';
    const REJECTION_REASON_NO_CONTACT = 'Не выходит на связь';

    public static function getRejectionReasons(): array
    {
        return [
            self::REJECTION_REASON_EXPENSIVE,
            self::REJECTION_REASON_INCONVENIENT_TIME,
            self::REJECTION_REASON_COMPETITORS,
            self::REJECTION_REASON_SPAM,
            self::REJECTION_REASON_NO_CONTACT,
        ];
    }

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
