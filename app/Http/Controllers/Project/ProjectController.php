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
use Illuminate\Support\Facades\File;

class ProjectController extends Controller
{
    use ApiResponseTrait;
    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) return $this->unauthorizedResponse('Login required');

            // Normalize user_ids input (accept single int or array)
            $incomingUserIds = $request->input('user_ids');
            $userIds = is_null($incomingUserIds)
                ? []
                : (is_array($incomingUserIds) ? $incomingUserIds : [$incomingUserIds]);

            // Validate
            $validated = $request->validate([
                'project_name'        => ['required', 'string', 'max:255'],
                'client_name'         => ['required', 'string', 'max:255'],
                'project_description' => ['nullable', 'string'],
                'category'            => ['nullable', 'string', 'max:255'],
                'priority'            => ['required', Rule::in(['low', 'medium', 'high'])],
                'budget'              => ['nullable', 'numeric', 'min:0'],
                'due_date'            => ['nullable', 'date'],
                'project_thumbnail'   => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                'user_ids'            => ['nullable'],
                'user_ids.*'          => ['integer', 'exists:users,id'],
            ]);

            $project = DB::transaction(function () use ($validated, $user, $userIds, $request) {

                $thumbnailUrlForDB = null;

                if ($request->hasFile('project_thumbnail')) {
                    $imagePath = $request->file('project_thumbnail')->store('projectThumbnails', 'public');
                    $thumbnailUrlForDB = env('APP_URL') . '/storage/app/public/' . $imagePath;
                }

                // Base payload
                $payload = [
                    'project_code'        => 'PRJ-' . Str::upper(Str::random(6)),
                    'project_name'        => $validated['project_name'],
                    'client_name'         => $validated['client_name'],
                    'project_description' => $validated['project_description'] ?? null,
                    'priority'            => $validated['priority'],
                    'category'            => $validated['category'] ?? null,
                    'budget'              => $validated['budget'] ?? null,
                    'due_date'            => $validated['due_date'] ?? null,

                    // Save FULL URL directly in DB per your requirement:
                    'project_thumbnail'   => $thumbnailUrlForDB,

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

            // You no longer need append('project_thumbnail_url') because we stored a full URL already.
            // $project->append('project_thumbnail_url');

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

            // Validate inputs (all optional; only provided fields are validated)
            $validated = $request->validate([
                'project_name'        => ['sometimes', 'required', 'string', 'max:255'],
                'client_name'         => ['sometimes', 'required', 'string', 'max:255'],
                'project_description' => ['sometimes', 'nullable', 'string'],
                'category'            => ['sometimes', 'nullable', 'string', 'max:255'], // CSV "web,crm"
                'due_date'            => ['sometimes', 'nullable', 'date'],
                'project_thumbnail'   => ['sometimes', 'nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            ]);

            // Collect simple field updates
            $updates = [];
            foreach (['project_name', 'client_name', 'project_description', 'category', 'due_date'] as $field) {
                if (array_key_exists($field, $validated)) {
                    $updates[$field] = $validated[$field];
                }
            }

            // Handle thumbnail like your example (move() + relative path)
            if ($request->hasFile('project_thumbnail')) {
                $image     = $request->file('project_thumbnail');
                // unique-ish filename: time + random + original name (optional sanitize)
                $imageName = time() . '_' . \Illuminate\Support\Str::random(6) . '_' . $image->getClientOriginalName();

                // destination: public/projects/thumbnails
                $destPath  = public_path('projects/thumbnails');

                // ensure directory exists
                if (!File::exists($destPath)) {
                    File::makeDirectory($destPath, 0755, true);
                }

                // delete old file if we stored a relative path earlier
                if (!empty($project->project_thumbnail)) {
                    $oldAbs = public_path($project->project_thumbnail);
                    if (File::exists($oldAbs)) {
                        File::delete($oldAbs);
                    }
                }

                // move uploaded file
                $image->move($destPath, $imageName);

                // save RELATIVE path in DB
                $updates['project_thumbnail'] = 'projects/thumbnails/' . $imageName;
            }

            if (empty($updates)) {
                return $this->successResponse($project->fresh(), 'No changes');
            }

            $project->update($updates);

            // If you want to also return a public URL alongside the stored relative path:
            $fresh = $project->fresh();
            $fresh->setAttribute('project_thumbnail_url', $fresh->project_thumbnail ? url($fresh->project_thumbnail) : null);

            return $this->successResponse($fresh, 'Project updated');
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

    public function destroy(Request $request, Project $project)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            DB::transaction(function () use ($project) {

                $this->deleteProjectThumbnailFile($project->project_thumbnail);

                $project->delete();
            });

            return $this->successResponse(null, 'Project deleted');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to delete project', $e->getMessage());
        }
    }

    /**
     * Delete a stored thumbnail file whether it is a relative public path
     */
    protected function deleteProjectThumbnailFile(?string $value): void
    {
        if (empty($value)) return;

        $prefix = rtrim(config('app.url'), '/') . '/storage/';
        if (Str::startsWith($value, $prefix)) {
            // Convert URL -> relative path under 'public' disk
            $relative = ltrim(substr($value, strlen($prefix)), '/');
            if ($relative && Storage::disk('public')->exists($relative)) {
                Storage::disk('public')->delete($relative);
            }
            return;
        }
        $absolutePublic = public_path($value);
        if (File::exists($absolutePublic)) {
            File::delete($absolutePublic);
            return;
        }
        if (Storage::disk('public')->exists($value)) {
            Storage::disk('public')->delete($value);
            return;
        }
    }

    /**
     * GET /projects
     */
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
                'status'     => ['nullable', 'integer', 'between:0,3'],
                'priority'   => ['nullable', Rule::in(['low', 'medium', 'high'])],
                'due_date'   => ['nullable', 'date'],
                'due_before' => ['nullable', 'date'],
                'due_after'  => ['nullable', 'date'],
            ]);

            $limit = (int) $request->query('limit', 5);   // default 5
            $page  = (int) $request->query('page', 1);    // default page 1

            $query = Project::query()
                ->select(['id', 'project_code', 'project_name', 'client_name', 'status', 'progress', 'due_date'])
                ->with([
                    'users:id,profile_image'
                ])
                ->withCount([
                    'tasks as total_tasks',
                    'tasks as completed_tasks' => function ($q) {
                        $q->where('status', 2);
                    },
                ])
                ->orderByDesc('id'); // descending

            // Filters
            if ($s = $request->query('search')) {
                $query->where(function ($q) use ($s) {
                    $q->where('project_name', 'like', "%$s%")
                        ->orWhere('client_name', 'like', "%$s%")
                        ->orWhere('project_code', 'like', "%$s%");
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

            // Use custom page & limit
            $paginator = $query->paginate($limit, ['*'], 'page', $page);

            $data = $paginator->getCollection()->map(function (Project $project) {
                return [
                    'id'               => $project->id,
                    // 'project_thumbnail' => $project->project_thumbnail
                    //     ? Storage::url($project->project_thumbnail)   // => /storage/projects/thumbnails/<file>
                    //     : null,
                    'project_code'     => $project->project_code,
                    'project_name'     => $project->project_name,
                    'client_name'      => $project->client_name,
                    'status'           => $project->status,
                    'progress'         => $project->progress,
                    'due_date'         => optional($project->due_date)->format('Y-m-d'),
                    'assigned_user_images' => $project->users->map(function ($u) {
                        if (!$u->profile_image) return null;
                        // Full URL stays; relative path -> Storage::url()
                        return Str::startsWith($u->profile_image, ['http://', 'https://'])
                            ? $u->profile_image
                            : Storage::url($u->profile_image);
                    })->filter()->values()->all(),
                    'total_tasks'      => (int) ($project->total_tasks ?? 0),
                    'completed_tasks'  => (int) ($project->completed_tasks ?? 0),
                ];
            })->values();

            return $this->paginatedResponse($data, $paginator, 'Projects fetched');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch projects', $e->getMessage());
        }
    }

    public function showDetails(Request $request, Project $project)
    {
        try {
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            $project->loadMissing([
                'users:id,name,profile_image',
                'tasks' => function ($q) {
                    $q->orderByDesc('id');
                },
                'tasks.users:id,name,profile_image',
            ])->loadCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => function ($q) {
                    $q->where('status', 2);
                },
            ]);

            // Map project assigned users
            $assignedUsers = $project->users->map(function ($u) {
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

            // Map tasks and each task's assigned users
            $tasks = $project->tasks->map(function ($t) {
                $taskUsers = $t->users->map(function ($u) {
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

                return [
                    'id'          => $t->id,
                    'task_name'   => $t->task_name,
                    'description' => $t->description,
                    'status'      => $t->status,        // 0=pending,1=in_progress,2=done,3=blocked
                    'priority'    => $t->priority,      // low|medium|high
                    'category'    => $t->category,      // CSV as stored
                    'due_date'    => optional($t->due_date)->format('Y-m-d'),
                    'assigned_users' => $taskUsers,
                ];
            })->values()->all();

            // Build final payload (project "all info" + extras)
            $data = [
                // Project core columns (adjust/add if you have more)
                'id'                 => $project->id,
                'project_code'       => $project->project_code,
                'project_name'       => $project->project_name,
                'client_name'        => $project->client_name,
                'project_description' => $project->project_description,
                'category'           => $project->category,
                'priority'           => $project->priority,
                'budget'             => $project->budget,
                'due_date'           => optional($project->due_date)->format('Y-m-d'),
                'status'             => $project->status,
                'progress'           => $project->progress,
                'project_thumbnail' => $project->project_thumbnail
                    ? Storage::url($project->project_thumbnail)
                    : null,
                'created_at'         => optional($project->created_at)->toDateTimeString(),
                'updated_at'         => optional($project->updated_at)->toDateTimeString(),

                // Aggregates
                'total_tasks'        => (int) ($project->total_tasks ?? 0),
                'completed_tasks'    => (int) ($project->completed_tasks ?? 0),

                // Relationships
                'assigned_users'     => $assignedUsers,
                'tasks'              => $tasks,
            ];

            return $this->successResponse($data, 'Project details fetched');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch project details', $e->getMessage());
        }
    }
}
