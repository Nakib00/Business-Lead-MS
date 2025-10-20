<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\User;

class ProjectController extends Controller
{
    use ApiResponseTrait;
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // Normalize user_ids input (accept single int or array)
            $incomingUserIds = $request->input('user_ids');
            $userIds = is_null($incomingUserIds)
                ? []
                : (is_array($incomingUserIds) ? $incomingUserIds : [$incomingUserIds]);

            // Validate input (note: project_thumbnail is now a file)
            $validated = $request->validate([
                'project_name'        => ['required', 'string', 'max:255'],
                'client_name'         => ['required', 'string', 'max:255'],
                'project_description' => ['nullable', 'string'],
                'category'            => ['nullable', 'string', 'max:255'],
                'priority'            => ['required', Rule::in(['low', 'medium', 'high'])],
                'budget'              => ['nullable', 'numeric', 'min:0'],
                'due_date'            => ['nullable', 'date'],
                'project_thumbnail'   => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'], // 5 MB
                'user_ids'            => ['nullable'],
                'user_ids.*'          => ['integer', 'exists:users,id'],
            ]);

            $project = DB::transaction(function () use ($validated, $user, $userIds, $request) {
                // Handle thumbnail upload (if provided)
                $thumbnailPath = null;
                if ($request->hasFile('project_thumbnail')) {
                    $file = $request->file('project_thumbnail');
                    // e.g., projects/thumbnails/PRJ-XXXXXX_20251020_650f3a2f.png
                    $dir  = 'projects/thumbnails';
                    $name = sprintf(
                        '%s_%s.%s',
                        'PRJ-' . Str::upper(Str::random(6)),
                        now()->format('Ymd_His') . '_' . Str::random(8),
                        $file->getClientOriginalExtension()
                    );
                    // save to "public" disk (storage/app/public/…)
                    $thumbnailPath = $file->storeAs($dir, $name, 'public');
                }

                // Base payload for Project
                $payload = [
                    'project_code'        => 'PRJ-' . Str::upper(Str::random(6)),
                    'project_name'        => $validated['project_name'],
                    'client_name'         => $validated['client_name'],
                    'project_description' => $validated['project_description'] ?? null,
                    'priority'            => $validated['priority'],
                    'category'            => $validated['category'] ?? null,
                    'budget'              => $validated['budget'] ?? null,
                    'due_date'            => $validated['due_date'] ?? null,
                    'project_thumbnail'   => $thumbnailPath, // store RELATIVE PATH in DB
                    'status'              => 0,
                    'progress'            => 0,
                ];

                if (Schema::hasColumn('projects', 'created_by')) {
                    $payload['created_by'] = $user->id;
                }

                /** @var Project $project */
                $project = Project::create($payload);

                // Always include the creator in assignments
                if (!in_array($user->id, $userIds ?? [], true)) {
                    $userIds[] = $user->id;
                }
                $userIds = array_values(array_unique(array_map('intval', $userIds)));

                if (!empty($userIds)) {
                    $project->users()->syncWithoutDetaching($userIds);
                }

                return $project->fresh(['users']);
            });

            // Add a public URL in the response (from accessor below)
            $project->append('project_thumbnail_url');

