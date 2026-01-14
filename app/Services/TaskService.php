<?php

namespace App\Services;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskService
{
    /**
     * Create a new task within a project.
     */
    public function createTask(Project $project, array $data, User $user)
    {
        return DB::transaction(function () use ($project, $data, $user) {
            // Normalize category if array
            $category = null;
            if (isset($data['category'])) {
                $category = $this->normalizeCategory($data['category']);
            }

            $task = Task::create([
                'project_id'  => $project->id,
                'task_name'   => $data['task_name'],
                'description' => $data['description'] ?? null,
                'status'      => 0, // pending
                'due_date'    => $data['due_date'] ?? null,
                'priority'    => $data['priority'],
                'category'    => $category,
            ]);

            // Handle user assignment
            $ids = [];
            if (!empty($data['user_ids'])) {
                $ids = is_array($data['user_ids']) ? $data['user_ids'] : [$data['user_ids']];
            }
            if (!empty($data['user_id'])) {
                $ids[] = $data['user_id'];
            }

            if (!empty($ids)) {
                $ids = array_values(array_unique(array_map('intval', $ids)));
                $task->users()->syncWithoutDetaching($ids);
            }

            return $task->load(['project', 'users']);
        });
    }

    /**
     * Update task details (generic).
     */
    public function updateTask(Task $task, array $data)
    {
        // Normalize category if present
        if (array_key_exists('category', $data)) {
            $data['category'] = $this->normalizeCategory($data['category']);
        }

        $task->update($data);

        return $task->fresh();
    }

    /**
     * Assign users to a task.
     */
    public function assignUsers(Task $task, array $userIds, string $mode = 'attach')
    {
        $ids = array_values(array_unique(array_map('intval', $userIds)));

        if ($mode === 'sync') {
            $task->users()->sync($ids);
        } else {
            $task->users()->syncWithoutDetaching($ids);
        }

        return $task->fresh('users');
    }

    /**
     * Remove a user from a task.
     */
    public function removeUser(Task $task, User $user)
    {
        $task->users()->detach($user->id);
        return $task->fresh('users');
    }

    /**
     * Delete a task.
     */
    public function deleteTask(Task $task)
    {
        return DB::transaction(function () use ($task) {
            $task->users()->detach(); // cleanup pivot
            $task->delete();
        });
    }

    /**
     * Helper to normalize category input (array/string -> CSV string).
     */
    protected function normalizeCategory($input)
    {
        if (is_array($input)) {
            return implode(',', array_map(fn($v) => trim((string)$v), $input));
        }

        return collect(explode(',', (string)$input))
            ->map(fn($v) => trim($v))
            ->filter()
            ->implode(',') ?: null;
    }
}
