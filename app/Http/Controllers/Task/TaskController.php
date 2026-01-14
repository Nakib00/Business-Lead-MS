<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Task\AssignTaskUsersRequest;
use App\Http\Requests\Task\UpdateTaskDetailsRequest;

class TaskController extends Controller
{
    use ApiResponseTrait;

    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function storeForProject(StoreTaskRequest $request, Project $project)
    {
        try {
            $task = $this->taskService->createTask(
                $project,
                $request->validated(),
                $request->user()
            );

            return $this->successResponse($task, 'Task created', 201);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create task', $e->getMessage());
        }
    }

    public function assignUsers(AssignTaskUsersRequest $request, Project $project, Task $task)
    {
        try {
            if ($task->project_id !== $project->id) {
                return $this->notFoundResponse('Task does not belong to this project');
            }

            $mode = $request->input('mode', 'attach');
            $userIds = $request->input('user_ids', []);

            // If user_id (singular) is used
            if ($request->has('user_id')) {
                $userIds[] = $request->input('user_id');
            }

            if (empty($userIds) && empty($request->input('user_ids'))) {
                // The Form Request validation "min:1" for array might catch this, 
                // but if they mix singular and plural it needs care.
                // The Request validates user_ids or user_id properly?
                // Let's re-verify the request logic logic in controller often handled this manually.
                // The Service handles dedup. Validator handles existence.
            }

            $task = $this->taskService->assignUsers($task, $userIds, $mode);

            return $this->successResponse($task, 'Users assigned to task');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to assign users to task', $e->getMessage());
        }
    }

    public function removeUser(Request $request, Project $project, Task $task, User $user)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            if ($task->project_id !== $project->id) {
                return $this->notFoundResponse('Task does not belong to this project');
            }

            $task = $this->taskService->removeUser($task, $user);

