<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Support\CrmReferenceData;
use App\Support\CrmStageRequirements;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DealController extends Controller
{
    public function index(Request $request)
    {
        $deals = Deal::with(['contact', 'stage', 'user', 'analyses'])
            ->when($request->stage_id, fn ($q, $v) => $q->where('pipeline_stage_id', $v))
            ->when($request->user_id, fn ($q, $v) => $q->where('user_id', $v))
            ->when($request->branch, fn ($q, $v) => $q->where('branch', $v))
            ->when($request->analysis_id, fn ($q, $v) => $q->whereHas('analyses', fn ($analysisQuery) => $analysisQuery->where('analyses.id', $v)))
            ->latest()
            ->paginate(50);

        return response()->json($deals);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'contact_id' => 'nullable|exists:contacts,id',
            'lead_id' => 'nullable|exists:leads,id',
            'pipeline_stage_id' => 'required|exists:pipeline_stages,id',
            'user_id' => 'nullable|exists:users,id',
            'branch' => $this->branchRules(),
            'appointment_at' => 'nullable|date',
            'payment_status' => ['nullable', 'string', Rule::in(CrmReferenceData::values('payment_statuses'))],
            'cancel_reason' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'analyses' => 'nullable|array',
        ]);

        $stage = PipelineStage::with('pipeline')->findOrFail($data['pipeline_stage_id']);
        $this->ensureStageType($stage, 'deals');
        CrmStageRequirements::assertDealCanBePlacedInStage(
            $stage,
            $data,
            null,
            $this->payloadHasAnalyses($data['analyses'] ?? null)
        );

        $meta = $this->buildMeta(null, [
            'notes' => $data['notes'] ?? null,
        ]);

        $analysesPayload = $data['analyses'] ?? null;

        unset($data['notes'], $data['analyses']);

        $deal = DB::transaction(function () use ($request, $data, $stage, $meta, $analysesPayload) {
            $deal = Deal::create([
                ...$data,
                'pipeline_id' => $stage->pipeline_id,
                'user_id' => $data['user_id'] ?? $request->user()->id,
                'meta' => $meta,
            ]);

            if (is_array($analysesPayload)) {
                $this->syncAnalyses($deal, $analysesPayload);
            }

            $deal->stageHistory()->create([
                'pipeline_stage_id' => $deal->pipeline_stage_id,
                'user_id' => $request->user()->id,
                'entered_at' => now(),
            ]);

            return $deal;
        });

        return response()->json($deal->load(['contact', 'stage', 'user', 'analyses']), 201);
    }

    public function show(Deal $deal)
    {
        return response()->json(
            $deal->load(['contact', 'stage', 'user', 'analyses', 'tasks', 'stageHistory.stage'])
        );
    }

    public function update(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'contact_id' => 'nullable|exists:contacts,id',
            'lead_id' => 'nullable|exists:leads,id',
            'user_id' => 'nullable|exists:users,id',
            'branch' => $this->branchRules(),
            'appointment_at' => 'nullable|date',
            'payment_status' => ['nullable', 'string', Rule::in(CrmReferenceData::values('payment_statuses'))],
            'cancel_reason' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'analyses' => 'nullable|array',
        ]);

        $analysesProvided = array_key_exists('analyses', $data);
        $analysesPayload = $data['analyses'] ?? [];

        if (array_key_exists('notes', $data)) {
            $data['meta'] = $this->buildMeta($deal->meta, [
                'notes' => $data['notes'],
            ]);
            unset($data['notes']);
        }

        unset($data['analyses']);

        $stage = $deal->stage()->with('pipeline')->first();

        if ($stage) {
            CrmStageRequirements::assertDealCanBePlacedInStage(
                $stage,
                $data,
                $deal,
                $analysesProvided ? $this->payloadHasAnalyses($analysesPayload) : null
            );
        }

        DB::transaction(function () use ($deal, $data, $analysesProvided, $analysesPayload) {
            $deal->update($data);

            if ($analysesProvided) {
                $this->syncAnalyses($deal, $analysesPayload);
            }
        });

        return response()->json($deal->fresh()->load(['contact', 'stage', 'user', 'analyses']));
    }

    public function destroy(Deal $deal)
    {
        $deal->delete();

        return response()->json(null, 204);
    }

    public function moveStage(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'stage_id' => 'required|exists:pipeline_stages,id',
            'name' => 'sometimes|string|max:255',
            'contact_id' => 'nullable|exists:contacts,id',
            'lead_id' => 'nullable|exists:leads,id',
            'user_id' => 'nullable|exists:users,id',
            'branch' => $this->branchRules(),
            'appointment_at' => 'nullable|date',
            'payment_status' => ['nullable', 'string', Rule::in(CrmReferenceData::values('payment_statuses'))],
            'cancel_reason' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric',
            'notes' => 'nullable|string',
            'analyses' => 'nullable|array',
        ]);

        $stage = PipelineStage::with('pipeline')->findOrFail($data['stage_id']);
        $this->ensureStageType($stage, 'deals', 'stage_id');

        $updates = $data;
        unset($updates['stage_id']);

        $analysesProvided = array_key_exists('analyses', $updates);
        $analysesPayload = $updates['analyses'] ?? [];

        if (array_key_exists('notes', $updates)) {
            $updates['meta'] = $this->buildMeta($deal->meta, [
                'notes' => $updates['notes'],
            ]);
            unset($updates['notes']);
        }

        unset($updates['analyses']);

        CrmStageRequirements::assertDealCanBePlacedInStage(
            $stage,
            $updates,
            $deal,
            $analysesProvided ? $this->payloadHasAnalyses($analysesPayload) : null
        );

        DB::transaction(function () use ($deal, $stage, $updates, $analysesProvided, $analysesPayload, $request) {
            if ($updates !== []) {
                $deal->update($updates);
            }

            if ($analysesProvided) {
                $this->syncAnalyses($deal, $analysesPayload);
            }

            $deal->stageHistory()
                ->whereNull('left_at')
                ->latest('entered_at')
                ->first()
                ?->update(['left_at' => now()]);

            $deal->update([
                'pipeline_id' => $stage->pipeline_id,
                'pipeline_stage_id' => $stage->id,
            ]);

            $deal->stageHistory()->create([
                'pipeline_stage_id' => $stage->id,
                'user_id' => $request->user()->id,
                'entered_at' => now(),
            ]);
        });

        return response()->json($deal->fresh()->load(['stage', 'analyses', 'contact', 'user']));
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