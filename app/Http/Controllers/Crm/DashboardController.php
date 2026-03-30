<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Pipeline;
use App\Models\Task;
use App\Support\CrmReferenceData;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $leadPipeline = Pipeline::query()->with('stages')->where('type', 'leads')->first();
        $dealPipeline = Pipeline::query()->with('stages')->where('type', 'deals')->first();

        $recentLeads = Lead::query()
            ->with(['stage', 'contact'])
            ->withCount('tasks')
            ->latest()
            ->limit(4)
            ->get();

        $upcomingDeals = Deal::query()
            ->with(['stage', 'contact', 'analyses'])
            ->withCount('tasks')
            ->whereNotNull('appointment_at')
            ->where('appointment_at', '>=', now()->startOfDay())
            ->orderBy('appointment_at')
            ->limit(2)
            ->get();

        $overdueTasks = Task::query()
            ->with('user')
            ->where('status', Task::STATUS_PENDING)
            ->whereNotNull('due_at')
            ->where('due_at', '<', now())
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => [
                'lead_count' => Lead::query()->count(),
                'deal_count' => Deal::query()->count(),
                'contact_count' => Contact::query()->count(),
                'pending_task_count' => Task::query()->where('status', Task::STATUS_PENDING)->count(),
                'scheduled_deal_count' => Deal::query()->whereNotNull('appointment_at')->count(),
                'paid_deal_count' => Deal::query()->where('payment_status', Deal::PAYMENT_PAID)->count(),
            ],
            'pipelines' => [
                'leads' => $this->buildPipelineSnapshot($leadPipeline, Lead::class),
                'deals' => $this->buildPipelineSnapshot($dealPipeline, Deal::class),
            ],
            'recentLeads' => $recentLeads->map(fn (Lead $lead) => $this->serializeLeadCard($lead))->values(),
            'upcomingDeals' => $upcomingDeals->map(fn (Deal $deal) => $this->serializeDealCard($deal))->values(),
            'overdueTasks' => $overdueTasks->map(fn (Task $task) => $this->serializeTask($task))->values(),
        ]);
    }

    private function buildPipelineSnapshot(?Pipeline $pipeline, string $modelClass): ?array
    {
        if (! $pipeline) {
            return null;
        }

        $counts = $modelClass::query()
            ->where('pipeline_id', $pipeline->id)
            ->selectRaw('pipeline_stage_id, COUNT(*) as aggregate')
            ->groupBy('pipeline_stage_id')
            ->pluck('aggregate', 'pipeline_stage_id');

        return [
            'id' => $pipeline->id,
            'name' => $pipeline->name,
            'stages' => $pipeline->stages->map(function ($stage) use ($counts) {
                return [
                    'id' => $stage->id,
                    'name' => $stage->name,
                    'color' => $stage->color,
                    'is_final' => $stage->is_final,
                    'is_fail' => $stage->is_fail,
                    'count' => (int) ($counts[$stage->id] ?? 0),
                ];
            })->values(),
        ];
    }

    private function serializeLeadCard(Lead $lead): array
    {
        return [
            'id' => $lead->id,
            'name' => $lead->name,
            'phone' => $lead->phone,
            'source' => CrmReferenceData::label('lead_sources', $lead->source, $lead->source),
            'request_type' => CrmReferenceData::label('request_types', $lead->request_type, $lead->request_type),
            'branch' => CrmReferenceData::label('branches', $lead->branch, $lead->branch),
            'tasks_count' => $lead->tasks_count,
            'updated_at' => optional($lead->updated_at)?->diffForHumans(),
            'contact' => $lead->contact ? [
                'name' => $lead->contact->name,
                'phone' => $lead->contact->phone,
            ] : null,
            'stage' => $lead->stage ? [
                'name' => $lead->stage->name,
                'color' => $lead->stage->color,
                'is_final' => $lead->stage->is_final,
                'is_fail' => $lead->stage->is_fail,
            ] : null,
        ];
    }

    private function serializeDealCard(Deal $deal): array
    {
        return [
            'id' => $deal->id,
            'name' => $deal->name,
            'branch' => CrmReferenceData::label('branches', $deal->branch, $deal->branch),
            'payment_status' => CrmReferenceData::label('payment_statuses', $deal->payment_status, $deal->payment_status),
            'appointment_at' => optional($deal->appointment_at)?->format('d.m.Y H:i'),
            'appointment_relative' => optional($deal->appointment_at)?->diffForHumans(),
            'tasks_count' => $deal->tasks_count,
            'analyses_count' => $deal->analyses->count(),
            'contact' => $deal->contact ? [
                'name' => $deal->contact->name,
                'phone' => $deal->contact->phone,
            ] : null,
            'stage' => $deal->stage ? [
                'name' => $deal->stage->name,
                'color' => $deal->stage->color,
                'is_final' => $deal->stage->is_final,
                'is_fail' => $deal->stage->is_fail,
            ] : null,
        ];
    }

    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->id,
            'title' => $task->title,
            'type' => $task->type,
            'status' => $task->status,
            'due_at' => optional($task->due_at)?->format('d.m.Y H:i'),
            'due_relative' => optional($task->due_at)?->diffForHumans(),
            'user' => $task->user?->name,
        ];
    }
}
