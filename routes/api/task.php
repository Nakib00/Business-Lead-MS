<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Task\TaskController;



Route::middleware('auth:sanctum')->group(function () {
    // Task routes
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
