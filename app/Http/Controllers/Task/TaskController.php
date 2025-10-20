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

            // Accept category as string or array
            $validated = $request->validate([
                'task_name'   => ['required', 'string', 'max:255'],
                'description' => ['nullable', 'string'],
                'due_date'    => ['nullable', 'date'],
                'priority'    => ['required', Rule::in(['low', 'medium', 'high'])],
                'category'    => ['nullable'], // handle normalization below
            ]);

            // Normalize category to CSV
            $category = null;
            if ($request->has('category')) {
                $cat = $request->input('category');
                if (is_array($cat)) {
                    $cat = implode(',', array_map(fn($v) => trim((string)$v), $cat));
                } else {
                    // string: normalize spacing around commas
                    $cat = collect(explode(',', (string)$cat))
                        ->map(fn($v) => trim($v))
                        ->filter()
                        ->implode(',');
                }
                $category = $cat ?: null;
            }

            $task = Task::create([
                'project_id'  => $project->id,
                'task_name'   => $validated['task_name'],
                'description' => $validated['description'] ?? null,
                'status'      => 0, // default: pending
                'due_date'    => $validated['due_date'] ?? null,
                'priority'    => $validated['priority'],
                'category'    => $category,
            ]);

            // Return with project info for convenience
            $task->load('project');

            return $this->successResponse($task, 'Task created', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create task', $e->getMessage());
        }
    }
}
