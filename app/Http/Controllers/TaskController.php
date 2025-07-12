<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    function index(Request $request): Collection
    {
        return Task::all();
    }

    function store(TaskRequest $request): Task
    {
        $task = new Task($request->validated());

        $task->user_id = Auth::user()->id;
        $task->save();

        return $task;
    }

    function show($id): Task
    {
        $task = Task::findOrFail($id);

        $this->authorize('view', $task);

        return $task;
    }

    function update(TaskRequest $request): Task
    {
        $task = Task::findOrFail($request->id);

        $this->authorize('update', $task);

        $task->update($request->validated());

        return $task;
    }

    function destroy($id): JsonResponse
    {
        $task = Task::findOrFail($id);

        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully'], 200);
    }
}
