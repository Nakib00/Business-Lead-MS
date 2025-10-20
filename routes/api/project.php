<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Project\ProjectController;

// Route::middleware('auth:api')->group(function () {
    Route::prefix('projects')->group(function () {
        Route::post('/', [ProjectController::class, 'store']);
    });
// });
