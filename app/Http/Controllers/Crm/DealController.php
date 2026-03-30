<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Analysis;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Task;
use App\Support\CrmReferenceData;
use App\Support\CrmStageRequirements;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DealController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'stage' => (string) $request->input('stage', ''),
            'branch' => (string) $request->input('branch', ''),
            'payment_status' => (string) $request->input('payment_status', ''),
        ];

        $query = Deal::query()
            ->with(['contact', 'stage', 'user'])
            ->withCount('tasks');

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('branch', 'like', "%{$search}%")
                    ->orWhereHas('contact', function ($contactQuery) use ($search): void {
                        $contactQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($filters['stage'] !== '') {
            $query->where('pipeline_stage_id', $filters['stage']);
        }

        if ($filters['branch'] !== '') {
            $query->where('branch', $filters['branch']);
        }

        if ($filters['payment_status'] !== '') {
            $query->where('payment_status', $filters['payment_status']);
        }

        $pipeline = Pipeline::with('stages')->where('type', 'deals')->first();
        $stageCounts = Deal::selectRaw('pipeline_stage_id, COUNT(*) as aggregate')
            ->groupBy('pipeline_stage_id')
            ->pluck('aggregate', 'pipeline_stage_id');

        $deals = $query
            ->orderByRaw('appointment_at IS NULL, appointment_at ASC')
            ->latest('updated_at')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Deal $deal) => $this->serializeDealCard($deal));

        return Inertia::render('Deals/Index', [
            'filters' => $filters,
            'stats' => [
                'total' => Deal::count(),
                'scheduled' => Deal::whereNotNull('appointment_at')->count(),
                'paid' => Deal::where('payment_status', Deal::PAYMENT_PAID)->count(),
                'pending_tasks' => Task::where('taskable_type', Deal::class)
                    ->where('status', Task::STATUS_PENDING)
                    ->count(),
            ],
            'pipeline' => $pipeline ? [
                'name' => $pipeline->name,
                'stages' => $pipeline->stages->map(fn ($stage) => [
                    'id' => (string) $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'is_final' => (bool) $stage->is_final,
                    'is_fail' => (bool) $stage->is_fail,
                    'count' => (int) ($stageCounts[$stage->id] ?? 0),
                ])->values(),
            ] : null,
            'referenceData' => [
                'branches' => CrmReferenceData::branchOptions(),
                'paymentStatuses' => CrmReferenceData::options('payment_statuses'),
            ],
            'deals' => $deals,
        ]);
    }

    public function show(Deal $deal): Response
    {
        $deal->load([
            'contact',
            'lead',
            'stage',
            'user',
            'pipeline.stages',
            'tasks.user',
            'stageHistory.stage',
            'stageHistory.user',
            'analyses',
        ]);

        $leadOptions = Lead::query()
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Lead $leadOption) => [
                'id' => $leadOption->id,
                'label' => 'Лид #' . $leadOption->id . ' · ' . ($leadOption->name ?: 'Без названия'),
            ])
            ->values();

        $analysisOptions = Analysis::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Analysis $analysis) => [
                'id' => $analysis->id,
                'name' => $analysis->name,
                'code' => $analysis->code,
                'price' => (string) $analysis->price,
            ])
            ->values();

        return Inertia::render('Deals/Show', [
            'deal' => $this->serializeDealDetail($deal),
            'availableStages' => $deal->pipeline
                ? $deal->pipeline->stages->map(fn ($stage) => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'is_final' => (bool) $stage->is_final,
                    'is_fail' => (bool) $stage->is_fail,
                    'is_current' => $stage->id === $deal->pipeline_stage_id,
                    'move_url' => route('crm.deals.stage', $deal),
                ])->values()
                : [],
            'referenceData' => [
                'branches' => CrmReferenceData::branchOptions(),
                'paymentStatuses' => CrmReferenceData::options('payment_statuses'),
                'taskTypes' => CrmReferenceData::options('task_types'),
            ],
            'leadOptions' => $leadOptions,
            'analysisOptions' => $analysisOptions,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'branch' => $this->branchRules(),
            'appointment_at' => 'nullable|date',
            'payment_status' => ['nullable', 'string', Rule::in(CrmReferenceData::values('payment_statuses'))],
            'amount' => 'nullable|numeric',
        ]);

        $pipeline = Pipeline::query()
            ->with(['stages' => fn ($query) => $query->orderBy('sort_order')])
            ->where('type', 'deals')
            ->firstOrFail();

        $initialStage = $pipeline->stages->first();

        abort_unless($initialStage, 500, 'Deal pipeline has no stages.');

        CrmStageRequirements::assertDealCanBePlacedInStage(
            $initialStage,
            $data + ['payment_status' => $data['payment_status'] ?? Deal::PAYMENT_UNPAID],
        );

        $deal = Deal::create([
            ...$data,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $initialStage->id,
            'user_id' => $request->user()->id,
            'payment_status' => $data['payment_status'] ?? Deal::PAYMENT_UNPAID,
            'amount' => $data['amount'] ?? 0,
        ]);

        $deal->stageHistory()->create([
            'pipeline_stage_id' => $deal->pipeline_stage_id,
            'user_id' => $request->user()->id,
            'entered_at' => now(),
        ]);

        return to_route('crm.deals.show', $deal, 303);
    }

    public function update(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'branch' => $this->branchRules(),
            'appointment_at' => 'nullable|date',
            'payment_status' => ['nullable', 'string', Rule::in(CrmReferenceData::values('payment_statuses'))],
            'cancel_reason' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric',
        ]);

        $deal->update($data);

        return to_route('crm.deals.show', $deal, 303);
    }

    public function destroy(Deal $deal): RedirectResponse
    {
        $deal->delete();

        return redirect()->route('crm.deals.index');
    }

    public function upsertContact(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ]);

        $contact = $deal->contact ?? $this->findMatchingContact(
            $data['phone'] ?? null,
            $data['email'] ?? null,
        ) ?? new Contact();

        $contact->fill([
            'name' => $this->buildFullName($data['first_name'], $data['last_name'] ?? null),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'branch' => $deal->branch,
            'meta' => $this->mergeMeta($contact->meta, [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
            ]),
        ]);
        $contact->save();

        $deal->update(['contact_id' => $contact->id]);

        if ($deal->lead && ! $deal->lead->contact_id) {
            $deal->lead->update(['contact_id' => $contact->id]);
        }

        return to_route('crm.deals.show', $deal, 303);
    }

    public function attachLead(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'lead_id' => 'nullable|exists:leads,id',
        ]);

        $leadId = $data['lead_id'] ?? null;

        $deal->update(['lead_id' => $leadId]);

        if ($leadId) {
            $lead = Lead::query()->findOrFail($leadId);

            if ($lead->contact_id && ! $deal->contact_id) {
                $deal->update(['contact_id' => $lead->contact_id]);
            }

            $dealStage = $deal->stage()->with('pipeline')->first();

            if ($dealStage) {
                $this->moveLeadToConvertedStageFromDealStage($lead, $dealStage, $request->user()->id);
            }
        }

        return redirect()->route('crm.deals.show', $deal);
    }

    public function updateAnalyses(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'analyses' => 'nullable|array',
        ]);

        $analysesPayload = $data['analyses'] ?? [];
        $stage = $deal->stage()->with('pipeline')->first();

        if ($stage) {
            CrmStageRequirements::assertDealCanBePlacedInStage(
                $stage,
                [],
                $deal,
                $this->payloadHasAnalyses($analysesPayload)
            );
        }

        $this->syncAnalyses($deal, $analysesPayload);

        return redirect()->route('crm.deals.show', $deal);
    }

    public function storeTask(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['nullable', Rule::in(CrmReferenceData::values('task_types'))],
            'due_at' => 'nullable|date',
        ]);

        $deal->tasks()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'user_id' => $request->user()->id,
            'type' => $data['type'] ?? Task::TYPE_CALL,
            'status' => Task::STATUS_PENDING,
            'due_at' => $data['due_at'] ?? null,
        ]);

        return redirect()->route('crm.deals.show', $deal);
    }

    public function moveStage(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'stage_id' => 'required|exists:pipeline_stages,id',
        ]);

        $stage = PipelineStage::with('pipeline')->findOrFail($data['stage_id']);

        if ($stage->pipeline?->type !== 'deals') {
            return back()->withErrors([
                'stage_id' => 'Выбранный этап не относится к воронке сделок.',
            ]);
        }

        CrmStageRequirements::assertDealCanBePlacedInStage($stage, [], $deal);

        DB::transaction(function () use ($deal, $stage, $request): void {
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

            if ($deal->lead) {
                $this->moveLeadToConvertedStageFromDealStage($deal->lead, $stage, $request->user()->id);
            }
        });

        return redirect()->route('crm.deals.show', $deal);
    }

    private function branchRules(): array
    {
        $branches = CrmReferenceData::configuredBranchValues();

        return $branches === []
            ? ['nullable', 'string', 'max:255']
            : ['nullable', 'string', 'max:255', Rule::in($branches)];
    }

    private function serializeDealCard(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->name,
            'name' => $deal->name,
            'branch' => CrmReferenceData::label('branches', $deal->branch, $deal->branch),
            'branch_value' => $deal->branch,
            'payment_status' => CrmReferenceData::label('payment_statuses', $deal->payment_status, $deal->payment_status),
            'payment_status_value' => $deal->payment_status,
            'appointment_at' => $deal->appointment_at?->format('d.m.Y H:i'),
            'appointment_relative' => $deal->appointment_at?->locale('ru')->diffForHumans(),
            'tasks_count' => $deal->tasks_count,
            'contact' => $deal->contact ? [
                'name' => $deal->contact->name,
                'phone' => $deal->contact->phone,
                'email' => $deal->contact->email,
            ] : null,
            'stage' => $deal->stage ? [
                'name' => $deal->stage->name,
                'color' => $deal->stage->color,
                'is_final' => (bool) $deal->stage->is_final,
                'is_fail' => (bool) $deal->stage->is_fail,
            ] : null,
            'user' => $deal->user?->name,
        ];
    }

    private function serializeDealDetail(Deal $deal): array
    {
        $contactNames = $this->splitContactName($deal->contact);

        return [
            'id' => $deal->id,
            'title' => $deal->name,
            'name' => $deal->name,
            'branch' => CrmReferenceData::label('branches', $deal->branch, $deal->branch),
            'branch_value' => $deal->branch,
            'payment_status' => CrmReferenceData::label('payment_statuses', $deal->payment_status, $deal->payment_status),
            'payment_status_value' => $deal->payment_status,
            'appointment_at' => $deal->appointment_at?->format('d.m.Y H:i'),
            'appointment_input' => $deal->appointment_at?->format('Y-m-d\\TH:i'),
            'appointment_relative' => $deal->appointment_at?->locale('ru')->diffForHumans(),
            'cancel_reason' => $deal->cancel_reason,
            'amount' => $deal->amount,
            'created_at' => $deal->created_at?->format('d.m.Y H:i'),
            'updated_at' => $deal->updated_at?->format('d.m.Y H:i'),
            'meta' => $deal->meta ?? [],
            'contact' => $deal->contact ? [
                'id' => $deal->contact->id,
                'name' => $deal->contact->name,
                'first_name' => $contactNames['first_name'],
                'last_name' => $contactNames['last_name'],
                'phone' => $deal->contact->phone,
                'email' => $deal->contact->email,
            ] : null,
            'lead' => $deal->lead ? [
                'id' => $deal->lead->id,
                'name' => $deal->lead->name,
            ] : null,
            'stage' => $deal->stage ? [
                'id' => $deal->stage->id,
                'name' => $deal->stage->name,
                'color' => $deal->stage->color,
                'is_final' => (bool) $deal->stage->is_final,
                'is_fail' => (bool) $deal->stage->is_fail,
            ] : null,
            'analyses' => $deal->analyses->map(fn ($analysis) => [
                'id' => $analysis->id,
                'name' => $analysis->name,
                'code' => $analysis->code,
                'price' => $analysis->pivot?->price ?? $analysis->price,
                'selected' => true,
            ])->values()->all(),
            'user' => $deal->user?->name,
            'tasks' => $deal->tasks
                ->sortBy([['status', 'asc'], ['due_at', 'asc']])
                ->values()
                ->map(fn (Task $task) => [
                    'id' => $task->id,
                    'title' => $task->title,
                    'description' => $task->description,
                    'status' => CrmReferenceData::label('task_statuses', $task->status, $task->status),
                    'type' => CrmReferenceData::label('task_types', $task->type, $task->type),
                    'due_at' => $task->due_at?->format('d.m.Y H:i'),
                    'due_relative' => $task->due_at?->locale('ru')->diffForHumans(),
                    'user' => $task->user?->name,
                ])->all(),
            'history' => $deal->stageHistory
                ->sortByDesc('entered_at')
                ->values()
                ->map(fn ($history) => [
                    'id' => $history->id,
                    'title' => $history->stage?->name ?? 'Смена этапа',
                    'subtitle' => $history->user?->name ? 'Ответственный: ' . $history->user->name : null,
                    'time' => $history->entered_at?->format('d.m.Y H:i'),
                    'note' => $history->left_at
                        ? 'Выход из этапа: ' . $history->left_at->format('d.m.Y H:i')
                        : 'Текущий этап',
                ])->all(),
        ];
    }

    private function splitContactName(?Contact $contact): array
    {
        if (! $contact) {
            return [
                'first_name' => '',
                'last_name' => '',
            ];
        }

        $meta = $contact->meta ?? [];
        $firstName = trim((string) ($meta['first_name'] ?? ''));
        $lastName = trim((string) ($meta['last_name'] ?? ''));

        if ($firstName !== '' || $lastName !== '') {
            return [
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        }

        $parts = preg_split('/\s+/u', trim((string) $contact->name)) ?: [];

        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => implode(' ', array_slice($parts, 1)),
        ];
    }

    private function buildFullName(string $firstName, ?string $lastName): string
    {
        return trim($firstName . ' ' . ($lastName ?? ''));
    }

    private function mergeMeta(?array $current, array $values): ?array
    {
        $meta = array_merge($current ?? [], array_filter(
            $values,
            static fn ($value) => $value !== null && $value !== ''
        ));

        return $meta === [] ? null : $meta;
    }

    private function findMatchingContact(?string $phone, ?string $email): ?Contact
    {
        if ($phone === null && $email === null) {
            return null;
        }

        return Contact::query()
            ->when($phone !== null, fn ($query) => $query->where('phone', $phone))
            ->when($email !== null, fn ($query) => $query->orWhere('email', $email))
            ->first();
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
                'analyses' => 'Поле анализов должно содержать идентификаторы или объекты с id.',
            ]);
        });

        if ($normalized->contains(fn ($item) => empty($item['id']))) {
            throw ValidationException::withMessages([
                'analyses' => 'Каждый анализ должен содержать корректный идентификатор.',
            ]);
        }

        $analysisIds = $normalized->pluck('id')->unique()->values();
        $analyses = Analysis::whereIn('id', $analysisIds)->get()->keyBy('id');

        if ($analyses->count() !== $analysisIds->count()) {
            throw ValidationException::withMessages([
                'analyses' => 'Один или несколько анализов не найдены.',
            ]);
        }

        $syncData = [];

        foreach ($normalized as $item) {
            $analysis = $analyses->get($item['id']);

            if ($item['price'] !== null && ! is_numeric($item['price'])) {
                throw ValidationException::withMessages([
                    'analyses' => 'Цена анализа должна быть числом.',
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

    private function moveLeadToConvertedStageFromDealStage(Lead $lead, PipelineStage $dealStage, int $actorId): void
    {
        if ($dealStage->pipeline?->type !== 'deals' || ! in_array((int) $dealStage->sort_order, [4, 6], true)) {
            return;
        }

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
}
