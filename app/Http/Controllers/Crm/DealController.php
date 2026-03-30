<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\Task;
use App\Support\CrmReferenceData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
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
                    'update_url' => route('crm.pipeline-stages.update', $stage),
                ])->values()
                : [],
            'referenceData' => [
                'branches' => CrmReferenceData::branchOptions(),
                'paymentStatuses' => CrmReferenceData::options('payment_statuses'),
            ],
        ]);
    }

    public function update(Request $request, Deal $deal): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'branch' => $this->branchRules(),
            'appointment_at' => 'nullable|date',
            'payment_status' => ['nullable', 'string', Rule::in(array_column(CrmReferenceData::options('payment_statuses'), 'value'))],
            'cancel_reason' => 'nullable|string|max:255',
            'amount' => 'nullable|numeric',
        ]);

        $deal->update($data);

        return redirect()->route('crm.deals.show', $deal);
    }

    private function branchRules(): array
    {
        $branches = array_column(CrmReferenceData::branchOptions(), 'value');

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
}
