<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
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
use Inertia\Inertia;
use Inertia\Response;

class LeadController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->input('search', '')),
            'stage' => (string) $request->input('stage', ''),
            'source' => (string) $request->input('source', ''),
            'request_type' => (string) $request->input('request_type', ''),
            'branch' => (string) $request->input('branch', ''),
        ];

        $query = Lead::query()
            ->with(['contact', 'stage', 'user'])
            ->withCount('tasks');

        if ($filters['search'] !== '') {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
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

        if ($filters['source'] !== '') {
            $query->where('source', $filters['source']);
        }

        if ($filters['request_type'] !== '') {
            $query->where('request_type', $filters['request_type']);
        }

        if ($filters['branch'] !== '') {
            $query->where('branch', $filters['branch']);
        }

        $pipeline = Pipeline::with('stages')->where('type', 'leads')->first();
        $stageCounts = Lead::selectRaw('pipeline_stage_id, COUNT(*) as aggregate')
            ->groupBy('pipeline_stage_id')
            ->pluck('aggregate', 'pipeline_stage_id');

        $leads = $query->latest()->paginate(12)->withQueryString()
            ->through(fn (Lead $lead) => $this->serializeLeadCard($lead));

        return Inertia::render('Leads/Index', [
            'filters' => $filters,
            'stats' => [
                'total' => Lead::count(),
                'with_contacts' => Lead::whereNotNull('contact_id')->count(),
                'pending_tasks' => Task::where('taskable_type', Lead::class)
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
                'sources' => CrmReferenceData::options('lead_sources'),
                'requestTypes' => CrmReferenceData::options('request_types'),
                'qualities' => CrmReferenceData::options('lead_qualities'),
                'branches' => CrmReferenceData::branchOptions(),
            ],
            'leads' => $leads,
        ]);
    }

    public function show(Lead $lead): Response
    {
        $lead->load([
            'contact',
            'stage',
            'user',
            'pipeline.stages',
            'tasks.user',
            'stageHistory.stage',
            'stageHistory.user',
        ]);

        $relatedDeals = Deal::with(['stage', 'contact'])
            ->withCount('tasks')
            ->where('lead_id', $lead->id)
            ->latest()
            ->get();

        $dealOptions = Deal::query()
            ->with('lead')
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->map(fn (Deal $dealOption) => [
                'id' => $dealOption->id,
                'label' => 'Сделка #' . $dealOption->id . ' · ' . ($dealOption->name ?: 'Без названия'),
                'lead_id' => $dealOption->lead_id,
                'lead_name' => $dealOption->lead?->name,
            ])
            ->values();

        return Inertia::render('Leads/Show', [
            'lead' => $this->serializeLeadDetail($lead),
            'availableStages' => $lead->pipeline
                ? $lead->pipeline->stages->map(fn ($stage) => [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'is_final' => (bool) $stage->is_final,
                    'is_fail' => (bool) $stage->is_fail,
                    'is_current' => $stage->id === $lead->pipeline_stage_id,
                    'move_url' => route('crm.leads.stage', $lead),
                ])->values()
                : [],
            'referenceData' => [
                'sources' => CrmReferenceData::options('lead_sources'),
                'requestTypes' => CrmReferenceData::options('request_types'),
                'qualities' => CrmReferenceData::options('lead_qualities'),
                'branches' => CrmReferenceData::branchOptions(),
                'taskTypes' => CrmReferenceData::options('task_types'),
            ],
            'dealOptions' => $dealOptions,
            'relatedDeals' => $relatedDeals->map(fn (Deal $deal) => $this->serializeRelatedDeal($deal))->values(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_sources'))],
            'request_type' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('request_types'))],
            'quality' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_qualities'))],
            'branch' => $this->branchRules(),
        ]);

        $pipeline = Pipeline::query()
            ->with(['stages' => fn ($query) => $query->orderBy('sort_order')])
            ->where('type', 'leads')
            ->firstOrFail();

        $initialStage = $pipeline->stages->first();

        abort_unless($initialStage, 500, 'Lead pipeline has no stages.');

        $lead = Lead::create([
            ...$data,
            'pipeline_id' => $pipeline->id,
            'pipeline_stage_id' => $initialStage->id,
            'user_id' => $request->user()->id,
        ]);

        $lead->stageHistory()->create([
            'pipeline_stage_id' => $lead->pipeline_stage_id,
            'user_id' => $request->user()->id,
            'entered_at' => now(),
        ]);

        return to_route('crm.leads.show', $lead, 303);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_sources'))],
            'request_type' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('request_types'))],
            'quality' => ['nullable', 'string', 'max:100', Rule::in(CrmReferenceData::values('lead_qualities'))],
            'branch' => $this->branchRules(),
        ]);

        $lead->update($data);

        return to_route('crm.leads.show', $lead, 303);
    }

    public function destroy(Lead $lead): RedirectResponse
    {
        $lead->delete();

        return redirect()->route('crm.leads.index');
    }

    public function upsertContact(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255',
        ]);

        $contact = $lead->contact ?? $this->findMatchingContact(
            $data['phone'] ?? null,
            $data['email'] ?? null,
        ) ?? new Contact();

        $contact->fill([
            'name' => $this->buildFullName($data['first_name'], $data['last_name'] ?? null),
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'source' => $lead->source,
            'branch' => $lead->branch,
            'meta' => $this->mergeMeta($contact->meta, [
                'first_name' => $data['first_name'],
                'last_name' => $data['last_name'] ?? null,
            ]),
        ]);
        $contact->save();

        $lead->update(['contact_id' => $contact->id]);

        $existingDeal = Deal::query()
            ->where('lead_id', $lead->id)
            ->latest('updated_at')
            ->first();

        if ($existingDeal) {
            return redirect()->route('crm.deals.show', $existingDeal);
        }

        $dealPipeline = Pipeline::query()
            ->with('stages')
            ->where('type', 'deals')
            ->first();

        $initialStage = $dealPipeline?->stages->first();

        if (! $dealPipeline || ! $initialStage) {
            return redirect()->route('crm.leads.show', $lead);
        }

        $deal = Deal::create([
            'pipeline_id' => $dealPipeline->id,
            'pipeline_stage_id' => $initialStage->id,
            'user_id' => $lead->user_id ?? $request->user()->id,
            'contact_id' => $contact->id,
            'lead_id' => $lead->id,
            'name' => $lead->name,
            'branch' => $lead->branch,
            'payment_status' => Deal::PAYMENT_UNPAID,
            'amount' => 0,
            'meta' => $this->mergeMeta(null, [
                'source' => $lead->source,
                'request_type' => $lead->request_type,
                'created_from_lead' => true,
            ]),
        ]);

        $deal->stageHistory()->create([
            'pipeline_stage_id' => $deal->pipeline_stage_id,
            'user_id' => $request->user()->id,
            'entered_at' => now(),
        ]);

        return redirect()->route('crm.deals.show', $deal);
    }

    public function attachDeal(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'deal_id' => 'nullable|exists:deals,id',
        ]);

        if (empty($data['deal_id'])) {
            return redirect()->route('crm.leads.show', $lead);
        }

        $deal = Deal::query()->findOrFail($data['deal_id']);

        $updates = ['lead_id' => $lead->id];

        if ($lead->contact_id && ! $deal->contact_id) {
            $updates['contact_id'] = $lead->contact_id;
        }

        $deal->update($updates);

        return to_route('crm.leads.show', $lead, 303);
    }

    public function storeTask(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => ['nullable', Rule::in(CrmReferenceData::values('task_types'))],
            'due_at' => 'nullable|date',
        ]);

        $lead->tasks()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'user_id' => $request->user()->id,
            'type' => $data['type'] ?? Task::TYPE_CALL,
            'status' => Task::STATUS_PENDING,
            'due_at' => $data['due_at'] ?? null,
        ]);

        return redirect()->route('crm.leads.show', $lead);
    }

    public function moveStage(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'stage_id' => 'required|exists:pipeline_stages,id',
        ]);

        $stage = PipelineStage::with('pipeline')->findOrFail($data['stage_id']);

        if ($stage->pipeline?->type !== 'leads') {
            return back()->withErrors([
                'stage_id' => 'Выбранный этап не относится к воронке лидов.',
            ]);
        }

        CrmStageRequirements::assertLeadCanBePlacedInStage($stage, [], $lead, false, 'stage_id');

        DB::transaction(function () use ($lead, $stage, $request): void {
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

        return redirect()->route('crm.leads.show', $lead);
    }

    private function branchRules(): array
    {
        $branches = CrmReferenceData::configuredBranchValues();

        return $branches === []
            ? ['nullable', 'string', 'max:255']
            : ['nullable', 'string', 'max:255', Rule::in($branches)];
    }

    private function serializeLeadCard(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'source' => CrmReferenceData::label('lead_sources', $lead->source, $lead->source),
            'source_value' => $lead->source,
            'request_type' => CrmReferenceData::label('request_types', $lead->request_type, $lead->request_type),
            'request_type_value' => $lead->request_type,
            'branch' => CrmReferenceData::label('branches', $lead->branch, $lead->branch),
            'branch_value' => $lead->branch,
            'tasks_count' => $lead->tasks_count,
            'updated_at' => $lead->updated_at?->locale('ru')->diffForHumans(),
            'contact' => $lead->contact ? [
                'name' => $lead->contact->name,
                'phone' => $lead->contact->phone,
                'email' => $lead->contact->email,
            ] : null,
            'stage' => $lead->stage ? [
                'name' => $lead->stage->name,
                'color' => $lead->stage->color,
                'is_final' => (bool) $lead->stage->is_final,
                'is_fail' => (bool) $lead->stage->is_fail,
            ] : null,
            'user' => $lead->user?->name,
        ];
    }

    private function serializeLeadDetail(Lead $lead): array
    {
        $contactNames = $this->splitContactName($lead->contact);

        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'source' => CrmReferenceData::label('lead_sources', $lead->source, $lead->source),
            'source_value' => $lead->source,
            'request_type' => CrmReferenceData::label('request_types', $lead->request_type, $lead->request_type),
            'request_type_value' => $lead->request_type,
            'branch' => CrmReferenceData::label('branches', $lead->branch, $lead->branch),
            'branch_value' => $lead->branch,
            'quality' => CrmReferenceData::label('lead_qualities', $lead->quality, $lead->quality),
            'quality_value' => $lead->quality,
            'created_at' => $lead->created_at?->format('d.m.Y H:i'),
            'updated_at' => $lead->updated_at?->format('d.m.Y H:i'),
            'meta' => $lead->meta ?? [],
            'contact' => $lead->contact ? [
                'id' => $lead->contact->id,
                'name' => $lead->contact->name,
                'first_name' => $contactNames['first_name'],
                'last_name' => $contactNames['last_name'],
                'phone' => $lead->contact->phone,
                'email' => $lead->contact->email,
                'telegram_chat_id' => $lead->contact->telegram_chat_id,
                'instagram_id' => $lead->contact->instagram_id,
            ] : null,
            'stage' => $lead->stage ? [
                'id' => $lead->stage->id,
                'name' => $lead->stage->name,
                'color' => $lead->stage->color,
                'is_final' => (bool) $lead->stage->is_final,
                'is_fail' => (bool) $lead->stage->is_fail,
            ] : null,
            'user' => $lead->user?->name,
            'tasks' => $lead->tasks
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
            'history' => $lead->stageHistory
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

    private function serializeRelatedDeal(Deal $deal): array
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
            'tasks_count' => $deal->tasks_count,
            'contact' => $deal->contact ? [
                'name' => $deal->contact->name,
                'phone' => $deal->contact->phone,
            ] : null,
            'stage' => $deal->stage ? [
                'name' => $deal->stage->name,
                'color' => $deal->stage->color,
                'is_final' => (bool) $deal->stage->is_final,
                'is_fail' => (bool) $deal->stage->is_fail,
            ] : null,
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
}
