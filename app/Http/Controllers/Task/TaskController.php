<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\TaskUserAssign;
use App\Models\IndvidualTask;
use App\Models\User;

class TaskController extends Controller
{
    //create task
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string',
            'description' => 'nullable|string',
            'priority' => 'required|integer|in:1,2,3',
            'due_date' => 'nullable|date',
            'created_user_id' => 'required|integer|exists:users,id',
            'assignments' => 'nullable|array',
            'assignments.*.user_id' => 'required_with:assignments|integer|exists:users,id',
            'assignments.*.due_date' => 'nullable|date',
        ]);

        DB::beginTransaction();

        try {
            // Step 1: Create the task
            $task = Task::create([
                'title' => $request->title,
                'description' => $request->description,
                'priority' => $request->priority,
                'status' => 'pending',
                'due_date' => $request->due_date,
                'created_user_id' => $request->created_user_id,
            ]);

            // Step 2: Assign users
            if ($request->has('assignments')) {
                foreach ($request->assignments as $assign) {
                    TaskUserAssign::create([
                        'task_id' => $task->id,
                        'user_id' => $assign['user_id'],
                        'status' => 'pending',
                        'due_date' => $assign['due_date'] ?? $request->due_date,
                        'feedback' => null,
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'status' => 201,
                'message' => 'Task created and users assigned successfully',
                'data' => $task
            ], 201);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'success' => false,
                'status' => 500,
                'message' => 'Task creation failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // assign task to user
    public function assignTask(Request $request)
    {
        $request->validate([
            'title' => 'nullable|string',
            'description' => 'nullable|string',
            'task_id' => 'required|integer|exists:tasks,id',
            'user_id' => 'required|integer|exists:users,id',
            'task_user_assigns_id' => 'required|integer|exists:task_user_assigns,id',
        ]);

        $individualTask = IndvidualTask::create([
            'title' => $request->title,
            'description' => $request->description,
            'task_id' => $request->task_id,
            'user_id' => $request->user_id,
            'task_user_assigns_id' => $request->task_user_assigns_id,
            'status' => 'pending',
            'checkbox' => 0,
        ]);

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'Individual task assigned successfully',
            'data' => $individualTask
        ], 201);
    }

    // get all tasks
    public function index(Request $request)
    {
        $query = Task::with(['creator:id,name,email,phone,type,profile_image']);

        // Filter by created_user_id
        if ($request->has('created_user_id')) {
            $query->where('created_user_id', $request->created_user_id);
        }

        // Search by title
        if ($request->has('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by due_date
        if ($request->has('due_date')) {
            $query->whereDate('due_date', $request->due_date);
        }

        // Order (default: latest first)
        $query->orderBy('created_at', 'desc');

        $tasks = $query->get();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Task list fetched successfully',
            'data' => $tasks
        ]);
    }

    // get task by id
    public function show($id)
    {
        $task = Task::with([
            'creator:id,name,email,phone,type,profile_image',
            'assignedUsers.user:id,name,email,phone,type,profile_image',
            'assignedUsers.individualTasks'
        ])->find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Task not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Task details fetched successfully',
            'data' => $task
        ]);
    }

    // update task status
    public function updateTaskStatus(Request $request, $id)
    {
        $request->validate([
            'status' => 'required|in:pending,in_progress,completed',
        ]);

        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Task not found',
            ], 404);
        }

        $task->status = $request->status;
        $task->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Task status updated successfully',
            'data' => $task
        ]);
    }

    // update task
    public function update(Request $request, $id)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'priority' => 'nullable|in:1,2,3',
            'status' => 'nullable|in:pending,in_progress,completed',
            'due_date' => 'nullable|date',
        ]);

        $task = Task::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Task not found'
            ], 404);
        }

        $task->update([
            'title' => $request->title,
            'description' => $request->description,
            'priority' => $request->priority,
            'status' => $request->status,
            'due_date' => $request->due_date,
        ]);

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Task updated successfully',
            'data' => $task
        ]);
    }

    // assign user to task
    public function assignUser(Request $request, $task_id)
    {
        $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'due_date' => 'nullable|date',
        ]);

        $task = Task::find($task_id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Task not found'
            ], 404);
        }

        // Check if user already assigned
        $existing = TaskUserAssign::where('task_id', $task_id)
            ->where('user_id', $request->user_id)
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'status' => 409,
                'message' => 'User already assigned to this task'
            ], 409);
        }

        $assignment = TaskUserAssign::create([
            'task_id' => $task_id,
            'user_id' => $request->user_id,
            'due_date' => $request->due_date,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'status' => 201,
            'message' => 'User assigned to task successfully',
            'data' => $assignment
        ]);
    }

    // update task user assignment table
    public function updateTaskUserAssign(Request $request, $id)
    {
        $request->validate([
            'status' => 'nullable|in:pending,in_progress,completed',
            'due_date' => 'nullable|date',
        ]);

        $assign = TaskUserAssign::find($id);

        if (!$assign) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Assigned task not found'
            ], 404);
        }

        if ($request->has('status')) {
            $assign->status = $request->status;
        }

        if ($request->has('due_date')) {
            $assign->due_date = $request->due_date;
        }

        $assign->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Status and/or due date updated successfully',
            'data' => $assign
        ]);
    }

    // update individual task
    public function updateIndividualTask(Request $request, $id)
    {
        $request->validate([
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|in:pending,completed'
        ]);

        $task = IndvidualTask::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Individual task not found'
            ], 404);
        }

        if ($request->has('title')) {
            $task->title = $request->title;
        }

        if ($request->has('description')) {
            $task->description = $request->description;
        }

        if ($request->has('status')) {
            $task->status = $request->status;
        }

        $task->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Individual task updated successfully',
            'data' => $task
        ]);
    }

    // toggle checkbox in individual task
    public function toggleCheckbox($id)
    {
        $task = IndvidualTask::find($id);

        if (!$task) {
            return response()->json([
                'success' => false,
                'status' => 404,
                'message' => 'Individual task not found'
            ], 404);
        }

        $task->checkbox = $task->checkbox === 1 ? 0 : 1;
        $task->save();

        return response()->json([
            'success' => true,
            'status' => 200,
            'message' => 'Checkbox status toggled successfully',
            'data' => $task
        ]);
    }
}
