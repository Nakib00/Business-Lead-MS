<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Project\ProjectController;

Route::get('/projects', [ProjectController::class, 'indexSummary']);
Route::middleware('auth:api')->group(function () {
    Route::prefix('projects')->group(function () {
        Route::get('/details/{project}', [ProjectController::class, 'showDetails']);
        Route::post('/', [ProjectController::class, 'store']);
        Route::put('/{project}', [ProjectController::class, 'updateDetails']);

        Route::patch('/priority/{project}', [ProjectController::class, 'updatePriority']);
        Route::patch('/status/{project}',   [ProjectController::class, 'updateStatus']);
        Route::patch('/progress/{project}', [ProjectController::class, 'updateProgress']);

        Route::post('/users/{project}',        [ProjectController::class, 'assignUsers']);
        Route::delete('/users/{project}/{user}', [ProjectController::class, 'removeUser']);

        Route::delete('/{project}', [ProjectController::class, 'destroy']);
    });
});
