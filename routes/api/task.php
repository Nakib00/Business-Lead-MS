<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Task\TaskController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('tasks')->group(function () {
        Route::get('/', [TaskController::class, 'indexSummary']);
        Route::get('/{task}', [TaskController::class, 'show']);
        Route::post('/{project}', [TaskController::class, 'storeForProject']);

        Route::patch('/status/{task}',   [TaskController::class, 'updateStatus']);
        Route::patch('/priority/{task}', [TaskController::class, 'updatePriority']);
        Route::patch('/category/{task}', [TaskController::class, 'updateCategory']);

        // Full update of selected fields
        Route::put('/{task}',          [TaskController::class, 'updateDetails']);

        Route::post('/users/{project}/{task}',        [TaskController::class, 'assignUsers']);
        Route::delete('/users/{project}/{task}/{user}', [TaskController::class, 'removeUser']);

        Route::delete('/{project}/{task}', [TaskController::class, 'destroy']);
    });
});
