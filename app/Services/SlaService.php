<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Task;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\Log;

class SlaService
{
    const SLA_THRESHOLDS = [
        'incoming_call' => ['goal' => 10,  'critical' => 20],
        'missed_call'   => ['goal' => 600, 'critical' => 1800],
        'website_form'  => ['goal' => 900, 'critical' => 3600],
        'chat'          => ['goal' => 300, 'critical' => 1200],
        'night'         => ['goal' => '09:30', 'critical' => '10:00'],
    ];

    const MAX_CALL_ATTEMPTS    = 3;
    const MAX_MESSAGE_ATTEMPTS = 2;

    public function handleNewLead(Lead $lead, string $source = 'form'): void
    {
        $meta                   = $lead->meta ?? [];
        $meta['sla_started_at'] = now()->toIso8601String();
        $meta['sla_source']     = $source;

        if ($this->isNightTime()) {
            $meta['sla_priority'] = 'high';
            $meta['sla_note']     = 'Ночная заявка - обработка до 09:30';
        }

        $lead->meta = $meta;
        $lead->save();
    }

    public function handleMissedCall(Lead $lead, int $attempt = 1): void
    {
        $meta                         = $lead->meta ?? [];
        $meta['missed_call_attempts'] = $attempt;
        $meta['last_call_attempt_at'] = now()->toIso8601String();
        $lead->meta                   = $meta;
        $lead->save();

        if ($attempt === 1) {
            $this->updateLeadStage($lead, 'Недозвон / Пауза');
            $this->createTask($lead, 'Перезвонить клиенту (попытка #2)', now()->addHours(2));
        } elseif ($attempt === 2) {
            $this->updateLeadStage($lead, 'Недозвон / Пауза');
            $this->createTask($lead, 'Перезвонить клиенту (попытка #3)', now()->addDay()->setTime(9, 0));
        } elseif ($attempt >= self::MAX_CALL_ATTEMPTS) {
            $this->updateLeadStage($lead, 'Некачественный лид');
            $meta           = $lead->meta ?? [];
            $meta['closed'] = true;
            $lead->meta     = $meta;
            $lead->save();
        }
    }

    public function checkNewLeadsSla(): void
    {
        $stageId = $this->getStageIdByName('Необработан');
        if (! $stageId) {
            return;
        }

        Lead::where('pipeline_stage_id', $stageId)
            ->where('created_at', '<', now()->subMinutes(30))
            ->each(function (Lead $lead): void {
                $this->notifyManager("Лид #{$lead->id} висит в статусе 'Новый' более 30 минут!");
            });
    }

    public function checkZeroInbox(): void
    {
        $stageId = $this->getStageIdByName('Необработан');
        if (! $stageId) {
            return;
        }

        $count = Lead::where('pipeline_stage_id', $stageId)->count();
        if ($count > 0) {
            $this->notifyManager("В статусе 'Новый' осталось {$count} необработанных лидов!");
        }
    }

    public function ensureNextStep(Lead $lead): void
    {
        $hasPendingTask = Task::where('taskable_type', Lead::class)
            ->where('taskable_id', $lead->id)
            ->where('status', Task::STATUS_PENDING)
            ->where('due_at', '>', now())
            ->exists();

        if (! $hasPendingTask) {
            $this->createTask($lead, 'Назначить следующий шаг для лида', now()->addHour());
        }
    }

    public function ensureRejectionReason(Lead $lead, ?string $reason = null): void
    {
        $rejectionStageId = $this->getStageIdByName('Некачественный лид');
        if ($lead->pipeline_stage_id === $rejectionStageId && ! $reason) {
            $this->createTask($lead, 'Указать причину отказа для лида', now()->addHour());
        }
    }

    public function handleMedicalInfoRequest(Lead $lead): void
    {
        $this->createTask($lead, 'Уточнить у врача и перезвонить пациенту', now()->addMinutes(30));
    }

    public function handleMedicalTeamDispatch(Lead $lead): void
    {
        $this->createTask($lead, 'Передать в график бригады', now()->addMinutes(15));
    }

    public function checkMissedCallSla(): void
    {
        $stageId = $this->getStageIdByName('Недозвон / Пауза');
        if (! $stageId) {
            return;
        }

        Lead::where('pipeline_stage_id', $stageId)
            ->where('updated_at', '<', now()->subMinutes(10))
            ->each(function (Lead $lead): void {
                $this->notifyManager("Недозвон по лиду #{$lead->id} более 10 минут назад!");
            });
    }

    public function checkChatSla(): void
    {
        Lead::whereJsonContains('meta->sla_source', 'chat')
            ->where('created_at', '<', now()->subMinutes(5))
            ->each(function (Lead $lead): void {
                $this->notifyManager("Чат (лид #{$lead->id}) ожидает ответа более 5 минут!");
            });
    }

    public function checkWebsiteFormSla(): void
    {
        Lead::whereJsonContains('meta->sla_source', 'form')
            ->where('created_at', '<', now()->subMinutes(15))
            ->each(function (Lead $lead): void {
                $this->notifyManager("Заявка с сайта (лид #{$lead->id}) ожидает ответа более 15 минут!");
            });
    }

    public function checkNightLeads(): void
    {
        Lead::whereJsonContains('meta->sla_priority', 'high')
            ->where('updated_at', '<', now()->setTime(9, 30))
            ->each(function (Lead $lead): void {
                $this->notifyManager("Ночная заявка (лид #{$lead->id}) должна быть обработана до 09:30!");
            });
    }

    // ──────────────────────────────────────────
    // Protected helpers
    // ──────────────────────────────────────────

    protected function getStageIdByName(string $name): ?int
    {
        return PipelineStage::where('name', $name)->value('id');
    }

    protected function updateLeadStage(Lead $lead, string $stageName): void
    {
        $stageId = $this->getStageIdByName($stageName);
        if ($stageId) {
            $lead->pipeline_stage_id = $stageId;
            $lead->save();
        }
    }

    /**
     * @param \Illuminate\Support\Carbon|\DateTimeInterface $dueAt
     */
    protected function createTask(Lead $lead, string $title, $dueAt): void
    {
        Task::create([
            'taskable_type' => Lead::class,
            'taskable_id'   => $lead->id,
            'user_id'       => $lead->user_id,
            'title'         => $title,
            'due_at'        => $dueAt,
            'type'          => Task::TYPE_CALL,
            'status'        => Task::STATUS_PENDING,
        ]);
    }

    protected function notifyManager(string $message): void
    {
        Log::info("SLA Alert: {$message}");
    }

    protected function isNightTime(): bool
    {
        $hour = now()->hour;
        return $hour >= 21 || $hour < 9;
    }
}
