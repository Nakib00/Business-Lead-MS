<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    use ApiResponseTrait;

    public function storeForProject(Request $request, Project $project)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Validate main fields
            $validated = $request->validate([
                'task_name'   => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'due_date'    => ['nullable', 'date'],
                'priority'    => ['required', Rule::in(['low', 'medium', 'high'])],
                'category'    => ['nullable'], // string or array
                'user_id'     => ['nullable', 'integer', 'exists:users,id'],
                'user_ids'    => ['nullable', 'array', 'min:1'],
                'user_ids.*'  => ['integer', 'exists:users,id'],
            ]);

            // Normalize category (CSV)
            $category = null;
            if ($request->has('category')) {
                $cat = $request->input('category');
                if (is_array($cat)) {
                    $cat = implode(',', array_map(fn($v) => trim((string)$v), $cat));
                } else {
                    $cat = collect(explode(',', (string)$cat))
                        ->map(fn($v) => trim($v))
                        ->filter()
                        ->implode(',');
                }
                $category = $cat ?: null;
            }

            // Create task
            $task = Task::create([
                'project_id'  => $project->id,
                'task_name'   => $validated['task_name'],
                'description' => $validated['description'] ?? null,
                'status'      => 0, // pending
                'due_date'    => $validated['due_date'] ?? null,
                'priority'    => $validated['priority'],
                'category'    => $category,
            ]);

            // Build list of user IDs to assign (optional)
            $ids = [];
            if ($request->filled('user_ids')) {
                $ids = is_array($request->user_ids) ? $request->user_ids : [$request->user_ids];
            }
            if ($request->filled('user_id')) {
                $ids[] = (int) $request->user_id;
            }
            if (!empty($ids)) {
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $task->users()->syncWithoutDetaching($ids);
            }

            $task->load(['project', 'users']);

            return $this->successResponse($task, 'Task created', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create task', $e->getMessage());
        }
    }

    public function assignUsers(Request $request, Project $project, Task $task)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Ensure the task belongs to the project from the URL
            if ($task->project_id !== $project->id) {
                return $this->notFoundResponse('Task does not belong to this project');
            }

            // Normalize/validate payload
            $mode = $request->input('mode', 'attach');
            $request->validate([
                'mode'       => ['nullable', Rule::in(['attach', 'sync'])],
                'user_id'    => ['nullable', 'integer', 'exists:users,id'],
                'user_ids'   => ['nullable', 'array', 'min:1'],
                'user_ids.*' => ['integer', 'exists:users,id'],
            ]);

            $ids = [];
            if ($request->filled('user_ids')) {
                $ids = is_array($request->user_ids) ? $request->user_ids : [$request->user_ids];
            } elseif ($request->filled('user_id')) {
                $ids = [(int) $request->user_id];
            }

            if (empty($ids)) {
                return $this->validationErrorResponse(['At least one user_id is required.']);
            }

            $ids = array_values(array_unique(array_map('intval', $ids)));

            if ($mode === 'sync') {
                $task->users()->sync($ids);
            } else {
                $task->users()->syncWithoutDetaching($ids);
            }

            return $this->successResponse($task->fresh('users'), 'Users assigned to task');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
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

            $task->users()->detach($user->id);

            return $this->successResponse($task->fresh('users'), 'User removed from task');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to remove user from task', $e->getMessage());
        }
    }

    /**
     * DELETE /projects/{project}/tasks/{task}
     * Deletes the task and removes assigned users.
     */
    public function destroy(Request $request, Project $project, Task $task)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Ensure the task belongs to the project from the URL
            if ($task->project_id !== $project->id) {
                return $this->notFoundResponse('Task does not belong to this project');
            }

            DB::transaction(function () use ($task) {
                // Detach users (extra safe even if FK has ON DELETE CASCADE)
                $task->users()->detach();

                // Delete the task (will cascade delete pivot rows if FK is set that way)
                $task->delete();
            });

            return $this->successResponse(null, 'Task deleted');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to delete task', $e->getMessage());
        }
    }

    public function indexSummary(Request $request)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Validate/normalize query params
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

            $limit = (int) $request->query('limit', 5); // default 5
            $page  = (int) $request->query('page', 1);  // default 1

            $query = Task::query()
                ->select(['id', 'project_id', 'task_name', 'status', 'priority', 'category', 'due_date'])
                ->with([
                    // Only need id & profile_image for assigned user avatars
                    'users:id,profile_image'
                ])
                ->orderByDesc('id'); // descending

            // Filters
            if ($pid = $request->query('project_id')) {
                $query->where('project_id', (int) $pid);
            }

            if ($s = $request->query('search')) {
                $query->where(function ($q) use ($s) {
                    $q->where('task_name', 'like', "%$s%")
                        ->orWhere('description', 'like', "%$s%")
                        ->orWhere('category', 'like', "%$s%");
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

            // Paginate with custom page/limit
            $paginator = $query->paginate($limit, ['*'], 'page', $page);

            // Transform response shape
            $data = $paginator->getCollection()->map(function (Task $task) {
                return [
                    'id'           => $task->id,
                    'project_id'   => $task->project_id,
                    'task_name'    => $task->task_name,
                    'status'       => $task->status,     // 0..3
                    'priority'     => $task->priority,   // low|medium|high
                    'category'     => $task->category,   // CSV
                    'due_date'     => optional($task->due_date)->format('Y-m-d'),
                    'assigned_user_images' => $task->users->map(function ($u) {
                        if (!$u->profile_image) return null;
                        // If already full URL, keep it; else generate via Storage::url()
                        return Str::startsWith($u->profile_image, ['http://', 'https://'])
                            ? $u->profile_image
                            : Storage::url($u->profile_image);
                    })->filter()->values()->all(),
                ];
            })->values();

            return $this->paginatedResponse($data, $paginator, 'Tasks fetched');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch tasks', $e->getMessage());
        }
    }

    /**
     * GET /tasks/{task}
     * Returns one task with all fields + assigned users (name & profile_image).
     */
    public function show(Request $request, Task $task)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Eager-load users (id, name, profile_image) and (optionally) project
            $task->loadMissing([
                'users:id,name,profile_image',
                'project:id,project_name' // optional, remove if not needed
            ]);

            $assignedUsers = $task->users->map(function ($u) {
                return [
                    'id'    => $u->id,
                    'name'  => $u->name,
                    'profile_image' => $u->profile_image
                        ? (Str::startsWith($u->profile_image, ['http://', 'https://'])
                            ? $u->profile_image
                            : Storage::url($u->profile_image))
                        : null,
                ];
            })->values()->all();

            $data = [
                'id'          => $task->id,
                'project_id'  => $task->project_id,
                'task_name'   => $task->task_name,
                'description' => $task->description,
                'status'      => $task->status,    // 0=pending,1=in_progress,2=done,3=blocked
                'priority'    => $task->priority,  // low|medium|high
                'category'    => $task->category,  // CSV as stored
                'due_date'    => optional($task->due_date)->format('Y-m-d'),
                'assigned_users' => $assignedUsers,

                // Optional convenience:
                'project' => $task->relationLoaded('project') ? $task->project : null,
                'created_at' => optional($task->created_at)->toDateTimeString(),
                'updated_at' => optional($task->updated_at)->toDateTimeString(),
            ];

            return $this->successResponse($data, 'Task fetched');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch task', $e->getMessage());
        }
    }
}
