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

        // Projects Query - relating to the user
        $projectsQuery = $user->projects();

        $projectStats = [
            'total' => $projectsQuery->count(),
            'counts' => [
                'pending'   => $user->projects()->where('status', 0)->count(),
                'active'    => $user->projects()->where('status', 1)->count(),
                'completed' => $user->projects()->where('status', 2)->count(),
                'on_hold'   => $user->projects()->where('status', 3)->count(),
            ],
            'active_progress' => [
                'total'   => $user->projects()->where('status', 1)->sum('progress'),
                'average' => $user->projects()->where('status', 1)->avg('progress') ?? 0,
            ],
            'recent' => $user->projects()->orderBy('created_at', 'desc')->take(5)->get(),
        ];

        // Tasks Query - relating to the user
        $taskStats = [
            'total' => $user->tasks()->count(),
            'counts' => [
                'pending'     => $user->tasks()->where('status', 0)->count(),
                'in_progress' => $user->tasks()->where('status', 1)->count(),
                'done'        => $user->tasks()->where('status', 2)->count(),
                'blocked'     => $user->tasks()->where('status', 3)->count(),
            ],
            'recent' => $user->tasks()->orderBy('created_at', 'desc')->take(5)->get(),
        ];

        return $this->successResponse([
            'projects' => $projectStats,
            'tasks' => $taskStats,
        ], 'Dashboard stats retrieved successfully');
    }
    public function superAdminIndex()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        // Ensure user is authenticated
        if (!$user) {
            return $this->unauthorizedResponse('Unauthorized');
        }

        // Global Counts
        $totalAdmins = \App\Models\User::where('type', 'admin')->count();
        $totalFormTemplates = \App\Models\Form::whereNotNull('super_admin_id')->count();
        $totalProjects = \App\Models\Project::count();
        $totalSubmissions = \App\Models\FormSubmission::count();
        $totalTasks = \App\Models\Task::count();


        return $this->successResponse([
            'overview' => [
                'total_admins' => $totalAdmins,
                'total_form_templates' => $totalFormTemplates,
                'total_projects' => $totalProjects,
                'total_submissions' => $totalSubmissions,
                'total_tasks' => $totalTasks,
            ],
        ], 'Super Admin Dashboard stats retrieved successfully');
    }
}
