<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PermissionController;

Route::middleware('auth:api')->group(function () {
    Route::put('/permissions/update', [PermissionController::class, 'update']);
});
