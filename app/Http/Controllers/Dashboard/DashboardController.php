<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Traits\ApiResponseTrait;

class DashboardController extends Controller
{
    use ApiResponseTrait;

    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Ensure user is authenticated
        if (!$user) {
            return $this->unauthorizedResponse('Unauthorized');
        }

        // Projects Query - Consolidated
        $projectCounts = $user->projects()
            ->selectRaw('
                count(*) as total,
                COALESCE(sum(case when status = 0 then 1 else 0 end), 0) as pending,
                COALESCE(sum(case when status = 1 then 1 else 0 end), 0) as active,
                COALESCE(sum(case when status = 2 then 1 else 0 end), 0) as completed,
                COALESCE(sum(case when status = 3 then 1 else 0 end), 0) as on_hold,
                COALESCE(sum(case when status = 1 then progress else 0 end), 0) as active_progress_total,
                COALESCE(avg(case when status = 1 then progress else null end), 0) as active_progress_average
            ')
            ->first();

        // Tasks Query - Consolidated
        $taskCounts = $user->tasks()
            ->selectRaw('
                count(*) as total,
                COALESCE(sum(case when status = 0 then 1 else 0 end), 0) as pending,
                COALESCE(sum(case when status = 1 then 1 else 0 end), 0) as in_progress,
                COALESCE(sum(case when status = 2 then 1 else 0 end), 0) as done,
                COALESCE(sum(case when status = 3 then 1 else 0 end), 0) as blocked
            ')
            ->first();

        $projectStats = [
            'total' => $projectCounts->total,
            'counts' => [
                'pending'   => $projectCounts->pending,
                'active'    => $projectCounts->active,
                'completed' => $projectCounts->completed,
                'on_hold'   => $projectCounts->on_hold,
            ],
            'active_progress' => [
                'total'   => $projectCounts->active_progress_total,
                'average' => $projectCounts->active_progress_average,
            ],
            // Retaining separate query for recent items as it requires ordering and limiting
            'recent' => $user->projects()->orderBy('created_at', 'desc')->take(5)->get(),
        ];

        $taskStats = [
            'total' => $taskCounts->total,
            'counts' => [
                'pending'     => $taskCounts->pending,
                'in_progress' => $taskCounts->in_progress,
                'done'        => $taskCounts->done,
                'blocked'     => $taskCounts->blocked,
            ],
            // Retaining separate query for recent items
            'recent' => $user->tasks()->orderBy('created_at', 'desc')->take(5)->get(),
        ];

        return $this->successResponse([
            'projects' => $projectStats,
            'tasks' => $taskStats,
        ], 'Dashboard stats retrieved successfully');
    }
}
