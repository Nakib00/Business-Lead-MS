<?php

namespace App\Http\Requests\Project;

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\ProjectService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\UpdateProjectDetailsRequest;
use App\Http\Requests\Project\UpdateProjectThumbnailRequest;
use App\Http\Requests\Project\AssignProjectUsersRequest;

class ProjectController extends Controller
{
    use ApiResponseTrait;

    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function store(StoreProjectRequest $request)
    {
        try {
            $project = $this->projectService->createProject(
                $request->validated(),
                $request->file('project_thumbnail'),
                $request->user()
            );

            return $this->successResponse($project, 'Project created', 201);
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to create project', $e->getMessage());
        }
    }

    public function updatePriority(Request $request, Project $project)
    {
        try {
            // Keep validation inline for simple single-field updates or create a Request if preferred.
            // For "professional" look, generic single-field updates are often fine inline or via a shared Request.
            // I'll keep it inline to avoid over-engineering for 1 field, or I could use the Service generic update.
            $validated = $request->validate([
                'priority' => ['required', Rule::in(['low', 'medium', 'high'])],
            ]);

            $project = $this->projectService->updateProjectDetails($project, $validated);

            return $this->successResponse($project, 'Priority updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update priority', $e->getMessage());
        }
    }

    public function updateStatus(Request $request, Project $project)
    {
        try {
            $validated = $request->validate([
                'status' => ['required', 'integer', 'between:0,3'],
            ]);

            $project = $this->projectService->updateProjectDetails($project, $validated);

            return $this->successResponse($project, 'Status updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update status', $e->getMessage());
        }
    }

    public function updateProgress(Request $request, Project $project)
    {
        try {
            $validated = $request->validate([
                'progress' => ['required', 'integer', 'between:0,100'],
            ]);

            $project = $this->projectService->updateProjectDetails($project, $validated);

            return $this->successResponse($project, 'Progress updated');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update progress', $e->getMessage());
        }
    }

    public function updateDetails(UpdateProjectDetailsRequest $request, Project $project)
    {
        try {
            // Service handles isDirty check inside updateProjectDetails (saves if dirty)
            $project = $this->projectService->updateProjectDetails($project, $request->validated());

            if ($project->wasChanged()) {
                return $this->successResponse($project, 'Project updated');
            }
            return $this->successResponse($project, 'No changes');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to update project', $e->getMessage());
        }
    }

    public function updateProjectThumbnail(UpdateProjectThumbnailRequest $request, $id)
    {
        try {
            $project = Project::find($id);

            if (!$project) {
                return response()->json([
                    'success' => false,
                    'message' => 'Project not found',
                ], 404);
            }

            $project = $this->projectService->updateProjectThumbnail(
                $project,
                $request->file('project_thumbnail')
            );

            return response()->json([
                'success' => true,
                'message' => 'Project thumbnail updated successfully',
                'data'    => [
                    'project_id'            => $project->id,
                    'project_thumbnail_url' => $project->project_thumbnail_url,
                ],
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project thumbnail',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function assignUsers(AssignProjectUsersRequest $request, Project $project)
    {
        try {
            $mode = $request->input('mode', 'attach');
            $userIds = $request->input('user_ids', []);

            // If user_id (singular) is used, add it to array
            if ($request->has('user_id')) {
                $userIds[] = $request->input('user_id');
            }

            $project = $this->projectService->assignUsers($project, $userIds, $mode);

            return $this->successResponse($project, 'Users assigned to project');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to assign users', $e->getMessage());
        }
    }

    public function removeUser(Request $request, Project $project, User $user)
    {
        try {
            $project = $this->projectService->removeUser($project, $user);
            return $this->successResponse($project, 'User removed from project');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to remove user', $e->getMessage());
        }
    }

    public function destroy(Request $request, Project $project)
    {
        try {
            $this->projectService->deleteProject($project);
            return $this->successResponse(null, 'Project deleted');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to delete project', $e->getMessage());
        }
    }

    /**
     * GET /projects
     * Kept in controller as it is primarily data fetching/filtering
     */
    public function indexSummary(Request $request)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return $this->unauthorizedResponse('Login required');
            }

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
                    'client_id',
                    'project_description',
                    'category',
                    'budget',
                    'status',
                    'progress',
                    'due_date',
                    'priority',
                    'project_thumbnail',
                    'admin_id',
                ]);

            // Role-based filtering
            switch ($user->type) {
                case 'admin':
                    $query->where('admin_id', $user->id);
                    break;
                case 'leader':
                    // Leader sees projects of their registered admin
                    $query->where('admin_id', $user->reg_user_id);
                    break;
                case 'member':
                    // Member sees projects they are assigned to
                    $query->whereHas('users', function ($q) use ($user) {
                        $q->where('users.id', $user->id);
                    });
                    break;
                case 'client':
                    // Client sees projects assigned to them
                    $query->where('client_id', $user->id);
                    break;
                default:
                    // Fallback: Show nothing or handle strictly? 
                    // Let's assume unauthorized / empty list for unknown types
                    $query->whereRaw('0 = 1');
                    break;
            }

            $query->with([
                'users:id,name,profile_image',
            ])
                ->withCount([
                    'tasks as total_tasks',
                    'tasks as completed_tasks' => function ($q) {
                        $q->where('status', 2);
                    },
                ])
                ->orderByDesc('id');

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
                // Accessor in Model should handle this, but keeping controller logic identical
                return [
                    'id'                      => $project->id,
                    'project_code'            => $project->project_code,
                    'project_name'            => $project->project_name,
                    'client_id'               => $project->client_id,
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
                            'profile_image_url'  => filter_var($u->profile_image, FILTER_VALIDATE_URL)
                                ? $u->profile_image
                                : $u->profile_image_url,
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

            $assignedUsers = $project->users->map(function ($u) {
                return [
                    'id'                => $u->id,
                    'name'              => $u->name,
                    'profile_image_url' => filter_var($u->profile_image, FILTER_VALIDATE_URL)
                        ? $u->profile_image
                        : $u->profile_image_url,
                ];
            })->values()->all();

            $tasks = $project->tasks->map(function ($t) {
                $taskUsers = $t->users->map(function ($u) {
                    return [
                        'id'                => $u->id,
                        'name'              => $u->name,
                        'profile_image_url' => filter_var($u->profile_image, FILTER_VALIDATE_URL)
                            ? $u->profile_image
                            : $u->profile_image_url,
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

            $data = [
                'id'                   => $project->id,
                'project_code'         => $project->project_code,
                'project_name'         => $project->project_name,
                'client_id'            => $project->client_id,
                'project_description'  => $project->project_description,
                'category'             => $project->category,
                'priority'             => $project->priority,
                'budget'               => $project->budget,
                'due_date' => $project->due_date?->format('Y-m-d'),
                'status'               => (int) $project->status,
                'progress'             => (int) $project->progress,
                'admin_id'             => (int) $project->admin_id,
                'project_thumbnail_url' => $project->project_thumbnail_url,
                'created_at'           => optional($project->created_at)->toDateTimeString(),
                'updated_at'           => optional($project->updated_at)->toDateTimeString(),
                'total_tasks'          => (int) ($project->total_tasks ?? 0),
                'completed_tasks'      => (int) ($project->completed_tasks ?? 0),
                'assigned_users'       => $assignedUsers,
                'tasks'                => $tasks,
            ];

            return $this->successResponse($data, 'Project details fetched');
        } catch (\Throwable $e) {
            return $this->serverErrorResponse('Failed to fetch project details', $e->getMessage());
        }
    }
}
