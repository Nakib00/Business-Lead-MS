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

        // Projects Aggregation
        $projAgg = $user->projects()
            ->selectRaw('
                count(*) as total,
                count(case when status = 0 then 1 end) as pending,
                count(case when status = 1 then 1 end) as active,
                count(case when status = 2 then 1 end) as completed,
                count(case when status = 3 then 1 end) as on_hold,
                sum(case when status = 1 then progress else 0 end) as active_progress_total,
                avg(case when status = 1 then progress else null end) as active_progress_avg
            ')
            ->first();

        // Projects Recent
        $recentProjects = $user->projects()->orderBy('created_at', 'desc')->take(5)->get();

        $projectStats = [
            'total' => (int) ($projAgg->total ?? 0),
            'counts' => [
                'pending'   => (int) ($projAgg->pending ?? 0),
                'active'    => (int) ($projAgg->active ?? 0),
                'completed' => (int) ($projAgg->completed ?? 0),
                'on_hold'   => (int) ($projAgg->on_hold ?? 0),
            ],
            'active_progress' => [
                'total'   => (float) ($projAgg->active_progress_total ?? 0),
                'average' => (float) ($projAgg->active_progress_avg ?? 0),
            ],
            'recent' => $recentProjects,
        ];

        // Tasks Aggregation
        $taskAgg = $user->tasks()
            ->selectRaw('
                count(*) as total,
                count(case when status = 0 then 1 end) as pending,
                count(case when status = 1 then 1 end) as in_progress,
                count(case when status = 2 then 1 end) as done,
                count(case when status = 3 then 1 end) as blocked
            ')
            ->first();

        // Tasks Recent
        $recentTasks = $user->tasks()->orderBy('created_at', 'desc')->take(5)->get();

        $taskStats = [
            'total' => (int) ($taskAgg->total ?? 0),
            'counts' => [
                'pending'     => (int) ($taskAgg->pending ?? 0),
                'in_progress' => (int) ($taskAgg->in_progress ?? 0),
                'done'        => (int) ($taskAgg->done ?? 0),
                'blocked'     => (int) ($taskAgg->blocked ?? 0),
            ],
            'recent' => $recentTasks,
        ];

        return $this->successResponse([
            'projects' => $projectStats,
            'tasks' => $taskStats,
        ], 'Dashboard stats retrieved successfully');
    }
}
