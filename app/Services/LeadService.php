<?php

namespace App\Services;

use App\Models\Contact;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class LeadService
{
    public function __construct(protected SlaService $slaService)
    {
    }

    public function create(array $data): Lead
    {
        return DB::transaction(function () use ($data): Lead {
            // Ищем существующий контакт
            $contact = null;

            if (! empty($data['phone'])) {
                $contact = Contact::where('phone', $data['phone'])->first();
            }

            if (! $contact && ! empty($data['telegram_chat_id'])) {
                $contact = Contact::where('telegram_chat_id', $data['telegram_chat_id'])->first();
            }

            if (! $contact) {
                $contact = Contact::create([
                    'name'             => $data['name'] ?? 'Неизвестно',
                    'phone'            => $data['phone'] ?? null,
                    'telegram_chat_id' => $data['telegram_chat_id'] ?? null,
                    'source'           => $data['source'] ?? null,
                ]);
            }

            /** @var Pipeline $pipeline */
            $pipeline = Pipeline::with('stages')
                ->where('type', 'leads')
                ->firstOrFail();

            /** @var PipelineStage $stage */
            $stage = $pipeline->stages()->orderBy('sort_order')->firstOrFail();

            /** @var Lead $lead */
            $lead = Lead::create([
                'pipeline_id'       => $pipeline->id,
                'pipeline_stage_id' => $stage->id,
                'user_id'           => Auth::id(),
                'contact_id'        => $contact ? $contact->id : null,
                'name'              => $data['name'] ?? $contact ? $contact->name : 'Неизвестно',
                'phone'             => $data['phone'] ?? $contact ? $contact->phone : null,
                'source'            => $data['source'] ?? $contact ? $contact->source : null,
                'request_type'      => $data['request_type'] ?? null,
                'branch'            => $data['branch'] ?? null,
                'telegram_chat_id'  => $data['telegram_chat_id'] ?? null,
                'external_id'       => $data['external_id'] ?? null,
                'meta'              => $data['meta'] ?? [],
            ]);

            $slaSource = match ($lead->source) {
                Lead::SOURCE_TELEGRAM, Lead::SOURCE_INSTAGRAM => 'chat',
                Lead::SOURCE_SIPUNI                           => 'missed_call',
                default                                       => 'form',
            };

            $this->slaService->handleNewLead($lead, $slaSource);

            return $lead;
        });
    }

    public function changeStage(Lead $lead, int $stageId): void
    {
        $lead->pipeline_stage_id = $stageId;
        $lead->save();
    }

    public function handleMissedCall(Lead $lead, int $attempt = 1): void
    {
        $this->slaService->handleMissedCall($lead, $attempt);
    }

    public function runAllSlaChecks(): void
    {
        $this->slaService->checkNewLeadsSla();
        $this->slaService->checkMissedCallSla();
        $this->slaService->checkChatSla();
        $this->slaService->checkWebsiteFormSla();
        $this->slaService->checkNightLeads();
        $this->slaService->checkZeroInbox();
    }
}
