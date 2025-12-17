<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PermissionController;

Route::middleware('auth:api')->group(function () {
    Route::patch('/permissions/{permissionId}', [PermissionController::class, 'update']);
});
