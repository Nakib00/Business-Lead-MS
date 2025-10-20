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
}
