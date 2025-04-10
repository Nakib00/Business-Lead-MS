<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Lead\BusinessLeadController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::prefix('users')->group(function () {
        Route::get('/', [AuthController::class, 'index']);
        Route::delete('/{id}', [AuthController::class, 'destroy']);
        Route::put('/toggle-subscribe/{id}', [AuthController::class, 'toggleSubscribe']);
    });

    Route::prefix('business-leads')->group(function () {
        Route::post('/', [BusinessLeadController::class, 'store']);
        Route::get('/{userId}', [BusinessLeadController::class, 'index']);
        Route::get('{leads_id}', [BusinessLeadController::class, 'show']);
        Route::put('{leads_id}', [BusinessLeadController::class, 'update']);
        Route::delete('{leads_id}', [BusinessLeadController::class, 'destroy']);
        Route::get('/leads/count', [BusinessLeadController::class, 'totalLeadCount']);
        Route::get('/count/{userId}', [BusinessLeadController::class, 'userLeadCount']);
        Route::post('/leads/upload', [BusinessLeadController::class, 'upload']);
    });
});
