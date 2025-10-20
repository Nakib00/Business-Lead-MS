<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Task\TaskController;

Route::middleware('auth:api')->group(function () {
    Route::prefix('tasks')->group(function () {
        Route::post('/{project}', [TaskController::class, 'storeForProject']);
    });
});