            return $this->successResponse($project, 'Project created', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create project', $e->getMessage());
        }
    }

    /**
     * PATCH /projects/{project}/priority
     * Body: { "priority": "low" | "medium" | "high" }
     */
    public function updatePriority(Request $request, Project $project)
    {
        try {
            $user = $request->user();
            if (!$user) return $this->unauthorizedResponse('Login required');

            $data = $request->validate([
                'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            ]);

            $project->update(['priority' => $data['priority']]);

            return $this->successResponse($project->fresh(), 'Priority updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update priority', $e->getMessage());
        }
    }

    /**
     * PATCH /projects/{project}/status
     * Body: { "status": 0|1|2|3 }  // 0=pending,1=active,2=completed,3=on_hold
     */
    public function updateStatus(Request $request, Project $project)
    {
        try {
            $user = $request->user();
            if (!$user) return $this->unauthorizedResponse('Login required');

            $data = $request->validate([
                'status' => ['required', 'integer', 'between:0,3'],
            ]);

            $project->update(['status' => (int) $data['status']]);

            return $this->successResponse($project->fresh(), 'Status updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update status', $e->getMessage());
        }
    }

    /**
     * PATCH /projects/{project}/progress
     * Body: { "progress": 0..100 }
     */
    public function updateProgress(Request $request, Project $project)
    {
        try {
            $user = $request->user();
            if (!$user) return $this->unauthorizedResponse('Login required');

            $data = $request->validate([
                'progress' => ['required', 'integer', 'between:0,100'],
            ]);

            $project->update(['progress' => (int) $data['progress']]);

            return $this->successResponse($project->fresh(), 'Progress updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update progress', $e->getMessage());
        }
    }

    public function updateDetails(Request $request, Project $project)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Validate inputs (all optional; use sometimes so only present fields are validated)
            $validated = $request->validate([
                'project_name'        => ['sometimes', 'required', 'string', 'max:255'],
                'client_name'         => ['sometimes', 'required', 'string', 'max:255'],
                'project_description' => ['sometimes', 'nullable', 'string'],
                'category'            => ['sometimes', 'nullable', 'string', 'max:255'], // CSV "web,crm"
                'due_date'            => ['sometimes', 'nullable', 'date'],
                'project_thumbnail'   => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ]);

            // Prepare update payload from validated inputs
            $updates = [];
            foreach (['project_name', 'client_name', 'project_description', 'category', 'due_date'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updates[$field] = $validated[$field];
                }
            }

            // If a file is uploaded, store it and (optionally) remove old one
            if ($request->hasFile('project_thumbnail')) {
                $file = $request->file('project_thumbnail');

                $dir  = 'projects/thumbnails';
                $name = sprintf(
                    'PRJ_%d_%s.%s',
                    $project->id,
                    now()->format('Ymd_His'),
                    $file->getClientOriginalExtension()
                );

                $newPath = $file->storeAs($dir, $name, 'public');

                // delete old file if exists
                if ($project->project_thumbnail && Storage::disk('public')->exists($project->project_thumbnail)) {
                    Storage::disk('public')->delete($project->project_thumbnail);
                }

                $updates['project_thumbnail'] = $newPath;
            }

            // Nothing to update?
            if (empty($updates)) {
                return $this->successResponse($project->fresh(), 'No changes');
            }

            $project->update($updates);

            // Attach a convenient URL in the response
            $project = $project->fresh();
            $project->append('project_thumbnail_url');

            return $this->successResponse($project, 'Project updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update project', $e->getMessage());
        }
    }

    public function assignUsers(Request $request, Project $project)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Normalize payload
            $payload = $request->all();
            $mode = $payload['mode'] ?? 'attach';

            // Accept either user_id or user_ids[]
            $ids = [];
            if (isset($payload['user_ids'])) {
                $ids = is_array($payload['user_ids']) ? $payload['user_ids'] : [$payload['user_ids']];
            } elseif (isset($payload['user_id'])) {
                $ids = [$payload['user_id']];
            }

            // Validate
            $validated = $request->validate([
                'mode'       => ['nullable', Rule::in(['attach', 'sync'])],
                'user_id'    => ['nullable', 'integer', 'exists:users,id'],
                'user_ids'   => ['nullable', 'array', 'min:1'],
                'user_ids.*' => ['integer', 'exists:users,id'],
            ]);

            // No users provided?
            if (empty($ids)) {
                return $this->validationErrorResponse(['At least one user_id is required.']);
            }

            // Dedup & cast to int
            $ids = array_values(array_unique(array_map('intval', $ids)));

            if ($mode === 'sync') {
                $project->users()->sync($ids);
            } else {
                $project->users()->syncWithoutDetaching($ids);
            }

            $project = $project->fresh('users');

            return $this->successResponse($project, 'Users assigned to project');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to assign users', $e->getMessage());
        }
    }

    /**
     * DELETE /projects/{project}/users/{user}
     * Detach a single user from the project
     */
    public function removeUser(Request $request, Project $project, User $user)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            $project->users()->detach($user->id);

            return $this->successResponse($project->fresh('users'), 'User removed from project');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to remove user', $e->getMessage());
        }
    }
}
