<?php

namespace App\Http\Controllers\Task;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\Task;
use App\Traits\ApiResponseTrait;
use Illuminate\Validation\Rule;

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
                'priority'    => ['required', Rule::in(['low','medium','high'])],
                'category'    => ['nullable'], // string or array
                'user_id'     => ['nullable','integer','exists:users,id'],
                'user_ids'    => ['nullable','array','min:1'],
                'user_ids.*'  => ['integer','exists:users,id'],
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

            $task->load(['project','users']);

            return $this->successResponse($task, 'Task created', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create task', $e->getMessage());
        }
    }
}
