<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);
    Route::prefix('users')->group(function () {
        Route::get('/', [AuthController::class, 'index']);
        Route::get('/count', [AuthController::class, 'countUser']);
        Route::get('/admin/{userId}', [AuthController::class, 'registeredUsers']);
        Route::delete('/{id}', [AuthController::class, 'destroy']);
        Route::put('/toggle-subscribe/{id}', [AuthController::class, 'toggleSubscribe']);
        Route::get('/profile', [AuthController::class, 'profile']);
        Route::post('/profile/update', [AuthController::class, 'updateProfile']);
        Route::post('/change-password', [AuthController::class, 'changePassword']);
    });
});

