<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Lead\BusinessLeadController;
use App\Http\Controllers\Task\TaskController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::prefix('users')->group(function () {
        Route::get('/', [AuthController::class, 'index']);
        Route::get('/admin/{userId}', [AuthController::class, 'registeredUsers']);
        Route::delete('/{id}', [AuthController::class, 'destroy']);
        Route::put('/toggle-subscribe/{id}', [AuthController::class, 'toggleSubscribe']);
    });

    Route::prefix('business-leads')->group(function () {
        Route::post('/', [BusinessLeadController::class, 'store']);
        Route::get('/', [BusinessLeadController::class, 'allBusinessLeads']);
        Route::get('/admin/{userId}', [BusinessLeadController::class, 'showLeadAdmin']);
        Route::get('/creator/{userId}', [BusinessLeadController::class, 'createorLeads']);
        Route::get('{leads_id}', [BusinessLeadController::class, 'show']);
        Route::put('{leads_id}', [BusinessLeadController::class, 'update']);
        Route::delete('{leads_id}', [BusinessLeadController::class, 'destroy']);
        Route::get('/leads/count', [BusinessLeadController::class, 'totalLeadCount']);
        Route::get('/count/{userId}', [BusinessLeadController::class, 'userLeadCount']);
        Route::post('/leads/upload', [BusinessLeadController::class, 'upload']);
    });

    Route::prefix('tasks')->group(function () {
        Route::post('/', [TaskController::class, 'store']);
        Route::post('/individual-tasks', [TaskController::class, 'assignTask']);
        Route::get('/', [TaskController::class, 'index']);
        Route::delete('/{tasks_id}', [TaskController::class, 'destroy']);
        Route::get('/{tasks_id}', [TaskController::class, 'show']);
        Route::patch('/status/{tasks_id}', [TaskController::class, 'updateTaskStatus']);
        Route::put('/update/{tasks_id}', [TaskController::class, 'update']);
        Route::post('/assign-user/{task_id}', [TaskController::class, 'assignUser']);
        Route::delete('/assign-tasks/{task_assign_user_id}', [TaskController::class, 'removeAssignUser']);
        Route::put('/task-user-assigns/update/{task_assign_user_id}', [TaskController::class, 'updateTaskUserAssign']);
        Route::put('/individual-tasks/update/{individual_task_id}', [TaskController::class, 'updateIndividualTask']);
        Route::put('/individual-tasks/toggle-checkbox/{individual_task_id}', [TaskController::class, 'toggleCheckbox']);
        Route::delete('/individual-tasks/{individual_task_id}', [TaskController::class, 'individualDestroy']);
    });
});
