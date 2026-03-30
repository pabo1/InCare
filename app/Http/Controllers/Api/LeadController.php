<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\PipelineStage;
use App\Support\CrmReferenceData;
use App\Support\CrmStageRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $leads = Lead::with(['contact', 'stage', 'user'])
            ->when($request->stage_id, fn ($q, $v) => $q->where('pipeline_stage_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->latest()
            ->paginate(50);

        return response()->json($leads);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_id' => 'nullable|exists:contacts,id',
            'pipeline_stage_id' => 'required|exists:pipeline_stages,id',
            'phone' => 'nullable|string|max:50',
            'source' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_sources'))],
            'request_type' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('request_types'))],
            'branch' => $this->branchRules(),
            'user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $stage = PipelineStage::with('pipeline')->findOrFail($data['pipeline_stage_id']);
        $this->ensureStageType($stage, 'leads');
        CrmStageRequirements::assertLeadCanBePlacedInStage($stage, $data);

        $meta = $this->buildMeta(null, [
            'notes' => $data['notes'] ?? null,
        ]);

        unset($data['notes']);

        $lead = Lead::create([
            ...$data,
            'pipeline_id' => $stage->pipeline_id,
            'user_id' => $data['user_id'] ?? $request->user()->id,
            'meta' => $meta,
        ]);

        $lead->stageHistory()->create([
            'pipeline_stage_id' => $lead->pipeline_stage_id,
            'user_id' => $request->user()->id,
            'entered_at' => now(),
        ]);

        return response()->json($lead->load(['contact', 'stage', 'user']), 201);
    }

    public function show(Lead $lead)
    {
        return response()->json(
            $lead->load(['contact', 'stage', 'user', 'tasks', 'stageHistory.stage'])
        );
    }

    public function update(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'contact_id' => 'nullable|exists:contacts,id',
            'phone' => 'nullable|string|max:50',
            'source' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_sources'))],
            'request_type' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('request_types'))],
            'branch' => $this->branchRules(),
            'user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        if (array_key_exists('notes', $data)) {
            $data['meta'] = $this->buildMeta($lead->meta, [
                'notes' => $data['notes'],
            ]);
            unset($data['notes']);
        }

        $stage = $lead->stage()->with('pipeline')->first();

        if ($stage) {
            CrmStageRequirements::assertLeadCanBePlacedInStage($stage, $data, $lead);
        }

        $lead->update($data);

        return response()->json($lead->load(['contact', 'stage', 'user']));
    }

    public function destroy(Lead $lead)
    {
        $lead->delete();

        return response()->json(null, 204);
    }

    public function moveStage(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'stage_id' => 'required|exists:pipeline_stages,id',
            'name' => 'sometimes|string|max:255',
            'contact_id' => 'nullable|exists:contacts,id',
            'phone' => 'nullable|string|max:50',
            'source' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_sources'))],
            'request_type' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('request_types'))],
            'branch' => $this->branchRules(),
            'user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $stage = PipelineStage::with('pipeline')->findOrFail($data['stage_id']);
        $this->ensureStageType($stage, 'leads', 'stage_id');

        $updates = $data;
        unset($updates['stage_id']);

        if (! array_key_exists('user_id', $updates) && $lead->user_id === null) {
            $updates['user_id'] = $request->user()->id;
        }

        if (array_key_exists('notes', $updates)) {
            $updates['meta'] = $this->buildMeta($lead->meta, [
                'notes' => $updates['notes'],
            ]);
            unset($updates['notes']);
        }

        CrmStageRequirements::assertLeadCanBePlacedInStage($stage, $updates, $lead, false, 'stage_id');

        DB::transaction(function () use ($lead, $stage, $updates, $request) {
            if ($updates !== []) {
                $lead->update($updates);
            }

            $lead->stageHistory()
                ->whereNull('left_at')
                ->latest('entered_at')
                ->first()
                ?->update(['left_at' => now()]);

            $lead->update([
                'pipeline_id' => $stage->pipeline_id,
                'pipeline_stage_id' => $stage->id,
            ]);

            $lead->stageHistory()->create([
                'pipeline_stage_id' => $stage->id,
                'user_id' => $request->user()->id,
                'entered_at' => now(),
            ]);
        });

        return response()->json($lead->fresh()->load(['contact', 'stage', 'user']));
    }

    public function convert(Request $request, Lead $lead)
    {
        $data = $request->validate([
            'deal_stage_id' => 'required|exists:pipeline_stages,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'user_id' => 'nullable|exists:users,id',
            'branch' => $this->branchRules(),
            'appointment_at' => 'nullable|date',
            'payment_status' => ['nullable', 'string', Rule::in(CrmReferenceData::values('payment_statuses'))],
            'amount' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'analyses' => 'nullable|array',
        ]);

        $stage = PipelineStage::with('pipeline')->findOrFail($data['deal_stage_id']);
        $this->ensureStageType($stage, 'deals', 'deal_stage_id');

        CrmStageRequirements::assertLeadReadyForConversion($lead, [
            'branch' => $data['branch'] ?? $lead->branch,
        ]);
        CrmStageRequirements::assertDealCanBePlacedInStage(
            $stage,
            [
                'branch' => $data['branch'] ?? $lead->branch,
                'appointment_at' => $data['appointment_at'] ?? null,
                'payment_status' => $data['payment_status'] ?? Deal::PAYMENT_UNPAID,
            ],
            null,
            $this->payloadHasAnalyses($data['analyses'] ?? null)
        );

        $analysesPayload = $data['analyses'] ?? null;
        $notes = $data['notes'] ?? null;

        unset($data['analyses'], $data['notes'], $data['deal_stage_id']);

        $deal = DB::transaction(function () use ($request, $lead, $stage, $data, $notes, $analysesPayload) {
            $contact = $this->resolveContactForConversion(
                $lead,
                $data['contact_id'] ?? null,
                $data['branch'] ?? $lead->branch,
                $notes
            );

            $leadUpdates = [];

            if ($lead->contact_id !== $contact->id) {
                $leadUpdates['contact_id'] = $contact->id;
            }

            if (($data['branch'] ?? null) && $lead->branch !== $data['branch']) {
                $leadUpdates['branch'] = $data['branch'];
            }

            if ($leadUpdates !== []) {
                $lead->update($leadUpdates);
            }

            $deal = Deal::create([
                'pipeline_id' => $stage->pipeline_id,
                'pipeline_stage_id' => $stage->id,
                'user_id' => $data['user_id'] ?? $lead->user_id ?? $request->user()->id,
                'contact_id' => $contact->id,
                'lead_id' => $lead->id,
                'name' => $lead->name,
                'branch' => $data['branch'] ?? $lead->branch,
                'appointment_at' => $data['appointment_at'] ?? null,
                'payment_status' => $data['payment_status'] ?? Deal::PAYMENT_UNPAID,
                'amount' => $data['amount'] ?? 0,
                'meta' => $this->buildMeta(null, [
                    'source' => $lead->source,
                    'request_type' => $lead->request_type,
                    'notes' => $notes,
                ]),
            ]);

            if (is_array($analysesPayload)) {
                $this->syncAnalyses($deal, $analysesPayload);
            }

            $deal->stageHistory()->create([
                'pipeline_stage_id' => $deal->pipeline_stage_id,
                'user_id' => $request->user()->id,
                'entered_at' => now(),
            ]);

            $this->moveLeadToConvertedStage($lead, $request->user()->id);

            $lead->update([
                'meta' => $this->buildMeta($lead->meta, [
                    'converted_deal_id' => $deal->id,
                ]),
            ]);

            return $deal;
        });

        return response()->json([
            'lead' => $lead->fresh()->load(['contact', 'stage', 'stageHistory.stage']),
            'deal' => $deal->load(['contact', 'stage', 'analyses']),
        ], 201);
    }

    private function moveLeadToConvertedStage(Lead $lead, int $actorId): void
    {
        $successStage = PipelineStage::query()
            ->where('pipeline_id', $lead->pipeline_id)
            ->where('is_final', true)
            ->where('is_fail', false)
            ->orderBy('sort_order')
            ->first();

        if (! $successStage || $successStage->id === $lead->pipeline_stage_id) {
            return;
        }

        $lead->stageHistory()
            ->whereNull('left_at')
            ->latest('entered_at')
            ->first()
            ?->update(['left_at' => now()]);

        $lead->update([
            'pipeline_stage_id' => $successStage->id,
        ]);

        $lead->stageHistory()->create([
            'pipeline_stage_id' => $successStage->id,
            'user_id' => $actorId,
            'entered_at' => now(),
        ]);
    }

    private function syncAnalyses(Deal $deal, array $payload): void
    {
        $normalized = collect($payload)->map(function ($item) {
            if (is_numeric($item)) {
                return [
                    'id' => (int) $item,
                    'price' => null,
                ];
            }

            if (is_array($item)) {
                $id = $item['id'] ?? $item['analysis_id'] ?? null;
                $price = $item['price'] ?? null;

                return [
                    'id' => $id !== null ? (int) $id : null,
                    'price' => $price,
                ];
            }

            throw ValidationException::withMessages([
                'analyses' => 'The analyses field must contain IDs or objects with id/analysis_id.',
            ]);
        });

        if ($normalized->contains(fn ($item) => empty($item['id']))) {
            throw ValidationException::withMessages([
                'analyses' => 'Each analysis entry must contain a valid id.',
            ]);
        }

        $analysisIds = $normalized->pluck('id')->unique()->values();
        $analyses = Analysis::whereIn('id', $analysisIds)->get()->keyBy('id');

        if ($analyses->count() !== $analysisIds->count()) {
            throw ValidationException::withMessages([
                'analyses' => 'One or more analyses were not found.',
            ]);
        }

        $syncData = [];

        foreach ($normalized as $item) {
            $analysis = $analyses->get($item['id']);

            if ($item['price'] !== null && ! is_numeric($item['price'])) {
                throw ValidationException::withMessages([
                    'analyses' => 'Analysis price must be numeric when provided.',
                ]);
            }

            $syncData[$item['id']] = [
                'price' => $item['price'] !== null
                    ? (float) $item['price']
                    : (float) $analysis->price,
            ];
        }

        $deal->analyses()->sync($syncData);
    }

    private function resolveContactForConversion(Lead $lead, ?int $requestedContactId, ?string $branch, ?string $notes): Contact
    {
        if ($requestedContactId) {
            return Contact::query()->findOrFail($requestedContactId);
        }

        if ($lead->contact_id) {
            return Contact::query()->findOrFail($lead->contact_id);
        }

        $contact = $this->findLeadContact($lead);

        if (! $contact) {
            return Contact::create([
                'name' => $lead->name,
                'phone' => $lead->phone,
                'telegram_chat_id' => $lead->telegram_chat_id,
                'source' => $lead->source,
                'branch' => $branch,
                'meta' => $this->buildMeta(null, [
                    'notes' => $notes,
                ]),
            ]);
        }

        $contact->fill(array_filter([
            'name' => $contact->name ?: $lead->name,
            'phone' => $contact->phone ?: $lead->phone,
            'telegram_chat_id' => $contact->telegram_chat_id ?: $lead->telegram_chat_id,
            'source' => $contact->source ?: $lead->source,
            'branch' => $contact->branch ?: $branch,
        ], static fn ($value) => $value !== null && $value !== ''));

        $contact->meta = $this->buildMeta($contact->meta, [
            'notes' => $notes,
        ]);
        $contact->save();

        return $contact;
    }

    private function findLeadContact(Lead $lead): ?Contact
    {
        $contactQuery = Contact::query();
        $matched = false;

        if ($lead->phone) {
            $contactQuery->where('phone', $lead->phone);
            $matched = true;
        }

        if ($lead->telegram_chat_id) {
            if ($matched) {
                $contactQuery->orWhere('telegram_chat_id', $lead->telegram_chat_id);
            } else {
                $contactQuery->where('telegram_chat_id', $lead->telegram_chat_id);
                $matched = true;
            }
        }

        return $matched ? $contactQuery->first() : null;
    }

    private function payloadHasAnalyses(?array $payload): bool
    {
        return is_array($payload) && count($payload) > 0;
    }

    private function ensureStageType(PipelineStage $stage, string $expectedType, string $field = 'pipeline_stage_id'): void
    {
        if ($stage->pipeline?->type !== $expectedType) {
            throw ValidationException::withMessages([
                $field => "The selected stage must belong to the {$expectedType} pipeline.",
            ]);
        }
    }

    private function branchRules(): array
    {
        $configuredBranches = CrmReferenceData::configuredBranchValues();

        $rules = ['nullable', 'string', 'max:255'];

        if ($configuredBranches !== []) {
            $rules[] = Rule::in($configuredBranches);
        }

        return $rules;
    }

    private function buildMeta(?array $current, array $extra): ?array
    {
        $filtered = array_filter($extra, static fn ($value) => $value !== null && $value !== '' && $value !== []);
        $meta = array_merge($current ?? [], $filtered);

        return $meta === [] ? null : $meta;
    }
}