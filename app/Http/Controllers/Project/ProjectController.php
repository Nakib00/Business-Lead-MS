<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Project;
use App\Models\User;
use App\Traits\ApiResponseTrait;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            if (is_null($incomingUserIds)) {
                $userIds = [];
            } elseif (is_array($incomingUserIds)) {
                $userIds = $incomingUserIds;
            } else {
                $userIds = [$incomingUserIds]; // single value -> array
            }

            // Validate input
            $validated = $request->validate([
                'project_name'        => ['required', 'string', 'max:255'],
                'client_name'         => ['required', 'string', 'max:255'],
                'project_description' => ['nullable', 'string'],
                'priority'            => ['required', Rule::in(['low', 'medium', 'high'])],
                'budget'              => ['nullable', 'numeric', 'min:0'],
                'due_date'            => ['nullable', 'date'],
                'project_thumbnail'   => ['nullable', 'string', 'max:255'],
                'user_ids'            => ['nullable'],
                'user_ids.*'          => ['integer', 'exists:users,id'],
            ]);

            // Transaction: create project + assign users
            $project = DB::transaction(function () use ($validated, $user, $userIds) {
                // Base payload for Project
                $payload = [
                    'project_code'        => 'PRJ-' . Str::upper(Str::random(6)),
                    'project_name'        => $validated['project_name'],
                    'client_name'         => $validated['client_name'],
                    'project_description' => $validated['project_description'] ?? null,
                    'priority'            => $validated['priority'],
                    'budget'              => $validated['budget'] ?? null,
                    'due_date'            => $validated['due_date'] ?? null,
                    'project_thumbnail'   => $validated['project_thumbnail'] ?? null,
                    'status'              => 0,
                    'progress'            => 0,
                ];

                // If your table has a created_by column, fill it
                if (Schema::hasColumn('projects', 'created_by')) {
                    $payload['created_by'] = $user->id;
                }

                /** @var Project $project */
                $project = Project::create($payload);

                // Always include the creator in assignments
                if (!in_array($user->id, $userIds ?? [], true)) {
                    $userIds[] = $user->id;
                }

                // Filter duplicates and ensure all exist (already validated, but be safe)
                $userIds = array_values(array_unique(array_map('intval', $userIds)));

                if (!empty($userIds)) {
                    $project->users()->syncWithoutDetaching($userIds);
                }

                return $project->fresh(['users']);
            });

            return $this->successResponse($project, 'Project created', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return $this->validationErrorResponse($e->validator->errors());
        } catch (\Throwable $e) {
            // Log if you want: \Log::error($e);
            return $this->serverErrorResponse('Failed to create project', $e->getMessage());
        }
    }
}
