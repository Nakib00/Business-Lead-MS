<?php

use Illuminate\Support\Facades\Route;


require __DIR__ . '/api/auth.php';
require __DIR__ . '/api/dashboard.php';
require __DIR__ . '/api/lead.php';
require __DIR__ . '/api/task.php';
require __DIR__ . '/api/from.php';
require __DIR__ . '/api/project.php';
require __DIR__ . '/api/permission.php';

Route::middleware('auth:api')->prefix('system')->group(function () {
    Route::get('/artisan', [App\Http\Controllers\System\ArtisanRunnerController::class, 'run']);
});
