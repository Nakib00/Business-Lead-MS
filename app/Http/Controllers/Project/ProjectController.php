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
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\File;

class ProjectController extends Controller
{
    use ApiResponseTrait;

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->unauthorizedResponse('Login required');
            }

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

                // <-- key part for arrays
                'user_ids'            => ['nullable', 'array'],
                'user_ids.*'          => ['integer', 'exists:users,id'],
            ]);

            $project = DB::transaction(function () use ($validated, $user, $request) {

                // prefer reg_user_id if present; fallback to current user id
                $adminId = $user->reg_user_id ?? $user->id;

                // Handle thumbnail (store on "public" disk and generate a public URL)
                $thumbnailFullUrl = null;
                if ($request->hasFile('project_thumbnail')) {
                    $imagePath = $request->file('project_thumbnail')->store('projectThumbnails', 'public');
                    // Save ONLY relative path in DB
                    $thumbnailFullUrl = $imagePath;
                }


                $payload = [
                    'project_code'        => 'PRJ-' . Str::upper(Str::random(6)),
                    'project_name'        => $validated['project_name'],
                    'client_name'         => $validated['client_name'],
                    'project_description' => $validated['project_description'] ?? null,
                    'priority'            => $validated['priority'],
                    'category'            => $validated['category'] ?? null,
                    'budget'              => $validated['budget'] ?? null,
                    'due_date'            => $validated['due_date'] ?? null,

                    'admin_id'            => $adminId,

                    // Save FULL URL/path (Storage::url)
                    'project_thumbnail'   => $thumbnailFullUrl,

                    'status'              => 0,
                    'progress'            => 0,
                ];

                if (Schema::hasColumn('projects', 'created_by')) {
                    $payload['created_by'] = $user->id;
                }

                /** @var Project $project */
                $project = Project::create($payload);

                // Grab array directly from validated data, ensure ints & unique
                $userIds = $validated['user_ids'] ?? [];
                $userIds = array_values(array_unique(array_map('intval', $userIds)));

                // Always include the creator
                if (!in_array($user->id, $userIds, true)) {
                    $userIds[] = $user->id;
                }

                if (!empty($userIds)) {
                    $project->users()->syncWithoutDetaching($userIds);
                }

                return $project->fresh(['users']);
            });

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
            // Make sure user is logged in (keep your existing check)
            if (!$request->user()) {
                return $this->unauthorizedResponse('Login required');
            }

            // Only take the fields we care about (works with form-data, urlencoded, query, etc.)
            $input = $request->only([
                'project_name',
                'client_name',
                'project_description',
                'category',
                'priority',
                'budget',
                'due_date',
                'status',
                'progress',
            ]);

            // Validate only provided fields (all are optional / "sometimes")
            $validated = validator($input, [
                'project_name'        => ['sometimes', 'string', 'max:255'],
                'client_name'         => ['sometimes', 'string', 'max:255'],
                'project_description' => ['sometimes', 'nullable', 'string'],
                'category'            => ['sometimes', 'nullable', 'string', 'max:255'],
                'priority'            => ['sometimes', Rule::in(['low', 'medium', 'high'])],
                'budget'              => ['sometimes', 'numeric', 'min:0'],
                'due_date'            => ['sometimes', 'nullable', 'date'],
                'status'              => ['sometimes', 'integer', 'between:0,3'],
                'progress'            => ['sometimes', 'integer', 'between:0,100'],
            ])->validate();

            // Fill model with new values
            $project->fill($validated);

            // If nothing actually changed, return "No changes"
            if (!$project->isDirty()) {
                // Accessor in model will automatically append project_thumbnail_url
                return $this->successResponse($project->fresh(), 'No changes');
            }

            // Save changes
            $project->save();

            // fresh() to get updated values + appended attributes
            return $this->successResponse($project->fresh(), 'Project updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update project', $e->getMessage());
        }
    }

    /**
     * Update project thumbnail image
     */
    public function updateProjectThumbnail(Request $request, $id)
    {
        try {
            // Validate the request
            $validator = Validator::make($request->all(), [
                'project_thumbnail' => 'required|image|mimes:jpeg,png,jpg,gif,webp|max:2048'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find the project
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found'
                ], 404);
            }

            // Delete old thumbnail if exists
            $this->deleteProjectThumbnailFile($project->project_thumbnail);

            // Upload new thumbnail
            $thumbnailFullUrl = null;
            if ($request->hasFile('project_thumbnail')) {
                $imagePath = $request->file('project_thumbnail')->store('projectThumbnails', 'public');
                $thumbnailFullUrl = Storage::url($imagePath);
            }

            // Update project thumbnail
            $project->project_thumbnail = $thumbnailFullUrl;
            $project->save();

            return response()->json([
                'success' => true,
                'message' => 'Project thumbnail updated successfully',
                'data' => [
                    'project_id' => $project->id,
                    'project_thumbnail' => $project->project_thumbnail
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project thumbnail',
                'error' => $e->getMessage()
            ], 500);
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
            // Must be logged in
            $user = Auth::user();
            if (!$user) {
                return $this->unauthorizedResponse('Login required');
            }

            // Determine effective admin id
            $effectiveAdminId = $user->reg_user_id ?: $user->id;

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

            $limit     = (int) $request->query('limit', 5);
            $page      = (int) $request->query('page', 1);
            $s         = $request->query('search');
            $status    = $request->query('status');
            $priority  = $request->query('priority');
            $dueExact  = $request->query('due_date');
            $dueBefore = $request->query('due_before');
            $dueAfter  = $request->query('due_after');

            $query = Project::query()
                ->select([
                    'id',
                    'project_code',
                    'project_name',
                    'client_name',
                    'project_description',
                    'category',
                    'budget',
                    'status',
                    'progress',
                    'due_date',
                    'priority',
                    'project_thumbnail',
                    'admin_id',
                ])
                // Only projects under this effective admin
                ->where('admin_id', $effectiveAdminId)
                ->with([
                    'users:id,name,profile_image',
                ])
                ->withCount([
                    'tasks as total_tasks',
                    'tasks as completed_tasks' => function ($q) {
                        $q->where('status', 2);
                    },
                ])
                ->orderByDesc('id');

            // Filters
            if (!empty($s)) {
                $query->where(function ($q) use ($s) {
                    $like = '%' . str_replace(['%', '_'], ['\%', '\_'], $s) . '%';
                    $q->where('project_name', 'like', $like)
                        ->orWhere('client_name', 'like', $like)
                        ->orWhere('project_code', 'like', $like);
                });
            }
            if ($status !== null) {
                $query->where('status', (int) $status);
            }
            if (!empty($priority)) {
                $query->where('priority', $priority);
            }
            if (!empty($dueExact)) {
                $query->whereDate('due_date', '=', $dueExact);
            }
            if (!empty($dueBefore)) {
                $query->whereDate('due_date', '<=', $dueBefore);
            }
            if (!empty($dueAfter)) {
                $query->whereDate('due_date', '>=', $dueAfter);
            }

            $paginator = $query->paginate($limit, ['*'], 'page', $page);

            $data = $paginator->getCollection()->map(function (Project $project) {
                return [
                    'id'                      => $project->id,
                    'project_code'            => $project->project_code,
                    'project_name'            => $project->project_name,
                    'client_name'             => $project->client_name,
                    'project_description'     => $project->project_description,
                    'category'                => $project->category,
                    'budget'                  => $project->budget,
                    'status'                  => (int) $project->status,
                    'progress'                => (int) $project->progress,
                    'due_date' => $project->due_date?->format('Y-m-d'),
                    'priority'                => $project->priority,
                    'project_thumbnail_url'   => $project->project_thumbnail_url,
                    'total_tasks'             => (int) ($project->total_tasks ?? 0),
                    'completed_tasks'         => (int) ($project->completed_tasks ?? 0),
                    'assigned_users'          => $project->users->map(function ($u) {
                        return [
                            'id'                 => $u->id,
                            'name'               => $u->name,
                            'profile_image_url'  => $u->profile_image_url,
                        ];
                    })->values(),
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
            // Require login
            $user = Auth::user();
            if (!$user) {
                return $this->unauthorizedResponse('Login required');
            }

            $effectiveAdminId = $user->reg_user_id ?: $user->id;

            if ((int)$project->admin_id !== (int)$effectiveAdminId) {

                return $this->notFoundResponse('Project not found');
            }

            $project->loadMissing([
                'users:id,name,profile_image',
                'tasks' => function ($q) {
                    $q->orderByDesc('id')
                        ->select(['id', 'project_id', 'task_name', 'description', 'status', 'priority', 'category', 'due_date']);
                },
                'tasks.users:id,name,profile_image',
            ])->loadCount([
                'tasks as total_tasks',
                'tasks as completed_tasks' => function ($q) {
                    $q->where('status', 2);
                },
            ]);

            // Map project assigned users (use accessor for URL)
            $assignedUsers = $project->users->map(function ($u) {
                return [
                    'id'                => $u->id,
                    'name'              => $u->name,
                    'profile_image_url' => $u->profile_image_url,
                ];
            })->values()->all();

            $tasks = $project->tasks->map(function ($t) {
                $taskUsers = $t->users->map(function ($u) {
                    return [
                        'id'                => $u->id,
                        'name'              => $u->name,
                        'profile_image_url' => $u->profile_image_url,
                    ];
                })->values()->all();

                return [
                    'id'              => $t->id,
                    'task_name'       => $t->task_name,
                    'description'     => $t->description,
                    'status'          => (int) $t->status,
                    'priority'        => $t->priority,
                    'category'        => $t->category,
                    'due_date'        => $t->due_date,
                    'assigned_users'  => $taskUsers,
                ];
            })->values()->all();

            // Build final payload
            $data = [
                'id'                   => $project->id,
                'project_code'         => $project->project_code,
                'project_name'         => $project->project_name,
                'client_name'          => $project->client_name,
                'project_description'  => $project->project_description,
                'category'             => $project->category,
                'priority'             => $project->priority,
                'budget'               => $project->budget,
                'due_date' => $project->due_date?->format('Y-m-d'),
                'status'               => (int) $project->status,
                'progress'             => (int) $project->progress,
                'admin_id'             => (int) $project->admin_id,

                // Use accessor for a clean, correct URL
                'project_thumbnail_url' => $project->project_thumbnail_url,

                'created_at'           => optional($project->created_at)->toDateTimeString(),
                'updated_at'           => optional($project->updated_at)->toDateTimeString(),

                // Aggregates
                'total_tasks'          => (int) ($project->total_tasks ?? 0),
                'completed_tasks'      => (int) ($project->completed_tasks ?? 0),

                // Relationships
                'assigned_users'       => $assignedUsers,
                'tasks'                => $tasks,
            ];

            return $this->successResponse($data, 'Project details fetched');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch project details', $e->getMessage());
        }
    }
}
