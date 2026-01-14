<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class ProjectService
{
    /**
     * Create a new project.
     */
    public function createProject(array $data, ?UploadedFile $thumbnail, User $user)
    {
        return DB::transaction(function () use ($data, $thumbnail, $user) {
            // prefer reg_user_id if present; fallback to current user id
            $adminId = $user->reg_user_id ?? $user->id;

            // Handle thumbnail
            $thumbnailFullUrl = null;
            if ($thumbnail) {
                $thumbnailFullUrl = $thumbnail->store('projectThumbnails', 'public');
            }

            $payload = [
                'project_code'        => 'PRJ-' . Str::upper(Str::random(6)),
                'project_name'        => $data['project_name'],
                'client_name'         => $data['client_name'] ?? null,
                'project_description' => $data['project_description'] ?? null,
                'priority'            => $data['priority'],
                'category'            => $data['category'] ?? null,
                'budget'              => $data['budget'] ?? null,
                'due_date'            => $data['due_date'] ?? null,

                'admin_id'            => $adminId,
                'client_id'           => $data['client_id'] ?? null,

                // Save relative path (or whatever logic was used, matching controller)
                'project_thumbnail'   => $thumbnailFullUrl,

                'status'              => 0,
                'progress'            => 0,
            ];

            if (Schema::hasColumn('projects', 'created_by')) {
                $payload['created_by'] = $user->id;
            }

            $project = Project::create($payload);

            // Assign users
            $userIds = $data['user_ids'] ?? [];
            // Ensure unique integers
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
    }

    /**
     * Update project details.
     */
    public function updateProjectDetails(Project $project, array $data)
    {
        $project->fill($data);

        if ($project->isDirty()) {
            $project->save();
        }

        return $project->fresh();
    }

    /**
     * Update project thumbnail.
     */
    public function updateProjectThumbnail(Project $project, ?UploadedFile $file)
    {
        // Delete old thumbnail if exists
        $this->deleteProjectThumbnailFile($project->project_thumbnail);

        $thumbnailPath = null;
        if ($file) {
            $thumbnailPath = $file->store('projectThumbnails', 'public');
        }

        $project->project_thumbnail = $thumbnailPath;
        $project->save();

        return $project->fresh();
    }

    /**
     * Assign users to a project.
     */
    public function assignUsers(Project $project, array $userIds, string $mode = 'attach')
    {
        // Dedup & cast to int
        $ids = array_values(array_unique(array_map('intval', $userIds)));

        if ($mode === 'sync') {
            $project->users()->sync($ids);
        } else {
            $project->users()->syncWithoutDetaching($ids);
        }

        return $project->fresh('users');
    }

    /**
     * Remove a user from a project.
     */
    public function removeUser(Project $project, User $user)
    {
        $project->users()->detach($user->id);
        return $project->fresh('users');
    }

    /**
     * Delete a project.
     */
    public function deleteProject(Project $project)
    {
        return DB::transaction(function () use ($project) {
            $this->deleteProjectThumbnailFile($project->project_thumbnail);
            $project->delete();
        });
    }

    /**
     * Helper to delete thumbnail file.
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
}
