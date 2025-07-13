<?php

namespace App\Http\Controllers;

use App\Http\Requests\TaskStoreRequest;
use App\Http\Requests\TaskUpdateRequest;
use App\Models\Task;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class TaskController extends Controller
{
    use AuthorizesRequests;

    function index(Request $request): Collection
    {
        return Task::where('user_id', Auth::user()->id)->get();
    }

    function store(TaskStoreRequest $request): Task
    {
        $task = new Task($request->validated());

        $task->user_id = Auth::user()->id;
        $task->save();

        return $task;
    }

    function show(Task $task): Task
    {
        $this->authorize('view', $task);

        return $task;
    }

    function update(TaskUpdateRequest $request, Task $task): Task
    {
        $this->authorize('update', $task);

        $task->update($request->validated());

        return $task;
    }

    function destroy(Task $task): JsonResponse
    {
        $this->authorize('delete', $task);

        $task->delete();

        return response()->json(['message' => 'Task deleted successfully'], 200);
    }
}
