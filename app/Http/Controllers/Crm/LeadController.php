<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Task;
use App\Models\PipelineStage;
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
                'branches' => CrmReferenceData::branchOptions(),
            ],
            'relatedDeals' => $relatedDeals->map(fn (Deal $deal) => $this->serializeRelatedDeal($deal))->values(),
        ]);
    }

    public function update(Request $request, Lead $lead): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:50',
            'source' => ['nullable', 'string', 'max:100', Rule::in(array_column(CrmReferenceData::options('lead_sources'), 'value'))],
            'request_type' => ['nullable', 'string', 'max:100', Rule::in(array_column(CrmReferenceData::options('request_types'), 'value'))],
            'branch' => $this->branchRules(),
        ]);

        $lead->update($data);

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
        $branches = array_column(CrmReferenceData::branchOptions(), 'value');

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
            'quality' => $lead->quality,
            'created_at' => $lead->created_at?->format('d.m.Y H:i'),
            'updated_at' => $lead->updated_at?->format('d.m.Y H:i'),
            'meta' => $lead->meta ?? [],
            'contact' => $lead->contact ? [
                'id' => $lead->contact->id,
                'name' => $lead->contact->name,
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
}