            return $this->successResponse($task, 'User removed from task');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to remove user from task', $e->getMessage());
        }
    }

    public function destroy(Request $request, Project $project, Task $task)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            if ($task->project_id !== $project->id) {
                return $this->notFoundResponse('Task does not belong to this project');
            }

            $this->taskService->deleteTask($task);

            return $this->successResponse(null, 'Task deleted');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to delete task', $e->getMessage());
        }
    }

    public function updateStatus(Request $request, Task $task)
    {
        try {
            $validated = $request->validate([
                'status' => ['required', 'integer', 'between:0,3'],
            ]);

            $task = $this->taskService->updateTask($task, $validated);

            return $this->successResponse($task, 'Task status updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update task status', $e->getMessage());
        }
    }

    public function updatePriority(Request $request, Task $task)
    {
        try {
            $validated = $request->validate([
                'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            ]);

            $task = $this->taskService->updateTask($task, $validated);

            return $this->successResponse($task, 'Task priority updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update task priority', $e->getMessage());
        }
    }

    public function updateCategory(Request $request, Task $task)
    {
        try {
            $validated = $request->validate([
                'category' => ['nullable'],
            ]);

            // Normalization is handled in Service now
            $task = $this->taskService->updateTask($task, $validated);

            return $this->successResponse($task, 'Task category updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update task category', $e->getMessage());
        }
    }

    public function updateDetails(UpdateTaskDetailsRequest $request, Task $task)
    {
        try {
            // Service handles generic update + normalization
            $task = $this->taskService->updateTask($task, $request->validated());

            // "No changes" response logic
            if ($task->wasChanged()) {
                return $this->successResponse($task, 'Task updated');
            }
            return $this->successResponse($task, 'No changes'); // Or 'Task updated' to behave identically if desired

        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update task', $e->getMessage());
        }
    }

    /**
     * GET /tasks
     * Kept generic filtering logic here.
     */
    public function indexSummary(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->unauthorizedResponse('Login required');
            }

            $effectiveAdminId = $user->reg_user_id ?: $user->id;

            $request->validate([
                'limit'      => ['nullable', 'integer', 'min:1', 'max:100'],
                'page'       => ['nullable', 'integer', 'min:1'],
                'search'     => ['nullable', 'string', 'max:255'],
                'project_id' => ['nullable', 'integer', 'exists:projects,id'],
                'status'     => ['nullable', 'integer', 'between:0,3'],
                'priority'   => ['nullable', Rule::in(['low', 'medium', 'high'])],
                'due_date'   => ['nullable', 'date'],
                'due_before' => ['nullable', 'date'],
                'due_after'  => ['nullable', 'date'],
            ]);

            $limit = (int) $request->query('limit', 5);
            $page  = (int) $request->query('page', 1);

            $query = Task::query()
                ->select(['id', 'project_id', 'task_name', 'status', 'priority', 'category', 'due_date'])
                ->whereHas('project', function ($q) use ($effectiveAdminId) {
                    $q->where('admin_id', $effectiveAdminId);
                })
                ->with([
                    'users:id,name,profile_image',
                ])
                ->orderByDesc('id');

            if ($pid = $request->query('project_id')) {
                $query->where('project_id', (int) $pid);
            }

            if ($s = $request->query('search')) {
                $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
                $query->where(function ($q) use ($like) {
                    $q->where('task_name', 'like', $like)
                        ->orWhere('description', 'like', $like)
                        ->orWhere('category', 'like', $like);
                });
            }

            if (!is_null($request->query('status'))) {
                $query->where('status', (int) $request->query('status'));
            }

            if ($priority = $request->query('priority')) {
                $query->where('priority', $priority);
            }

            if ($dueExact = $request->query('due_date')) {
                $query->whereDate('due_date', '=', $dueExact);
            }

            if ($dueBefore = $request->query('due_before')) {
                $query->whereDate('due_date', '<=', $dueBefore);
            }

            if ($dueAfter = $request->query('due_after')) {
                $query->whereDate('due_date', '>=', $dueAfter);
            }

            $paginator = $query->paginate($limit, ['*'], 'page', $page);

            $data = $paginator->getCollection()->map(function (Task $task) {
                return [
                    'id'            => $task->id,
                    'project_id'    => $task->project_id,
                    'task_name'     => $task->task_name,
                    'status'        => (int) $task->status,
                    'priority'      => $task->priority,
                    'category'      => $task->category,
                    'due_date'      => optional($task->due_date)->format('Y-m-d'),
                    'assigned_users' => $task->users->map(function ($u) {
                        return [
                            'id'                => $u->id,
                            'name'              => $u->name,
                            'profile_image_url' => filter_var($u->profile_image, FILTER_VALIDATE_URL)
                                ? $u->profile_image
                                : $u->profile_image_url,
                        ];
                    })->values()->all(),
                ];
            })->values();

            return $this->paginatedResponse($data, $paginator, 'Tasks fetched');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch tasks', $e->getMessage());
        }
    }

    public function show(Request $request, Task $task)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->unauthorizedResponse('Login required');
            }

            $effectiveAdminId = $user->reg_user_id ?: $user->id;

            $task->loadMissing([
                'project:id,admin_id,project_name',
                'users:id,name,profile_image',
            ]);

            if (!$task->project || (int)$task->project->admin_id !== (int)$effectiveAdminId) {
                return $this->notFoundResponse('Task not found');
            }

            $assignedUsers = $task->users->map(function ($u) {
                return [
                    'id'                => $u->id,
                    'name'              => $u->name,
                    'profile_image_url' => filter_var($u->profile_image, FILTER_VALIDATE_URL)
                        ? $u->profile_image
                        : $u->profile_image_url,
                ];
            })->values()->all();

            $data = [
                'id'            => $task->id,
                'project_id'    => $task->project_id,
                'task_name'     => $task->task_name,
                'description'   => $task->description,
                'status'        => (int) $task->status,
                'priority'      => $task->priority,
                'category'      => $task->category,
                'due_date'      => optional($task->due_date)->format('Y-m-d'),
                'assigned_users' => $assignedUsers,
                'project'       => $task->relationLoaded('project')
                    ? [
                        'id'          => $task->project->id,
                        'project_name' => $task->project->project_name,
                        'admin_id'    => (int) $task->project->admin_id,
                    ]
                    : null,
                'created_at'    => optional($task->created_at)->toDateTimeString(),
                'updated_at'    => optional($task->updated_at)->toDateTimeString(),
            ];

            return $this->successResponse($data, 'Task fetched');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch task', $e->getMessage());
        }
    }
}
