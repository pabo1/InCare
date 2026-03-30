<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Deal;
use App\Models\Lead;
use App\Models\Task;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $tasks = Task::with(['user', 'taskable'])
            ->when($request->user_id, fn($q, $v) => $q->where('user_id', $v))
            ->when($request->status, fn($q, $v) => $q->where('status', $v))
            ->latest('due_at')
            ->paginate(50);

        return response()->json($tasks);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title'         => 'required|string|max:255',
            'description'   => 'nullable|string',
            'taskable_type' => 'required|in:lead,deal',
            'taskable_id'   => 'required|integer',
            'user_id'       => 'nullable|exists:users,id',
            'type'          => 'nullable|in:call,reactivation,remind',
            'due_at'        => 'nullable|date',
        ]);

        $taskableType = $data['taskable_type'] === 'lead' ? Lead::class : Deal::class;
        $taskableType::findOrFail($data['taskable_id']);

        $task = Task::create([
            'title'         => $data['title'],
            'description'   => $data['description'] ?? null,
            'taskable_type' => $taskableType,
            'taskable_id'   => $data['taskable_id'],
            'user_id'       => $data['user_id'] ?? $request->user()->id,
            'type'          => $data['type'] ?? Task::TYPE_CALL,
            'status'        => Task::STATUS_PENDING,
            'due_at'        => $data['due_at'] ?? null,
        ]);

        return response()->json($task->load('user'), 201);
    }

    public function update(Request $request, Task $task)
    {
        $data = $request->validate([
            'title'       => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status'      => 'sometimes|in:pending,done,cancelled',
            'user_id'     => 'nullable|exists:users,id',
            'type'        => 'nullable|in:call,reactivation,remind',
            'due_at'      => 'nullable|date',
        ]);

        $task->update($data);

        return response()->json($task->load('user'));
    }

    public function destroy(Task $task)
    {
        $task->delete();

        return response()->json(null, 204);
    }
}